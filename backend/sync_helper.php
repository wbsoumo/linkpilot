<?php
// backend/sync_helper.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp_helper.php';
require_once __DIR__ . '/imap_helper.php';

class SyncHelper {
    
    /**
     * Run full sync routine for a specific user ID
     */
    public static function syncUserEmails($userId) {
        $db = Database::getConnection();
        
        // Load Settings
        $stmtSettings = $db->prepare("SELECT * FROM email_intelligence_settings WHERE user_id = ?");
        $stmtSettings->execute([$userId]);
        $settings = $stmtSettings->fetch();
        
        if (!$settings || !$settings['is_active']) {
            throw new Exception("Email Intelligence service is not active for user ID $userId.");
        }
        
        // Load User Details
        $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();
        if (!$user) {
            throw new Exception("User not found.");
        }
        
        // 1. Fetch new emails
        $newEmails = [];
        try {
            $newEmails = IMAPHelper::fetchNewEmails($userId, 10); // fetch last 10 messages
        } catch (Throwable $e) {
            // Log sync error
            $errStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, status, message) VALUES (?, 'error', ?)");
            $errStmt->execute([$userId, 'IMAP Connection Error: ' . $e->getMessage()]);
            throw $e;
        }
        
        // Fetch spam filters
        $stmtRules = $db->prepare("SELECT * FROM spam_filters WHERE user_id = ?");
        $stmtRules->execute([$userId]);
        $rules = $stmtRules->fetchAll(PDO::FETCH_ASSOC);

        // Separate rules by type
        $emailRules = [];
        $domainRules = [];
        $keywordRules = [];
        foreach ($rules as $r) {
            $val = strtolower(trim($r['filter_value']));
            if ($r['filter_type'] === 'email') {
                $emailRules[$val] = $r['category'] ?: 'Spam';
            } elseif ($r['filter_type'] === 'domain') {
                $domainRules[$val] = $r['category'] ?: 'Spam';
            } elseif ($r['filter_type'] === 'keyword') {
                $keywordRules[] = [
                    'value' => $val,
                    'category' => $r['category'] ?: 'Spam'
                ];
            }
        }

        $syncedCount = 0;
        $businessType = $settings['business_type'] ?? 'Software Company';
        $industry = $settings['industry'] ?? 'Technology';
        
        foreach ($newEmails as $email) {
            $subject = $email['subject'];
            $sender = strtolower(trim($email['sender_email']));
            $senderName = $email['sender_name'];
            $bodyText = $email['body_text'] ?: strip_tags($email['body_html']);
            
            // Get domain
            $senderDomain = '';
            $parts = explode('@', $sender);
            if (count($parts) === 2) {
                $senderDomain = strtolower(trim($parts[1]));
            }

            // Check spam / promotional filters
            $matchedCategory = null;
            if (isset($emailRules[$sender])) {
                $matchedCategory = $emailRules[$sender];
            } elseif (!empty($senderDomain) && isset($domainRules[$senderDomain])) {
                $matchedCategory = $domainRules[$senderDomain];
            } else {
                foreach ($keywordRules as $kr) {
                    if (stripos($subject, $kr['value']) !== false || stripos($bodyText, $kr['value']) !== false) {
                        $matchedCategory = $kr['category'];
                        break;
                    }
                }
            }

            // Check built-in heuristics (no-reply, alerts, updates, banking)
            if (!$matchedCategory) {
                $lowerSubject = strtolower($subject);
                $lowerSender = strtolower($sender);
                
                if (str_contains($lowerSender, 'no-reply') || str_contains($lowerSender, 'noreply') || str_contains($lowerSender, 'newsletter')) {
                    $matchedCategory = 'Newsletter';
                } elseif (str_contains($lowerSubject, 'otp') || str_contains($lowerSubject, 'verification code') || str_contains($lowerSubject, 'verify your') || str_contains($lowerSubject, 'security alert') || str_contains($lowerSubject, 'login alert') || str_contains($lowerSubject, 'password reset')) {
                    $matchedCategory = 'Security Alerts';
                } elseif (str_contains($lowerSubject, 'statement') || str_contains($lowerSubject, 'payment confirmation') || str_contains($lowerSubject, 'transaction alert') || str_contains($lowerSubject, 'bank statement') || str_contains($lowerSubject, 'debit alert') || str_contains($lowerSubject, 'credit alert') || str_contains($lowerSubject, 'otp code')) {
                    $matchedCategory = 'Updates';
                }
            }

            if ($matchedCategory) {
                // Filter match: bypass AI and process instantly (takes milliseconds)
                $isSpam = ($matchedCategory === 'Spam' || $matchedCategory === 'Promotion') ? 1 : 0;
                $category = $matchedCategory;
                $priority = 'low';
                $sentiment = 'neutral';
                $confidence = 100;
                $aiSummary = 'Auto-filtered email categorized as ' . $category;
                
                $extractedDataJson = json_encode([
                    'person_name' => '',
                    'company_name' => '',
                    'phone_number' => '',
                    'email' => $sender,
                    'website' => '',
                    'address' => '',
                    'requirement' => '',
                    'budget' => 0.00,
                    'deadline' => '',
                    'urgency' => 'low',
                    'services_requested' => '',
                    'location' => '',
                    'country' => '',
                    'language' => '',
                    'keywords' => [],
                    'intent' => 'filtered_out',
                    'follow_up_required' => false,
                    'action_items' => []
                ]);

                $db->beginTransaction();
                
                $insEmail = $db->prepare("INSERT INTO received_emails (user_id, message_id, sender_email, sender_name, recipient_email, subject, body_text, body_html, received_date, category, ai_summary, ai_suggested_reply, ai_confidence_score, sentiment, priority, is_spam, spam_probability, extracted_data_json, ai_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, 'processed')");
                $insEmail->execute([
                    $userId, $email['message_id'], $sender, $senderName, $email['recipient_email'], $subject, $email['body_text'], $email['body_html'], $email['received_date'], $category, $aiSummary, $confidence, $sentiment, $priority, $isSpam, ($matchedCategory === 'Spam') ? 100 : 80, $extractedDataJson
                ]);
                
                $receivedEmailId = $db->lastInsertId();
                
                // Save attachments
                foreach ($email['attachments'] as $att) {
                    $attUploadDir = __DIR__ . '/uploads/attachments/';
                    if (!is_dir($attUploadDir)) {
                        mkdir($attUploadDir, 0755, true);
                    }
                    $attUniqueName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $att['filename']);
                    $attDest = $attUploadDir . $attUniqueName;
                    
                    if (file_put_contents($attDest, $att['content'])) {
                        $attPath = 'backend/uploads/attachments/' . $attUniqueName;
                        $insAtt = $db->prepare("INSERT INTO email_attachments (received_email_id, filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                        $insAtt->execute([
                            $receivedEmailId, $att['filename'], $attPath, $att['file_type'], $att['file_size']
                        ]);
                    }
                }
                
