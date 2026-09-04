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
        WhatsAppMetaService::logDebug("QueueWorker::processWhatsAppQueue() found {$count} pending queue item(s).");
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
            
            WhatsAppMetaService::logDebug("QueueWorker processing item ID={$queueId}, type={$type}, recipient={$recipient}, attempts={$attempts}");
            
            // Mark as processing
            $db->prepare("UPDATE whatsapp_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?")->execute([$queueId]);
            
            $payload = json_decode($item['payload_json'], true) ?: [];
            
            // Get Decrypted Token
            $encryptedToken = $item['access_token'];
            $decrypted = decryptData($encryptedToken);
            $accessToken = ($decrypted !== false) ? $decrypted : $encryptedToken;
            
            $isMock = (strpos($accessToken, 'Mock') !== false || $accessToken === 'EAAGemini' || $accessToken === 'EAAGeminiTest');
            
            try {
                if ($type === 'inbound_ai_reply') {
                    self::processInboundAiReply($db, $queueId, $userId, $item, $payload, $accessToken, $isMock);
                    $processedCount++;
                    continue;
                }

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
     * Process asynchronous inbound AI reply queue item
     */
    private static function processInboundAiReply($db, $queueId, $userId, $item, $payload, $accessToken, $isMock) {
        $phoneNumberId = $payload['phone_number_id'] ?? $item['phone_number_id'];
        $fromWaId = $payload['from_wa_id'] ?? $item['recipient_number'];
        $waContactId = (int)($payload['wa_contact_id'] ?? 0);
        $crmContactId = !empty($payload['crm_contact_id']) ? (int)$payload['crm_contact_id'] : null;
        $profileName = $payload['profile_name'] ?? 'WhatsApp Contact';
        $bodyText = $payload['body_text'] ?? '';
        $messageDbId = (int)($payload['message_db_id'] ?? 0);
        $businessName = $payload['business_name'] ?? 'Business';

        // 1. Fetch Today's & Upcoming Meetings
        $stmtMeetings = $db->prepare("SELECT title, description, start_time, location, meet_link FROM crm_meetings WHERE user_id = ? AND start_time >= NOW() ORDER BY start_time ASC LIMIT 5");
        $stmtMeetings->execute([$userId]);
        $meetings = $stmtMeetings->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Pending/Active Tasks
        $stmtTasks = $db->prepare("SELECT title, description, status, due_date, due_time, priority, meet_link FROM crm_tasks WHERE user_id = ? AND status != 'Completed' ORDER BY due_date ASC LIMIT 8");
        $stmtTasks->execute([$userId]);
        $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        // 3. Fetch Recent CRM Leads
        $stmtLeads = $db->prepare("SELECT name, company, email, phone, budget, stage, priority, requirements FROM crm_leads WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmtLeads->execute([$userId]);
        $leads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

        // 4. Fetch Recent Inbox Emails
        $stmtEmails = $db->prepare("SELECT sender_name, sender_email, subject, category, ai_summary, received_date FROM received_emails WHERE user_id = ? AND is_spam = 0 AND is_archived = 0 ORDER BY received_date DESC LIMIT 5");
        $stmtEmails->execute([$userId]);
        $emails = $stmtEmails->fetchAll(PDO::FETCH_ASSOC);

        // 5. Fetch Timeline Remarks for this Contact
        $timeline = [];
        if ($crmContactId) {
            $stmtTimeline = $db->prepare("SELECT activity_type, description, created_at FROM crm_timeline WHERE user_id = ? AND contact_id = ? ORDER BY created_at DESC LIMIT 8");
            $stmtTimeline->execute([$userId, $crmContactId]);
            $timeline = $stmtTimeline->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fetch WhatsApp Contact Details
        $stmtWaCon = $db->prepare("SELECT chat_summary, last_message_at FROM whatsapp_contacts WHERE id = ? LIMIT 1");
        $stmtWaCon->execute([$waContactId]);
        $waContact = $stmtWaCon->fetch(PDO::FETCH_ASSOC);

        $chatSummaryText = $waContact ? ($waContact['chat_summary'] ?? '') : '';

        // 6. Fetch Conversation History (last 10 messages)
        $stmtChatHist = $db->prepare("SELECT direction, body, created_at FROM whatsapp_messages WHERE wa_contact_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmtChatHist->execute([$waContactId]);
        $chatHist = array_reverse($stmtChatHist->fetchAll(PDO::FETCH_ASSOC));

        // Build context strings
        $leadsCtx = "";
        foreach ($leads as $l) {
            $leadsCtx .= "- Lead: {$l['name']} | Company: {$l['company']} | Budget: INR {$l['budget']} | Stage: {$l['stage']} | Requirements: {$l['requirements']}\n";
        }
        
        $tasksCtx = "";
        foreach ($tasks as $t) {
            $tasksCtx .= "- Task: {$t['title']} | Due: {$t['due_date']} @ " . ($t['due_time'] ?: 'N/A') . " | Status: {$t['status']} | Priority: {$t['priority']}" . (!empty($t['meet_link']) ? " | Meet Link: {$t['meet_link']}" : "") . "\n";
        }
        
        $meetingsCtx = "";
        foreach ($meetings as $m) {
            $meetingsCtx .= "- Meeting: {$m['title']} | Time: {$m['start_time']} | Location: {$m['location']}" . (!empty($m['meet_link']) ? " | Meet Link: {$m['meet_link']}" : "") . "\n";
        }
        
        $emailsCtx = "";
        foreach ($emails as $e) {
            $emailsCtx .= "- Email from {$e['sender_name']} <{$e['sender_email']}> | Subject: {$e['subject']} | Category: {$e['category']} | Summary: {$e['ai_summary']}\n";
        }
        
        $remarksCtx = "";
        foreach ($timeline as $tl) {
            $remarksCtx .= "- [{$tl['created_at']}] {$tl['activity_type']}: {$tl['description']}\n";
        }
        
        $chatHistCtx = "";
        foreach ($chatHist as $ch) {
            $sender = ($ch['direction'] === 'inbound') ? $profileName : "You (AI)";
            $chatHistCtx .= "- [{$ch['created_at']}] $sender: {$ch['body']}\n";
        }

        $stmtUser = $db->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $userProfileName = $stmtUser->fetchColumn() ?: $businessName;

        // Fetch trained agent settings
        $agentKb = "";
        $agentRules = "";
        $agentCaps = "";
        $websiteUrl = "";
        
        $stmtAgent = $db->prepare("SELECT * FROM whatsapp_agents WHERE user_id = ? AND status = 'live' LIMIT 1");
        $stmtAgent->execute([$userId]);
        $waAgent = $stmtAgent->fetch(PDO::FETCH_ASSOC);
        
        if ($waAgent) {
            $websiteUrl = $waAgent['website_url'] ?? '';
            if (!empty($waAgent['knowledge_base'])) {
                $agentKb = "\n--- BUSINESS KNOWLEDGE BASE (SCRAPED FROM " . $waAgent['website_url'] . ") ---\n" . $waAgent['knowledge_base'] . "\n";
            }
            if (!empty($waAgent['ground_rules'])) {
                $agentRules = "\n--- BUSINESS GROUND RULES & INSTRUCTIONS ---\n" . $waAgent['ground_rules'] . "\n";
            }
            if (!empty($waAgent['capabilities'])) {
                $capsArray = explode(',', $waAgent['capabilities']);
                $agentCaps = "\n--- AGENT CAPABILITIES ---\n";
                if (in_array('faq_support', $capsArray)) {
                    $agentCaps .= "- FAQ / Support Role: Answer customer questions regarding the business using the business knowledge base details provided above. If the answer cannot be found in the knowledge base, state politely that you will double-check with the team and get back to them.\n";
                }
                if (in_array('human_handoff', $capsArray)) {
                    $agentCaps .= "- Human Handoff Role: If the customer specifically asks to talk to a human, manager, or representative, OR expresses high frustration, acknowledge this request politely and state that a human manager has been notified and will contact them directly.\n";
                }
            }
        }

        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');
        $systemPrompt = "You are a professional human representative/assistant for $userProfileName (representing the business '$businessName'" . (!empty($websiteUrl) ? " operating at $websiteUrl" : "") . ").
Your goal is to reply to incoming customer messages on behalf of $userProfileName / $businessName.
" . $agentCaps . $agentRules . $agentKb . "

CRITICAL IDENTITY & CONVERSATIONAL RULES:
1. Never say you are an 'AI', 'AI CRM', 'bot', 'AI assistant', 'CRM assistant', or a chatbot.
2. You are a real professional human representing $userProfileName. If asked who you are, what your name is, or who you represent, reply that you are $userProfileName or representing $userProfileName.
3. Respond like a real professional human. Use natural, warm, confident, conversational language with occasional fillers such as \"Hmm...\", \"Okay\", \"I see\", \"Sure\", or \"Got it\" where they fit naturally.
4. Avoid sounding robotic, overly formal, or repetitive. Responses must feel like they are coming from a knowledgeable person having a genuine conversation, while remaining clear, concise, and respectful.
5. Stay strictly on-topic. Focus entirely on the customer's business relationship, leads, bookings, invoices, and sales inquiries. Do not discuss general knowledge or unrelated subjects.
6. If you need to schedule a meeting, check the meetings/availability list below. Propose slot options that do not conflict with existing meetings.
7. If you do not have enough information to close a deal or answer a technical question, ask polite clarifying questions.
7.5 If the customer asks for the link or details of an upcoming scheduled meeting/task in the context:
    - Check if there is a 'Meet Link' or location provided for that task/meeting in the context.
    - If present, you MUST write the link directly in your 'suggested_reply' so they can join.
8. CRITICAL MEETING SCHEDULING FLOW:
   - If the customer mentions scheduling a meeting, call, or appointment, OR is currently replying to your scheduling request with a datetime or their Gmail/email address:
      - Even if the latest message is just their email address (e.g. 'myemail@gmail.com') or a date/time, you MUST treat this as part of the ongoing scheduling flow and extract the task info.
      - Check the conversation history to see if a date and time are finalized. If not, set 'meeting_flow_stage' to 'ask_datetime' and ask them to select a date and time in your 'suggested_reply'.
      - If a date and time are finalized but we do not have their Gmail/email address, set 'meeting_flow_stage' to 'ask_gmail' and ask them to provide their Gmail address in your 'suggested_reply' so you can send them a calendar invite.
      - If both date/time and Gmail are provided (either in the latest message or context history), set 'meeting_flow_stage' to 'finalize' (and specify the finalized due_date, due_time, and contact_gmail).
      - If they refuse or cannot provide an email, set 'meeting_flow_stage' to 'finalize' and do not ask again.
9. You MUST return your response as a valid, parsable JSON block with the following keys, and nothing else (no extra markdown blocks outside JSON):
{
  \"summary\": \"Brief 1-sentence summary of the user message\",
  \"suggested_reply\": \"The message text that you will automatically write back to the customer on WhatsApp. Write it in a natural, friendly, human-like professional tone representing $userProfileName. Do not use generic placeholders.\",
  \"sentiment\": \"positive|neutral|negative\",
  \"extracted_lead\": {
    \"person_name\": \"...\",
    \"company_name\": \"...\",
    \"budget\": 0.00,
    \"services\": \"...\",
    \"timeline\": \"...\",
    \"priority\": \"high|medium|low\"
  },
  \"extracted_task\": {
    \"title\": \"Describe the task/meeting to set, e.g., 'Follow up on proposed quote' or 'Introduce services call'\",
    \"description\": \"Any additional task or meeting details/description requested by the sender\",
    \"category\": \"Meeting|Follow-up|Reply|Arrange|General\",
    \"due_date\": \"YYYY-MM-DD\",
    \"due_time\": \"HH:MM:SS\" or null,
    \"priority\": \"high|medium|low\",
    \"contact_gmail\": \"Gmail address if provided, else null\",
    \"meeting_flow_stage\": \"ask_datetime|ask_gmail|finalize|none\"
  }
}

TODAY'S DATE AND TIME: $currentDate $currentTime.

--- USER WORKSPACE CONTEXT ---
## RECENT PIPELINE LEADS:
" . ($leadsCtx ?: "No leads in pipeline.") . "

## RECENT TASKS:
" . ($tasksCtx ?: "No active tasks.") . "

## UPCOMING MEETINGS & AVAILABILITY:
" . ($meetingsCtx ?: "No upcoming meetings scheduled.") . "

## RECENT INBOX EMAILS:
" . ($emailsCtx ?: "No recent emails.") . "

## RECENT REMARKS & TIMELINE FOR THIS CONTACT:
" . ($remarksCtx ?: "No timeline logs found.") . "

## CONVERSATION HISTORY SUMMARY (PREVIOUS CONTEXT):
" . ($chatSummaryText ?: "No previous conversation summary available.") . "

## CONVERSATION HISTORY (LAST 10 MESSAGES):
" . ($chatHistCtx ?: "No previous chat history.") . "
";

        $userPrompt = "Sender Name: $profileName\nMessage: $bodyText";

        WhatsAppMetaService::logDebug("processInboundAiReply: Calling callAI(...) for userId={$userId}, profileName='{$profileName}', bodyText='{$bodyText}'");

        try {
            $ai = callAI($systemPrompt, $userPrompt, $userId);
            $aiRes = json_decode($ai['text'], true);
            WhatsAppMetaService::logDebug("callAI completed. Raw AI response length: " . strlen($ai['text'] ?? '') . " | Text snippet: " . substr($ai['text'] ?? '', 0, 150));
        } catch (Throwable $aiEx) {
            WhatsAppMetaService::logDebug("callAI Exception for userId={$userId}: " . $aiEx->getMessage());
            throw $aiEx;
        }
        
        $aiSummary = null;
        $aiSuggestedReply = null;
        $sentiment = 'neutral';
        $secondWaMessageText = null;

        if ($aiRes) {
            $aiSummary = $aiRes['summary'] ?? null;
            $aiSuggestedReply = $aiRes['suggested_reply'] ?? null;
            $sentiment = $aiRes['sentiment'] ?? 'neutral';

            WhatsAppMetaService::logDebug("AI suggested_reply extracted: " . ($aiSuggestedReply ?: '[EMPTY]'));

            // Update inbound message with AI summary & sentiment
            if ($messageDbId > 0) {
                $db->prepare("UPDATE whatsapp_messages SET ai_summary = ?, ai_suggested_reply = ?, sentiment = ? WHERE id = ?")
                   ->execute([$aiSummary, $aiSuggestedReply, $sentiment, $messageDbId]);
            }

            // Auto CRM Lead Creation
            $leadInfo = $aiRes['extracted_lead'] ?? [];
            if (is_array($leadInfo) && (!empty($leadInfo['person_name']) || !empty($leadInfo['company_name']))) {
                $leadName = (!empty($leadInfo['person_name']) && is_string($leadInfo['person_name'])) ? trim($leadInfo['person_name']) : $profileName;
                $leadCompany = (!empty($leadInfo['company_name']) && is_string($leadInfo['company_name'])) ? trim($leadInfo['company_name']) : '';
                $leadBudget = (float)($leadInfo['budget'] ?? 0.00);
                $leadServices = (!empty($leadInfo['services']) && is_string($leadInfo['services'])) ? trim($leadInfo['services']) : '';
                $leadPriority = (!empty($leadInfo['priority']) && is_string($leadInfo['priority'])) ? trim($leadInfo['priority']) : 'medium';
                
                $stmtLeadCheck = $db->prepare("SELECT id FROM crm_leads WHERE (phone = ? OR email = ?) AND user_id = ? LIMIT 1");
                $stmtLeadCheck->execute([$fromWaId, '', $userId]);
                if (!$stmtLeadCheck->fetchColumn()) {
                    $stmtLeadIns = $db->prepare("INSERT INTO crm_leads (user_id, contact_id, name, phone, company, budget, services_required, priority, lead_source, stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'WhatsApp', 'New')");
                    $stmtLeadIns->execute([$userId, $crmContactId, $leadName, $fromWaId, $leadCompany, $leadBudget, $leadServices, $leadPriority]);
                    $newLeadId = $db->lastInsertId();
                    
                    $db->prepare("INSERT INTO crm_timeline (user_id, lead_id, contact_id, activity_type, description) VALUES (?, ?, ?, 'Lead Created', ?)")
                       ->execute([$userId, $newLeadId, $crmContactId, "Lead '$leadName' automatically created via AI WhatsApp parser."]);
                }
            }

            // Auto CRM Task / Meeting Creation
            $taskInfo = $aiRes['extracted_task'] ?? null;
            if ($taskInfo && is_array($taskInfo) && !empty($taskInfo['title'])) {
                $taskTitle = trim($taskInfo['title']);
                $taskDesc = trim($taskInfo['description'] ?? '');
                $taskCategory = trim($taskInfo['category'] ?? 'General');
                $taskDueDate = !empty($taskInfo['due_date']) ? trim($taskInfo['due_date']) : date('Y-m-d');
                $taskDueTime = !empty($taskInfo['due_time']) ? trim($taskInfo['due_time']) : null;
                $taskPriority = trim($taskInfo['priority'] ?? 'medium');
                $contactGmail = !empty($taskInfo['contact_gmail']) ? trim($taskInfo['contact_gmail']) : null;
                $meetingFlowStage = trim($taskInfo['meeting_flow_stage'] ?? 'none');

                if ($meetingFlowStage === 'finalize' && ($taskCategory === 'Meeting' || strpos($taskTitle, '[Meeting]') !== false)) {
                    $companyId = null;
                    if ($crmContactId) {
                        $stmtComp = $db->prepare("SELECT company_id FROM crm_contacts WHERE id = ? LIMIT 1");
                        $stmtComp->execute([$crmContactId]);
                        $companyId = $stmtComp->fetchColumn() ?: null;
                    }
                    $leadId = null;
                    if ($crmContactId) {
                        $stmtLead = $db->prepare("SELECT id FROM crm_leads WHERE contact_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
                        $stmtLead->execute([$crmContactId, $userId]);
                        $leadId = $stmtLead->fetchColumn() ?: null;
                    }

                    if (strpos($taskTitle, '[Meeting]') === false) {
                        $taskTitle = "[Meeting] " . $taskTitle;
                    }

                    $stmtTaskIns = $db->prepare("INSERT INTO crm_tasks (user_id, company_id, contact_id, lead_id, title, description, due_date, due_time, priority, status, sync_to_calendar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 1)");
                    $stmtTaskIns->execute([
                        $userId, $companyId, $crmContactId, $leadId, $taskTitle, $taskDesc, $taskDueDate, $taskDueTime, $taskPriority
                    ]);
                    $newTaskId = $db->lastInsertId();

                    $meetLink = null;
                    try {
                        require_once __DIR__ . '/external_apps_helper.php';
                        if (ExternalAppsHelper::isGoogleConnected($userId)) {
                            $meetLink = ExternalAppsHelper::generateGoogleMeetForTask($userId, $newTaskId);
                        }
                    } catch (Throwable $meetEx) {
                        WhatsAppMetaService::logDebug("Google Meet generation failed: " . $meetEx->getMessage());
                    }

                    if (empty($meetLink)) {
                        $chars = 'abcdefghijklmnopqrstuvwxyz';
                        $part1 = substr(str_shuffle($chars), 0, 3);
                        $part2 = substr(str_shuffle($chars), 0, 4);
                        $part3 = substr(str_shuffle($chars), 0, 3);
                        $meetLink = "https://meet.google.com/{$part1}-{$part2}-{$part3}";
                        $db->prepare("UPDATE crm_tasks SET meet_link = ? WHERE id = ?")->execute([$meetLink, $newTaskId]);
                    }

                    if ($contactGmail) {
                        try {
                            ExternalAppsHelper::sendTaskMeetingInviteEmail($userId, $newTaskId, $meetLink, $contactGmail);
                        } catch (Throwable $emailEx) {
                            WhatsAppMetaService::logDebug("Auto calendar email invite failed: " . $emailEx->getMessage());
                        }
                    }

                    $timingFormatted = date('jS F Y', strtotime($taskDueDate)) . ' at ' . ($taskDueTime ? substr($taskDueTime, 0, 5) : '09:00');
                    $secondWaMessageText = "📅 *Meeting Scheduled Details*:\n";
                    $secondWaMessageText .= "• *Subject*: " . str_replace('[Meeting] ', '', $taskTitle) . "\n";
                    $secondWaMessageText .= "• *Time*: " . $timingFormatted . "\n";
                    if (!empty($taskDesc)) {
                        $secondWaMessageText .= "• *Agenda*: " . $taskDesc . "\n";
                    }
                    if ($meetLink) {
                        $secondWaMessageText .= "• *Google Meet Link*: " . $meetLink . "\n";
                    }
                    if ($contactGmail) {
                        $secondWaMessageText .= "\nI have sent a calendar invitation email to your email: *" . $contactGmail . "* containing the invite attachment. Looking forward to our call!";
                    }

                    $db->prepare("INSERT INTO crm_timeline (user_id, lead_id, contact_id, company_id, activity_type, description) VALUES (?, ?, ?, ?, 'Task Created', ?)")
                       ->execute([$userId, $leadId, $crmContactId, $companyId, "Meeting task '$taskTitle' was automatically scheduled and invite sent via WhatsApp AI agent."]);
                }
            }
        }

        // Transmit AI reply if generated
        if (!empty($aiSuggestedReply)) {
            // Verify credit balance (and self-heal monthly free credits if needed)
            checkAndResetMonthlyCredits($userId);

            $stmtWallet = $db->prepare("SELECT remaining_credits, free_credits, purchased_credits FROM user_email_credits WHERE user_id = ?");
            $stmtWallet->execute([$userId]);
            $wallet = $stmtWallet->fetch();
            $creditsAvailable = $wallet ? (int)$wallet['remaining_credits'] : 0;

            WhatsAppMetaService::logDebug("processInboundAiReply: Credits available for userId={$userId}: {$creditsAvailable}");

            if ($creditsAvailable >= 1) {
                $replyMsgId = '';
                if (!$isMock) {
                    WhatsAppMetaService::logDebug("Sending outbound text message to {$fromWaId} via WhatsAppMetaService::sendTextMessage...");
                    try {
                        $sendRes = WhatsAppMetaService::sendTextMessage($userId, $phoneNumberId, $fromWaId, $aiSuggestedReply, $accessToken);
                        WhatsAppMetaService::logDebug("sendTextMessage response: " . json_encode($sendRes));
                        $replyMsgId = $sendRes['messages'][0]['id'] ?? 'wamid.auto.' . uniqid();
                    } catch (Throwable $sendEx) {
                        WhatsAppMetaService::logDebug("sendTextMessage failed with error: " . $sendEx->getMessage());
                        throw $sendEx;
                    }
                } else {
                    $replyMsgId = 'wamid.MockAuto.' . uniqid();
                    WhatsAppMetaService::logDebug("Mock account - generated mock replyMsgId: {$replyMsgId}");
                }

                $stmtInsAutoMsg = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, message_id, direction, type, body, status) VALUES (?, ?, ?, 'outbound', 'text', ?, 'sent')");
                $stmtInsAutoMsg->execute([$userId, $waContactId, $replyMsgId, $aiSuggestedReply]);

                $freeCredits = $wallet ? (int)$wallet['free_credits'] : 0;
                $purchasedCredits = $wallet ? (int)$wallet['purchased_credits'] : 0;
                if ($freeCredits >= 1) {
                    $freeCredits--;
                } elseif ($purchasedCredits >= 1) {
                    $purchasedCredits--;
                }
                $newRemaining = $freeCredits + $purchasedCredits;

                $stmtDeduct = $db->prepare("UPDATE user_email_credits SET free_credits = ?, purchased_credits = ?, remaining_credits = ?, used_credits = used_credits + 1 WHERE user_id = ?");
                $stmtDeduct->execute([$freeCredits, $purchasedCredits, $newRemaining, $userId]);

                $stmtTx = $db->prepare("INSERT INTO email_credit_transactions (user_id, type, credits, provider_used, status) VALUES (?, 'usage', 1, 'whatsapp_ai', 'success')");
                $stmtTx->execute([$userId]);

                if (!empty($secondWaMessageText)) {
                    usleep(500000);
                    $secondReplyMsgId = '';
                    if (!$isMock) {
                        $sendRes2 = WhatsAppMetaService::sendTextMessage($userId, $phoneNumberId, $fromWaId, $secondWaMessageText, $accessToken);
                        $secondReplyMsgId = $sendRes2['messages'][0]['id'] ?? 'wamid.auto.sec.' . uniqid();
                    } else {
                        $secondReplyMsgId = 'wamid.MockAuto.sec.' . uniqid();
                    }
                    $stmtInsAutoMsg2 = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, message_id, direction, type, body, status) VALUES (?, ?, ?, 'outbound', 'text', ?, 'sent')");
                    $stmtInsAutoMsg2->execute([$userId, $waContactId, $secondReplyMsgId, $secondWaMessageText]);
                }

                $db->prepare("UPDATE whatsapp_contacts SET last_message_at = NOW() WHERE id = ?")->execute([$waContactId]);
                $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, activity_type, description) VALUES (?, ?, 'WhatsApp Outbound', ?)")
                   ->execute([$userId, $crmContactId, "AI Auto-Pilot replied to '$profileName': " . substr($aiSuggestedReply, 0, 100)]);
            }
        }

        $db->prepare("UPDATE whatsapp_queue SET status = 'processed', error_message = NULL WHERE id = ?")->execute([$queueId]);
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
