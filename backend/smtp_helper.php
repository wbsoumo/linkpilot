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
    public static function sendEmail($userId, $recipientEmail, $subject, $body) {
        $db = Database::getConnection();
        
        // 1. Fetch SMTP Account
        $stmt = $db->prepare("SELECT * FROM smtp_accounts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $smtp = $stmt->fetch();
        
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
        
        // 3. Setup PHPMailer
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
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
            
            // Recipients
            $mail->setFrom($smtp['sender_email'], $smtp['sender_name']);
            $mail->addAddress($recipientEmail);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            // Plain text version
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>'], ["\n", "\n", "\n"], $body));
            
            // Send
            $mail->send();
            
            // Log and update statistics
            self::logSentEmail($userId, $recipientEmail, $subject, $body, 'sent');
            updateStatistic($userId, 'emails_sent');
            logActivity($userId, "Sent outreach email to: " . $recipientEmail);
            
            return [
                "status" => true,
                "message" => "Email sent successfully."
            ];
            
        } catch (Exception $e) {
            $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
            self::logSentEmail($userId, $recipientEmail, $subject, $body, 'failed', $errorMsg);
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
            $mail->isSMTP();
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
    private static function logSentEmail($userId, $recipientEmail, $subject, $body, $status, $errorMessage = null) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO sent_emails (user_id, recipient_email, subject, body, status, error_message) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $recipientEmail, $subject, $body, $status, $errorMessage]);
        } catch (Exception $e) {
            // Silence exceptions to prevent breaking execution flows
        }
    }
}
