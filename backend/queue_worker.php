<?php
// backend/queue_worker.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/wallet_helper.php';
require_once __DIR__ . '/smtp_helper.php';
require_once __DIR__ . '/providers/whatsapp_meta_service.php';

class QueueWorker {
    
    /**
     * Fetch pending emails and process them via AI
     */
    public static function processPendingEmails() {
        $db = Database::getConnection();
        
        // Fetch up to 10 pending emails across all users
        $stmt = $db->query("
            SELECT r.*, s.business_type, s.industry, u.name as user_name
            FROM received_emails r
            JOIN email_intelligence_settings s ON r.user_id = s.user_id
            JOIN users u ON r.user_id = u.id
            WHERE r.ai_status = 'pending'
            ORDER BY r.received_date ASC
            LIMIT 10
        ");
        $pendingEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = count($pendingEmails);
        if ($count === 0) {
            return 0; // No pending items
        }
        
        $processedCount = 0;
        foreach ($pendingEmails as $email) {
            $emailId = (int)$email['id'];
            $userId = (int)$email['user_id'];
            $subject = $email['subject'];
            $sender = strtolower(trim($email['sender_email']));
            $senderName = $email['sender_name'];
            $bodyText = $email['body_text'] ?: strip_tags($email['body_html']);
            
            $businessType = $email['business_type'] ?? 'Software Company';
            $industry = $email['industry'] ?? 'Technology';
            $userName = $email['user_name'];
            
            // Formulate AI system and user prompts
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
                // Log AI failure and update email row status to failed
                $db->prepare("UPDATE received_emails SET ai_status = 'failed' WHERE id = ?")->execute([$emailId]);
                $errStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message) VALUES (?, ?, ?, 'error', ?)");
                $errStmt->execute([$userId, $subject, $sender, 'AI extraction failed in background queue: ' . $e->getMessage()]);
                continue;
            }
            
            if (!$aiResponse) {
                // AI returned invalid JSON
                $db->prepare("UPDATE received_emails SET ai_status = 'failed' WHERE id = ?")->execute([$emailId]);
                $errStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message) VALUES (?, ?, ?, 'error', ?)");
                $errStmt->execute([$userId, $subject, $sender, 'AI background parsing returned invalid JSON: ' . substr($ai['text'], 0, 200)]);
                continue;
            }
            
            // Extract AI variables
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
            
            $isNoReplyCategory = in_array($category, ['Newsletter', 'Promotion', 'Spam', 'Security Alerts']);
            $suggestedReply = $isNoReplyCategory ? '' : ($aiResponse['suggested_reply'] ?? '');

