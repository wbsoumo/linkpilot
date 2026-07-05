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
                } elseif (str_contains($lowerSubject, 'invoice') || str_contains($lowerSubject, 'receipt') || str_contains($lowerSubject, 'statement') || str_contains($lowerSubject, 'payment confirmation') || str_contains($lowerSubject, 'transaction alert') || str_contains($lowerSubject, 'bank statement') || str_contains($lowerSubject, 'debit alert') || str_contains($lowerSubject, 'credit alert') || str_contains($lowerSubject, 'otp code')) {
                    $matchedCategory = 'Updates';
                }
            }

            $aiResponse = null;
            $tokensUsed = 0;

            if ($matchedCategory) {
                // Filter match: bypass AI
                $isSpam = ($matchedCategory === 'Spam' || $matchedCategory === 'Promotion') ? 1 : 0;
                $category = $matchedCategory;
                $priority = 'low';
                $sentiment = 'neutral';
                $confidence = 100;
                
                $aiResponse = [
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
                    'action_items' => [],
                    'short_summary' => 'Auto-filtered email categorized as ' . $category,
                    'suggested_reply' => '',
                    'spam_probability' => ($matchedCategory === 'Spam') ? 100 : (($matchedCategory === 'Promotion') ? 80 : 0),
                    'category' => $category,
                    'priority' => $priority,
                    'sentiment' => $sentiment,
                    'confidence_score' => $confidence
                ];
                
                // Log filter match
                $logStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message) VALUES (?, ?, ?, 'success', ?)");
                $logStmt->execute([$userId, $subject, $sender, 'Auto-filtered (0 tokens used): ' . $category]);
            } else {
                // Formulate system prompt for structured extraction
                $systemPrompt = "You are an AI Email CRM Analyst. Your task is to analyze incoming business emails and extract structured properties.
User's Business Profile: Type: '$businessType', Industry: '$industry'.

You MUST return your response as a valid, parsable JSON block with the following keys, and nothing else (no extra markdown code blocks, only raw JSON):
{
  \"person_name\": \"...\",
  \"company_name\": \"...\",
  \"phone_number\": \"...\",
  \"email\": \"...\",
  \"website\": \"...\",
  \"address\": \"...\",
  \"requirement\": \"...\",
  \"budget\": 0.00,
  \"deadline\": \"...\",
  \"urgency\": \"high|medium|low\",
  \"services_requested\": \"...\",
  \"location\": \"...\",
  \"country\": \"...\",
  \"language\": \"...\",
  \"keywords\": [\"...\"],
  \"intent\": \"...\",
  \"sentiment\": \"positive|neutral|negative\",
  \"priority\": \"high|medium|low\",
  \"follow_up_required\": true|false,
  \"spam_probability\": 0,
  \"category\": \"New Lead|Existing Client|Support Request|Meeting Request|Payment|Invoice|Job Opportunity|Vendor|Partnership|Complaint|General Query|Newsletter|Promotion|Spam|Updates|Security Alerts|Personal|Other\",
  \"short_summary\": \"...\",
  \"detailed_summary\": \"...\",
  \"action_items\": [\"...\"],
  \"suggested_reply\": \"...\",
  \"confidence_score\": 90
}";

                $userPrompt = "Email Headers:\nSender: $senderName <$sender>\nSubject: $subject\nDate: {$email['received_date']}\n\nEmail Content:\n$bodyText";
                
                try {
                    $ai = callAI($systemPrompt, $userPrompt, $userId);
                    $aiResponse = json_decode($ai['text'], true);
                    $tokensUsed = $ai['tokens'];
                } catch (Throwable $e) {
                    // Log AI error
                    $errStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message) VALUES (?, ?, ?, 'error', ?)");
                    $errStmt->execute([$userId, $subject, $sender, 'AI extraction failed: ' . $e->getMessage()]);
                    continue;
                }
                
                if (!$aiResponse) {
                    $errStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message) VALUES (?, ?, ?, 'error', ?)");
                    $errStmt->execute([$userId, $subject, $sender, 'AI returned invalid JSON: ' . substr($ai['text'], 0, 200)]);
                    continue;
                }
            }
            
            // Write email to database
            $isSpam = ($aiResponse['spam_probability'] ?? 0) > 60 ? 1 : 0;
            $category = $aiResponse['category'] ?? 'General Query';
            $priority = $aiResponse['priority'] ?? 'medium';
            $sentiment = $aiResponse['sentiment'] ?? 'neutral';
            $confidence = (int)($aiResponse['confidence_score'] ?? 80);
            
            $extractedDataJson = json_encode([
                'person_name' => $aiResponse['person_name'] ?? '',
                'company_name' => $aiResponse['company_name'] ?? '',
                'phone_number' => $aiResponse['phone_number'] ?? '',
                'email' => $aiResponse['email'] ?? '',
                'website' => $aiResponse['website'] ?? '',
                'address' => $aiResponse['address'] ?? '',
                'requirement' => $aiResponse['requirement'] ?? '',
                'budget' => $aiResponse['budget'] ?? 0.00,
                'deadline' => $aiResponse['deadline'] ?? '',
                'urgency' => $aiResponse['urgency'] ?? 'medium',
                'services_requested' => $aiResponse['services_requested'] ?? '',
                'location' => $aiResponse['location'] ?? '',
                'country' => $aiResponse['country'] ?? '',
                'language' => $aiResponse['language'] ?? '',
                'keywords' => $aiResponse['keywords'] ?? [],
                'intent' => $aiResponse['intent'] ?? '',
                'follow_up_required' => $aiResponse['follow_up_required'] ?? false,
                'action_items' => $aiResponse['action_items'] ?? []
            ]);
            
            // Skip automated AI replies for low-value categories
            $isNoReplyCategory = in_array($category, ['Newsletter', 'Promotion', 'Spam', 'Security Alerts']);
            $suggestedReply = $isNoReplyCategory ? '' : ($aiResponse['suggested_reply'] ?? '');

            $db->beginTransaction();
            
            $insEmail = $db->prepare("INSERT INTO received_emails (user_id, message_id, sender_email, sender_name, recipient_email, subject, body_text, body_html, received_date, category, ai_summary, ai_suggested_reply, ai_confidence_score, sentiment, priority, is_spam, spam_probability, extracted_data_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insEmail->execute([
                $userId, $email['message_id'], $sender, $senderName, $email['recipient_email'], $subject, $email['body_text'], $email['body_html'], $email['received_date'], $category, $aiResponse['short_summary'] ?? '', $suggestedReply, $confidence, $sentiment, $priority, $isSpam, $aiResponse['spam_probability'] ?? 0, $extractedDataJson
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
            
            // Ensure company exists
            if (!empty($extCompName)) {
                $stmtC = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ?");
                $stmtC->execute([$extCompName, $userId]);
                if ($cRow = $stmtC->fetch()) {
                    $companyId = $cRow['id'];
                } else {
                    $insC = $db->prepare("INSERT INTO crm_companies (user_id, name, industry, website, status, source) VALUES (?, ?, ?, ?, 'Active', 'Email Intelligence')");
                    $insC->execute([$userId, $extCompName, $industry, $aiResponse['website'] ?? null]);
                    $companyId = $db->lastInsertId();
                    
                    $db->prepare("INSERT INTO crm_timeline (user_id, company_id, activity_type, description) VALUES (?, ?, 'Company Created', ?)")
                       ->execute([$userId, $companyId, "Company '$extCompName' automatically created via AI Email parser."]);
                }
            }
            
            // Ensure contact exists
            if (!empty($extEmail)) {
                $stmtCon = $db->prepare("SELECT id FROM crm_contacts WHERE email = ? AND user_id = ?");
                $stmtCon->execute([$extEmail, $userId]);
                if ($conRow = $stmtCon->fetch()) {
                    $contactId = $conRow['id'];
                    if ($companyId) {
                        $db->prepare("UPDATE crm_contacts SET company_id = ? WHERE id = ? AND company_id IS NULL")->execute([$companyId, $contactId]);
                    }
                } else {
                    $conName = !empty($extPersonName) ? $extPersonName : $senderName;
                    $insCon = $db->prepare("INSERT INTO crm_contacts (user_id, company_id, name, email, phone, location) VALUES (?, ?, ?, ?, ?, ?)");
                    $insCon->execute([$userId, $companyId, $conName, $extEmail, $extPhone, $aiResponse['location'] ?? null]);
                    $contactId = $db->lastInsertId();
                    
                    $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, company_id, activity_type, description) VALUES (?, ?, ?, 'Contact Created', ?)")
                       ->execute([$userId, $contactId, $companyId, "Contact '$conName' automatically created via AI Email parser."]);
                }
            }
            
            // Log Email Received Activity
            $emailDesc = "Received email: '$subject' from '$senderName'. Category: '$category'.";
            $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, 'Email Received', ?)")
               ->execute([$userId, $companyId, $contactId, $emailDesc]);
            
            // Auto Lead creation
            $leadId = null;
            if ($category === 'New Lead') {
                $leadName = !empty($extPersonName) ? $extPersonName : $senderName;
                $services = $aiResponse['services_requested'] ?? '';
                $budgetVal = (float)($aiResponse['budget'] ?? 0.00);
                $requirementsVal = $aiResponse['requirement'] ?? '';
                
                $insLead = $db->prepare("INSERT INTO crm_leads (user_id, company_id, contact_id, name, email, phone, company, budget, requirements, services_required, priority, lead_score, ai_confidence_score, lead_source, stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Email', 'New')");
                $insLead->execute([
                    $userId, $companyId, $contactId, $leadName, $extEmail, $extPhone, $extCompName, $budgetVal, $requirementsVal, $services, $priority, 60, $confidence
                ]);
                $leadId = $db->lastInsertId();
                
                $db->prepare("INSERT INTO crm_timeline (user_id, lead_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, ?, 'Lead Created', ?)")
                   ->execute([$userId, $leadId, $companyId, $contactId, "Lead '$leadName' created automatically from incoming email."]);
            }
            
            // Automation workflow triggers
            $stmtWf = $db->prepare("SELECT * FROM automation_workflows WHERE user_id = ? AND trigger_type = 'email_category_detected' AND trigger_value = ? AND is_active = 1");
            $stmtWf->execute([$userId, $category]);
            $workflows = $stmtWf->fetchAll();
            
            foreach ($workflows as $wf) {
                $actions = json_decode($wf['actions_json'], true);
                foreach ($actions as $act) {
                    if ($act['action'] === 'assign_employee' && $leadId) {
                        $employee = trim($act['value'] ?? '');
                        $db->prepare("UPDATE crm_leads SET assigned_employee = ? WHERE id = ?")->execute([$employee, $leadId]);
                    } 
                    elseif ($act['action'] === 'schedule_followup') {
                        $dueDate = date('Y-m-d', strtotime("+3 days"));
                        $db->prepare("INSERT INTO crm_tasks (user_id, company_id, contact_id, lead_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                           ->execute([$userId, $companyId, $contactId, $leadId, "Follow-up regarding '$subject'", "Automated follow-up task triggered by workflow: '{$wf['name']}'", $dueDate, 'high']);
                    } 
                    elseif ($act['action'] === 'send_welcome_email') {
                        $body = "Hi " . ($extPersonName ?: $senderName) . ",\n\nThank you for reaching out! We have successfully received your request, and our team will get back to you shortly.\n\nBest regards,\n" . $user['name'];
                        SMTPHelper::sendEmail($userId, $sender, "Welcome - Request Received: $subject", $body);
                    }
                }
            }
            
            // Log success
            $logStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message, tokens_used) VALUES (?, ?, ?, 'processed', ?, ?)");
            $logStmt->execute([$userId, $subject, $sender, "Email successfully processed. AI confidence: $confidence%.", $tokensUsed]);
            
            $db->commit();
            $syncedCount++;
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
