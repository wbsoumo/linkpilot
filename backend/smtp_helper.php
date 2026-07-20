<?php
// backend/smtp_helper.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/libs/PHPMailer/Exception.php';
require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SMTPHelper {
    
    /**
     * Send email using user's custom SMTP configuration
     */
    public static function sendEmail($userId, $recipientEmail, $subject, $body, $attachments = [], $senderEmail = null, $originalMessageId = null, $ccEmails = [], $campaignLogId = null) {
        $db = Database::getConnection();
        
        $trackingEnabled = false;
        $trackingId = null;
        try {
            $stmtTrack = $db->prepare("SELECT email_open_tracking FROM user_profiles WHERE user_id = ?");
            $stmtTrack->execute([$userId]);
            $trackingVal = $stmtTrack->fetchColumn();
            if ($trackingVal !== false) {
                $trackingEnabled = (int)$trackingVal === 1;
            } else {
                $trackingEnabled = true;
            }
        } catch (Exception $e) {
            $trackingEnabled = true;
        }

        if ($trackingEnabled) {
            $trackingId = bin2hex(random_bytes(16));
            $pixelUrl = "https://linkpilot.work/o/" . $trackingId;
            $pixelHtml = "\n" . '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:none;width:1px;height:1px;border:none;">';
            $body .= $pixelHtml;
            
            try {
                $emailType = $campaignLogId ? 'campaign' : 'individual';
                $stmtInsTrack = $db->prepare("
                    INSERT INTO email_tracking (tracking_id, user_id, email_type, campaign_log_id, recipient_email, subject, open_count)
                    VALUES (?, ?, ?, ?, ?, ?, 0)
                ");
                $stmtInsTrack->execute([$trackingId, $userId, $emailType, $campaignLogId, $recipientEmail, $subject]);
            } catch (Exception $e) {
                error_log("Failed to insert email_tracking record: " . $e->getMessage());
            }
        }

        // Intercept and route via Gmail API if user connected Google integration
        require_once __DIR__ . '/external_apps_helper.php';
        if (ExternalAppsHelper::isGoogleConnected($userId)) {
            $gmailResult = ExternalAppsHelper::sendGmailEmail($userId, $recipientEmail, $subject, $body, $attachments, $originalMessageId, $ccEmails);
            if ($gmailResult['status']) {
                self::logSentEmail($userId, $recipientEmail, $subject, $body, 'sent', null, $trackingId);
                updateStatistic($userId, 'emails_sent');
                logActivity($userId, "Sent outreach email via Gmail API to: " . $recipientEmail);
                return [
                    "status" => true,
                    "message" => "Email sent successfully via Gmail API."
                ];
            } else {
                return $gmailResult;
            }
        }

        $db = Database::getConnection();
        
        // 1. Fetch default SMTP Account, fallback to first configured
        $smtp = null;
        if ($senderEmail) {
            $stmt = $db->prepare("SELECT * FROM smtp_accounts WHERE user_id = ? AND sender_email = ? LIMIT 1");
            $stmt->execute([$userId, $senderEmail]);
            $smtp = $stmt->fetch();
        }
        
        if (!$smtp) {
            $stmt = $db->prepare("SELECT * FROM smtp_accounts WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $smtp = $stmt->fetch();
        }
        
        if (!$smtp) {
            self::logSentEmail($userId, $recipientEmail, $subject, $body, 'failed', 'SMTP Configuration missing. Please configure SMTP in settings.');
            return [
                "status" => false,
                "message" => "SMTP configuration not found. Please connect your SMTP account first."
            ];
        }
        
        // 2. Decrypt Password
        $decryptedPassword = decryptData($smtp['password']);
        if ($decryptedPassword === false) {
            self::logSentEmail($userId, $recipientEmail, $subject, $body, 'failed', 'Failed to decrypt SMTP password. Please reconnect SMTP settings.');
            return [
                "status" => false,
                "message" => "SMTP decryption failed. Please update your SMTP password in settings."
            ];
        }

        // 3. Fetch template preference and profile details for wrapper
        $templateId = 'minimalist';
        try {
            $stmtUser = $db->prepare("SELECT active_email_template FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $userRes = $stmtUser->fetch();
            if ($userRes && !empty($userRes['active_email_template'])) {
                $templateId = $userRes['active_email_template'];
            }
        } catch (Exception $e) {}

        $senderDetails = [
            'name' => $smtp['sender_name'],
            'email' => $smtp['sender_email'],
            'title' => '',
            'company' => '',
            'linkedin' => ''
        ];

        try {
            $stmtProfile = $db->prepare("SELECT job_title, company_name, linkedin_url FROM user_profiles WHERE user_id = ?");
            $stmtProfile->execute([$userId]);
            $profRes = $stmtProfile->fetch();
            if ($profRes) {
                $senderDetails['title'] = $profRes['job_title'] ?? '';
                $senderDetails['company'] = $profRes['company_name'] ?? '';
                $senderDetails['linkedin'] = $profRes['linkedin_url'] ?? '';
            }
        } catch (Exception $e) {}

        require_once __DIR__ . '/email_template_helper.php';
        $formattedBody = (strpos($body, '<p>') === false && strpos($body, '<br>') === false && strpos($body, '<br/>') === false) ? nl2br($body) : $body;
        $wrappedBody = EmailTemplateHelper::wrap($formattedBody, $templateId, $senderDetails);
        
        // 4. Setup PHPMailer
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->CharSet    = 'UTF-8';
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $decryptedPassword;
            
            // Port and Security
            $port = (int)$smtp['port'];
            $mail->Port = $port;
            
            if ($port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            
            // Bypass certificate verification mismatch errors
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Recipients
            $mail->setFrom($smtp['sender_email'], $smtp['sender_name']);
            $mail->addAddress($recipientEmail);
            if (!empty($ccEmails)) {
                foreach ($ccEmails as $cc) {
                    $mail->addCC($cc);
                }
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $wrappedBody;
            // Plain text version
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>'], ["\n", "\n", "\n"], $body));
            
            // Attachments
            foreach ($attachments as $att) {
                if (isset($att['path']) && is_file($att['path'])) {
                    $mail->addAttachment($att['path'], $att['name'] ?? '');
                }
            }
            
            // Custom threading headers to prevent spam and link conversation
            if ($originalMessageId) {
                $mail->addCustomHeader('In-Reply-To', $originalMessageId);
                $mail->addCustomHeader('References', $originalMessageId);
            }
            
            // Send
            $mail->send();
            
            // Log and update statistics
            self::logSentEmail($userId, $recipientEmail, $subject, $body, 'sent', null, $trackingId);
            updateStatistic($userId, 'emails_sent');
            logActivity($userId, "Sent outreach email to: " . $recipientEmail);
            
            return [
                "status" => true,
                "message" => "Email sent successfully."
            ];
            
        } catch (Exception $e) {
            $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
            self::logSentEmail($userId, $recipientEmail, $subject, $body, 'failed', $errorMsg, $trackingId);
            return [
                "status" => false,
                "message" => "Mailer Error: " . $errorMsg
            ];
        }
    }
    
    /**
     * Test SMTP configuration settings
     */
    public static function testConnection($host, $port, $username, $password, $senderName, $senderEmail) {
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet    = 'UTF-8';
            $mail->isSMTP();
            $mail->Timeout     = 10; // Set SMTP connection timeout to 10 seconds
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $username;
            $mail->Password   = $password;
            $mail->Port       = (int)$port;
            
            if ((int)$port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ((int)$port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            
            // Bypass certificate verification mismatch errors
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            $mail->setFrom($senderEmail, $senderName);
            $mail->addAddress($senderEmail); // send to self for testing
            
            $mail->isHTML(true);
            $mail->Subject = 'LinkPilot AI SMTP Test Connection';
            $mail->Body    = '<h3>SMTP Setup Test</h3><p>Hello! If you are reading this email, your SMTP configuration inside LinkPilot AI is working perfectly.</p>';
            
            $mail->send();
            return [
                "status" => true,
                "message" => "SMTP Connection Test Successful. Verification email sent."
            ];
        } catch (Exception $e) {
            return [
                "status" => false,
                "message" => "SMTP Test Failed: " . ($mail->ErrorInfo ?: $e->getMessage())
            ];
        }
    }
    
    /**
     * Log email transmission to DB
     */
    private static function logSentEmail($userId, $recipientEmail, $subject, $body, $status, $errorMessage = null, $trackingId = null) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO sent_emails (user_id, recipient_email, subject, body, status, error_message) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $recipientEmail, $subject, $body, $status, $errorMessage]);
            $sentEmailId = $db->lastInsertId();
            
            if ($trackingId && $sentEmailId) {
                $stmtUpdate = $db->prepare("UPDATE email_tracking SET sent_email_id = ? WHERE tracking_id = ?");
                $stmtUpdate->execute([$sentEmailId, $trackingId]);
            }
        } catch (Exception $e) {
            // Silence exceptions to prevent breaking execution flows
        }
    }
}