            // DB Transaction for ingestion
            $db->beginTransaction();
            try {
                // 1. Update received_emails row details and status
                $updateEmail = $db->prepare("
                    UPDATE received_emails
                    SET category = ?,
                        ai_summary = ?,
                        ai_suggested_reply = ?,
                        ai_confidence_score = ?,
                        sentiment = ?,
                        priority = ?,
                        is_spam = ?,
                        spam_probability = ?,
                        extracted_data_json = ?,
                        ai_status = 'processed'
                    WHERE id = ?
                ");
                $updateEmail->execute([
                    $category, $aiResponse['short_summary'] ?? '', $suggestedReply, $confidence, $sentiment, $priority, $isSpam, $aiResponse['spam_probability'] ?? 0, $extractedDataJson, $emailId
                ]);
                
                // 2. Automated CRM Ingestion
                $companyId = null;
                $contactId = null;
                
                $extCompName = trim($aiResponse['company_name'] ?? '');
                $extPersonName = trim($aiResponse['person_name'] ?? '');
                $extEmail = strtolower(trim($aiResponse['email'] ?? $sender));
                $extPhone = trim($aiResponse['phone_number'] ?? '');
                
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
                
                // Create CRM Lead automatically if Category is "New Lead"
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
                
                // Create Task automatically if email is an Invoice or subject contains invoice keywords (and not spam)
                if ($isSpam === 0 && ($category === 'Invoice' || stripos($subject, 'invoice') !== false || stripos($subject, 'bill due') !== false)) {
                    $invoiceComp = !empty($extCompName) ? $extCompName : $senderName;
                    $invoiceAmount = (float)($aiResponse['budget'] ?? 0.00);
                    
                    $taskTitle = "Pay Invoice from " . $invoiceComp;
                    if ($invoiceAmount > 0) {
                        $taskTitle .= " - ₹" . number_format($invoiceAmount, 2);
                    }
                    
                    $taskDesc = "Invoice Details:\n";
                    $taskDesc .= "Sender: " . $senderName . " <" . $sender . ">\n";
                    $taskDesc .= "Subject: " . $subject . "\n";
                    if ($invoiceAmount > 0) {
                        $taskDesc .= "Amount: ₹" . number_format($invoiceAmount, 2) . "\n";
                    }
                    $taskDesc .= "Summary: " . ($aiResponse['short_summary'] ?? 'Invoice received via email.') . "\n";
                    if (!empty($aiResponse['requirement'])) {
                        $taskDesc .= "Details: " . $aiResponse['requirement'] . "\n";
                    }
                    
                    $rawDeadline = trim($aiResponse['deadline'] ?? '');
                    $dueDate = null;
                    if (!empty($rawDeadline)) {
                        $time = strtotime($rawDeadline);
                        if ($time !== false) {
                            $dueDate = date('Y-m-d', $time);
                        }
                    }
                    if (!$dueDate) {
                        // Default to 7 days from now
                        $dueDate = date('Y-m-d', strtotime('+7 days'));
                    }
                    
                    $stmtTask = $db->prepare("INSERT INTO crm_tasks (user_id, company_id, contact_id, lead_id, title, description, due_date, status, priority, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'high', ?)");
                    $stmtTask->execute([
                        $userId,
                        $companyId,
                        $contactId,
                        $leadId,
                        $taskTitle,
                        $taskDesc,
                        $dueDate,
                        "Automatically created from incoming invoice email."
                    ]);
                    
                    $newTaskId = $db->lastInsertId();
                    $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, 'Task Created', ?)")
                       ->execute([$userId, $companyId, $contactId, "Automated invoice payment task created: '$taskTitle' due on $dueDate."]);
                }
                
                // 3. Automation workflow triggers
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
                            $body = "Hi " . ($extPersonName ?: $senderName) . ",\n\nThank you for reaching out! We have successfully received your request, and our team will get back to you shortly.\n\nBest regards,\n" . $userName;
                            SMTPHelper::sendEmail($userId, $sender, "Welcome - Request Received: $subject", $body);
                        }
                    }
                }
                
                // Log AI success to processing logs
                $logStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message, tokens_used) VALUES (?, ?, ?, 'processed', ?, ?)");
                $logStmt->execute([$userId, $subject, $sender, "Email successfully processed. AI confidence: $confidence%.", $tokensUsed]);
                
                $db->commit();

                // Trigger active visual workflows for email_received
                try {
                    require_once __DIR__ . '/workflow_runner.php';
                    $stmtVisual = $db->prepare("SELECT * FROM automation_workflows WHERE user_id = ? AND trigger_type = 'email_received' AND is_active = 1");
                    $stmtVisual->execute([$userId]);
                    $visualWfs = $stmtVisual->fetchAll();
                    
                    $vContext = [
                        'sender_email' => $sender,
                        'sender_name' => $senderName,
                        'subject' => $subject,
                        'email_body' => $bodyText,
                        'body' => $bodyText,
                        'category' => $category,
                        'priority' => $priority,
                        'lead_id' => $leadId,
                        'contact_id' => $contactId
                    ];
                    
                    foreach ($visualWfs as $vwf) {
                        WorkflowRunner::execute($userId, $vwf, $vContext);
                    }
                } catch (Throwable $vEx) {
                    // Suppress workflow errors to keep worker robust
                }

                $processedCount++;
            } catch (Throwable $trxError) {
                $db->rollBack();
                $db->prepare("UPDATE received_emails SET ai_status = 'failed' WHERE id = ?")->execute([$emailId]);
                $errStmt = $db->prepare("INSERT INTO email_processing_logs (user_id, email_subject, sender, status, message) VALUES (?, ?, ?, 'error', ?)");
                $errStmt->execute([$userId, $subject, $sender, 'Ingestion failed: ' . $trxError->getMessage()]);
            }
        }
        
        return $processedCount;
    }

    /**
     * Fetch pending WhatsApp queue payloads and transmit them via Meta.
     */
    public static function processWhatsAppQueue() {
        $db = Database::getConnection();
        
        // Fetch up to 20 pending items across all users
        $stmt = $db->prepare("
            SELECT q.*, a.access_token, a.phone_number_id
            FROM whatsapp_queue q
            JOIN whatsapp_accounts a ON q.user_id = a.user_id AND a.status = 'connected'
            WHERE q.status = 'pending' AND (q.scheduled_at <= ? OR q.scheduled_at IS NULL)
            ORDER BY q.created_at ASC
            LIMIT 20
        ");
        $stmt->execute([date('Y-m-d H:i:s')]);
        $pendingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = count($pendingItems);
        if ($count === 0) {
            return 0;
        }
        
        $processedCount = 0;
        foreach ($pendingItems as $item) {
            $queueId = (int)$item['id'];
            $userId = (int)$item['user_id'];
            $phoneNumberId = $item['phone_number_id'];
            $recipient = $item['recipient_number'];
            $type = $item['type'];
            $attempts = (int)$item['attempts'];
            
            // Mark as processing
            $db->prepare("UPDATE whatsapp_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?")->execute([$queueId]);
            
            $payload = json_decode($item['payload_json'], true) ?: [];
            
            // Get Decrypted Token
            $encryptedToken = $item['access_token'];
            $decrypted = decryptData($encryptedToken);
            $accessToken = ($decrypted !== false) ? $decrypted : $encryptedToken;
            
            $isMock = (strpos($accessToken, 'Mock') !== false || $accessToken === 'EAAGemini' || $accessToken === 'EAAGeminiTest');
            
            try {
                // Credits balance validation
                // Campaigns and normal broadcasts are free, no credit checks needed
                $isUser = false;
                
                $metaMsgId = '';
                if (!$isMock) {
                    $response = null;
                    if ($type === 'text') {
                        $body = $payload['body'] ?? '';
                        $response = WhatsAppMetaService::sendTextMessage($userId, $phoneNumberId, $recipient, $body, $accessToken);
                    } elseif ($type === 'template') {
                        $tplName = $payload['template_name'] ?? '';
                        $tplLang = $payload['language_code'] ?? 'en';
                        $tplComponents = $payload['components'] ?? [];
                        $response = WhatsAppMetaService::sendTemplateMessage($userId, $phoneNumberId, $recipient, $tplName, $tplLang, $tplComponents, $accessToken);
                    } elseif (in_array($type, ['image', 'video', 'document', 'audio'])) {
                        $mediaId = $payload['media_id'] ?? '';
                        $filename = $payload['filename'] ?? null;
                        $response = WhatsAppMetaService::sendMediaMessage($userId, $phoneNumberId, $recipient, $type, $mediaId, $filename, $accessToken);
                    } else {
                        throw new Exception("Unsupported WhatsApp queue dispatch type: " . $type);
                    }
                    
                    // Success
                    $metaMsgId = $response['messages'][0]['id'] ?? '';
                } else {
                    $metaMsgId = 'wamid.HBgLOTE5OTk5OTk5OTk5FQIAERg5M0RCMDZFQzg2Q0I4OEFEOAA=' . uniqid();
                }
                
                $db->beginTransaction();
                try {
                    // Campaigns are free, no credit deduction needed
                    // Update queue status
                    // Update queue status
                    $db->prepare("UPDATE whatsapp_queue SET status = 'sent', error_message = NULL WHERE id = ?")->execute([$queueId]);
                    
                    // Create/Find WhatsApp Contact
                    $stmtContact = $db->prepare("SELECT id FROM whatsapp_contacts WHERE user_id = ? AND RIGHT(wa_id, 10) = RIGHT(?, 10) ORDER BY last_message_at DESC LIMIT 1");
                    $stmtContact->execute([$userId, $recipient]);
                    $waContactId = $stmtContact->fetchColumn();
                    
                    if (!$waContactId) {
                        // Create basic contact
                        $stmtCrmCon = $db->prepare("SELECT id FROM crm_contacts WHERE (phone = ? OR whatsapp = ?) AND user_id = ? LIMIT 1");
                        $stmtCrmCon->execute([$recipient, $recipient, $userId]);
                        $crmContactId = $stmtCrmCon->fetchColumn() ?: null;
                        
                        $stmtInsWaCon = $db->prepare("INSERT INTO whatsapp_contacts (user_id, contact_id, wa_id, profile_name, last_message_at, unread_count) VALUES (?, ?, ?, ?, NOW(), 0)");
                        $stmtInsWaCon->execute([$userId, $crmContactId, $recipient, 'WhatsApp Contact']);
                        $waContactId = (int)$db->lastInsertId();
                    }
                    
                    // Save Outbound Message Log
                    $bodyText = $payload['body'] ?? ($payload['template_name'] ?? "Outbound " . ucfirst($type));
                    $stmtInsMsg = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, message_id, direction, type, body, status) VALUES (?, ?, ?, 'outbound', ?, ?, 'sent')");
                    $stmtInsMsg->execute([$userId, $waContactId, $metaMsgId, $type, $bodyText]);
                    
                    // Hook to campaign logs if matching
                    $campaignLogId = isset($payload['campaign_log_id']) ? (int)$payload['campaign_log_id'] : null;
                    if ($campaignLogId) {
                        $logStatus = $isMock ? 'read' : 'sent';
                        $db->prepare("UPDATE whatsapp_campaign_logs SET message_id = ?, status = ?, sent_at = NOW() WHERE id = ?")->execute([$metaMsgId, $logStatus, $campaignLogId]);
                        
                        // Find campaign details
                        $stmtFindCamp = $db->prepare("
                            SELECT l.campaign_id 
                            FROM whatsapp_campaign_logs l 
                            WHERE l.id = ? 
                            LIMIT 1
                        ");
                        $stmtFindCamp->execute([$campaignLogId]);
                        $campId = $stmtFindCamp->fetchColumn();
                        
                        if ($campId) {
                            if ($isMock) {
                                $db->prepare("
                                    UPDATE whatsapp_campaigns 
                                    SET sent_count = sent_count + 1, 
                                        delivered_count = delivered_count + 1, 
                                        read_count = read_count + 1 
                                    WHERE id = ?
                                ")->execute([$campId]);
                            } else {
                                $db->prepare("
                                    UPDATE whatsapp_campaigns 
                                    SET sent_count = sent_count + 1 
                                    WHERE id = ?
                                ")->execute([$campId]);
                            }
                            
                            // Check if all logs are sent
                            $stmtCheckComp = $db->prepare("
                                SELECT COUNT(*) 
                                FROM whatsapp_campaign_logs 
                                WHERE campaign_id = ? AND status IN ('pending', 'queued', 'processing')
                            ");
                            $stmtCheckComp->execute([$campId]);
                            if ((int)$stmtCheckComp->fetchColumn() === 0) {
                                $db->prepare("UPDATE whatsapp_campaigns SET status = 'completed' WHERE id = ?")->execute([$campId]);
                            }
                        }
                    }
                    
                    $db->commit();
                    $processedCount++;
                } catch (Exception $trxEx) {
                    $db->rollBack();
                    throw $trxEx;
                }
                
            } catch (Throwable $e) {
                // Failure handler
                $statusVal = ($attempts >= 2) ? 'failed' : 'pending';
                $db->prepare("UPDATE whatsapp_queue SET status = ?, error_message = ? WHERE id = ?")->execute([$statusVal, $e->getMessage(), $queueId]);
                
                $campaignLogId = isset($payload['campaign_log_id']) ? (int)$payload['campaign_log_id'] : null;
                if ($campaignLogId && $statusVal === 'failed') {
                    $db->prepare("UPDATE whatsapp_campaign_logs SET status = 'failed', error_message = ? WHERE id = ?")->execute([$e->getMessage(), $campaignLogId]);
                }
            }
        }
        
        return $processedCount;
    }

    /**
     * Fetch pending email campaigns and process them.
     */
    public static function processEmailCampaignQueue() {
        $db = Database::getConnection();
        
        // Find all active campaigns
        $stmtCamps = $db->prepare("SELECT id, user_id, batch_size FROM email_campaigns WHERE status = 'Active'");
        $stmtCamps->execute();
        $activeCampaigns = $stmtCamps->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCount = 0;
        
        require_once __DIR__ . '/smtp_helper.php';
        
        foreach ($activeCampaigns as $camp) {
            $campaignId = (int)$camp['id'];
            $userId = (int)$camp['user_id'];
            $limit = (int)$camp['batch_size'];
            
            // Fetch pending logs for this campaign scheduled now or in the past
            $stmtLogs = $db->prepare("
                SELECT * FROM email_campaign_logs 
                WHERE campaign_id = ? AND status = 'Pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                LIMIT ?
            ");
            $stmtLogs->bindValue(1, $campaignId, PDO::PARAM_INT);
            $stmtLogs->bindValue(2, $limit, PDO::PARAM_INT);
            $stmtLogs->execute();
            $pending = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($pending)) {
                // If no more pending logs at all, complete the campaign
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM email_campaign_logs WHERE campaign_id = ? AND status = 'Pending'");
                $stmtCheck->execute([$campaignId]);
                if ((int)$stmtCheck->fetchColumn() === 0) {
                    $db->prepare("UPDATE email_campaigns SET status = 'Completed' WHERE id = ?")->execute([$campaignId]);
                }
                continue;
            }
            
            $sentIncrement = 0;
            $failedIncrement = 0;
            
            // Load campaign info
            $stmtInfo = $db->prepare("SELECT subject, body_html FROM email_campaigns WHERE id = ?");
            $stmtInfo->execute([$campaignId]);
            $campInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            if (!$campInfo) continue;
            
            foreach ($pending as $log) {
                $email = $log['recipient_email'];
                $vars = !empty($log['variable_data_json']) ? json_decode($log['variable_data_json'], true) : [];
                
                $subject = $campInfo['subject'];
                $body = $campInfo['body_html'];
                
                if (!empty($vars) && is_array($vars)) {
                    foreach ($vars as $k => $v) {
                        $placeholder = '{' . $k . '}';
                        $valStr = (string)$v;
                        $subject = str_replace($placeholder, $valStr, $subject);
                        $body = str_replace($placeholder, $valStr, $body);
                    }
                }
                
                try {
                    $res = SMTPHelper::sendEmail($userId, $email, $subject, $body);
                    if ($res && !empty($res['status'])) {
                        $db->prepare("UPDATE email_campaign_logs SET status = 'Sent', sent_at = NOW() WHERE id = ?")->execute([$log['id']]);
                        $sentIncrement++;
                        $processedCount++;
                    } else {
                        $errMsg = $res['message'] ?? 'Sending failed';
                        $db->prepare("UPDATE email_campaign_logs SET status = 'Failed', error_message = ? WHERE id = ?")->execute([$errMsg, $log['id']]);
                        $failedIncrement++;
                    }
                } catch (Exception $e) {
                    $db->prepare("UPDATE email_campaign_logs SET status = 'Failed', error_message = ? WHERE id = ?")->execute([$e->getMessage(), $log['id']]);
                    $failedIncrement++;
                }
            }
            
            // Update campaign counters
            $db->prepare("
                UPDATE email_campaigns 
                SET sent_count = sent_count + ?, failed_count = failed_count + ?
                WHERE id = ?
            ")->execute([$sentIncrement, $failedIncrement, $campaignId]);
        }
        
        return $processedCount;
    }
}