                // Log filter match
                $logStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message) VALUES (?, ?, ?, 'success', ?)");
                $logStmt->execute([$userId, $subject, $sender, 'Auto-filtered (0 tokens used): ' . $category]);
                
                $db->commit();
                $syncedCount++;
            } else {
                // Save as pending AI process immediately without hitting AI! (takes milliseconds)
                $db->beginTransaction();
                
                $insEmail = $db->prepare("INSERT INTO received_emails (user_id, message_id, sender_email, sender_name, recipient_email, subject, body_text, body_html, received_date, category, ai_summary, ai_suggested_reply, ai_confidence_score, sentiment, priority, is_spam, spam_probability, extracted_data_json, ai_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'General Query', 'Analyzing email details...', '', 0, 'neutral', 'medium', 0, 0, '{}', 'pending')");
                $insEmail->execute([
                    $userId, $email['message_id'], $sender, $senderName, $email['recipient_email'], $subject, $email['body_text'], $email['body_html'], $email['received_date']
                ]);
                
                $receivedEmailId = $db->lastInsertId();
                
                // Save attachments
                foreach ($email['attachments'] as $att) {
                    $attUploadDir = __DIR__ . '/uploads/attachments/';
                    if (!is_dir($attUploadDir)) {
                        mkdir($attUploadDir, 0755, true);
                    }
                    $attUniqueName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $att['filename']);
                    $attDest = $attUploadDir . $attUniqueName;
                    
                    if (file_put_contents($attDest, $att['content'])) {
                        $attPath = 'backend/uploads/attachments/' . $attUniqueName;
                        $insAtt = $db->prepare("INSERT INTO email_attachments (received_email_id, filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                        $insAtt->execute([
                            $receivedEmailId, $att['filename'], $attPath, $att['file_type'], $att['file_size']
                        ]);
                    }
                }
                
                // Log queueing
                $logStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message) VALUES (?, ?, ?, 'pending', 'Email queued for background AI processing.')");
                $logStmt->execute([$userId, $subject, $sender]);
                
                $db->commit();
                $syncedCount++;
            }
        }
        
        // Update Scheduler next timestamps
        $syncInterval = $settings['sync_interval_minutes'] ?? 60;
        $nextSync = date('Y-m-d H:i:s', strtotime("+$syncInterval minutes"));
        
        $db->prepare("UPDATE email_intelligence_settings SET last_sync_at = NOW(), next_sync_at = ? WHERE user_id = ?")
           ->execute([$nextSync, $userId]);
           
        return [
            'emails_synced' => $syncedCount,
            'last_sync_at' => date('Y-m-d H:i:s'),
            'next_sync_at' => $nextSync
        ];
    }
}
