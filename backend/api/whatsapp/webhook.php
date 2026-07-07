<?php
// backend/api/whatsapp/webhook.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

// Disable error display to avoid output pollution on Webhook responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $mode =
        $_GET['hub.mode'] ??
        $_GET['hub_mode'] ??
        '';

    $verifyToken =
        $_GET['hub.verify_token'] ??
        $_GET['hub_verify_token'] ??
        '';

    $challenge =
        $_GET['hub.challenge'] ??
        $_GET['hub_challenge'] ??
        '';

    // Fetch Verify Token from DB
    $stmtToken = $db->prepare("
        SELECT setting_value
        FROM admin_settings
        WHERE setting_key='whatsapp_webhook_verify_token'
        LIMIT 1
    ");
    $stmtToken->execute();
    $dbToken = trim($stmtToken->fetchColumn() ?: '');

    $expectedToken = $dbToken ?: 'LINKPILOT_VERIFY_2026';

    if ($mode === 'subscribe' && trim($verifyToken) === trim($expectedToken)) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo "Verification failed. Invalid verify token.";
    exit;
}

// --- 2. HANDLE INCOMING PAYLOAD (POST) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);

if (!$payload) {
    http_response_code(400);
    echo "Invalid JSON Payload";
    exit;
}

try {
    // A. Log raw payload for auditing and monitoring
    $logStmt = $db->prepare("INSERT INTO whatsapp_webhook_logs (payload, status) VALUES (?, 'unprocessed')");
    $logStmt->execute([$rawPayload]);
    $logId = $db->lastInsertId();
} catch (Exception $e) {
    // Log exception but continue parsing to avoid dropping messages
    $logId = null;
}

try {
    // B. Parse Entry Elements
    $entries = $payload['entry'] ?? [];
    foreach ($entries as $entry) {
        $changes = $entry['changes'] ?? [];
        foreach ($changes as $change) {
            $value = $change['value'] ?? [];
            $metadata = $value['metadata'] ?? [];
            $phoneNumberId = $metadata['phone_number_id'] ?? '';
            
            if (empty($phoneNumberId)) {
                continue; // Skip if no phone ID to route to user account
            }
            
            // 1. Locate the connected account by phone_number_id
            $stmtAcc = $db->prepare("SELECT user_id, access_token, business_name FROM whatsapp_accounts WHERE phone_number_id = ? AND status = 'connected' LIMIT 1");
            $stmtAcc->execute([$phoneNumberId]);
            $accRow = $stmtAcc->fetch();
            if (!$accRow) {
                continue; // WhatsApp Account not linked to any user inside LinkPilot
            }
            $userId = (int)$accRow['user_id'];
            $encryptedToken = $accRow['access_token'];
            $decrypted = decryptData($encryptedToken);
            $accessToken = ($decrypted !== false) ? $decrypted : $encryptedToken;
            $isMock = (strpos($accessToken, 'Mock') !== false || strpos($accessToken, 'EAAGemini') !== false);
            
            // 2. Fetch User Settings
            $stmtSettings = $db->prepare("SELECT * FROM whatsapp_settings WHERE user_id = ? LIMIT 1");
            $stmtSettings->execute([$userId]);
            $settings = $stmtSettings->fetch() ?: [
                'ai_enabled' => 1,
                'auto_crm_creation' => 1,
                'auto_contact_detection' => 1,
                'auto_lead_detection' => 1,
                'auto_company_detection' => 1,
                'auto_reply_suggestions' => 1
            ];
            
            // 3. Process Inbound Messages
            $messages = $value['messages'] ?? [];
            foreach ($messages as $msg) {
                $fromWaId = $msg['from'] ?? ''; // Sender WhatsApp ID (Phone Number)
                $messageId = $msg['id'] ?? '';
                $msgType = $msg['type'] ?? 'text';
                $timestamp = (int)($msg['timestamp'] ?? time());
                
                if (empty($fromWaId) || empty($messageId)) {
                    continue;
                }
                
                // Get Contact Profile Name
                $profileName = $value['contacts'][0]['profile']['name'] ?? 'WhatsApp Contact';
                
                $db->beginTransaction();
                try {
                    // a. Locate or create whatsapp_contacts record
                    $stmtContact = $db->prepare("SELECT id, contact_id FROM whatsapp_contacts WHERE user_id = ? AND wa_id = ?");
                    $stmtContact->execute([$userId, $fromWaId]);
                    $waContact = $stmtContact->fetch();
                    
                    $crmContactId = null;
                    
                    if ($waContact) {
                        $waContactId = (int)$waContact['id'];
                        $crmContactId = $waContact['contact_id'];
                        
                        // Increment unread count and set last message timestamp
                        $db->prepare("UPDATE whatsapp_contacts SET unread_count = unread_count + 1, last_message_at = FROM_UNIXTIME(?) WHERE id = ?")
                           ->execute([$timestamp, $waContactId]);
                    } else {
                        // Create CRM contact if configured
                        if ($settings['auto_contact_detection']) {
                            // Find existing CRM contact by phone
                            $stmtCrmCon = $db->prepare("SELECT id FROM crm_contacts WHERE (phone = ? OR whatsapp = ?) AND user_id = ? LIMIT 1");
                            $stmtCrmCon->execute([$fromWaId, $fromWaId, $userId]);
                            $crmConRow = $stmtCrmCon->fetch();
                            
                            if ($crmConRow) {
                                $crmContactId = (int)$crmConRow['id'];
                            } else {
                                // Auto create basic contact
                                $stmtCrmIns = $db->prepare("INSERT INTO crm_contacts (user_id, name, phone, whatsapp) VALUES (?, ?, ?, ?)");
                                $stmtCrmIns->execute([$userId, $profileName, $fromWaId, $fromWaId]);
                                $crmContactId = (int)$db->lastInsertId();
                                
                                // Log Timeline
                                $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, activity_type, description) VALUES (?, ?, 'Contact Created', ?)")
                                   ->execute([$userId, $crmContactId, "Contact '$profileName' created from incoming WhatsApp chat."]);
                            }
                        }
                        
                        // Create whatsapp_contacts link
                        $stmtInsWaCon = $db->prepare("INSERT INTO whatsapp_contacts (user_id, contact_id, wa_id, profile_name, last_message_at, unread_count) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), 1)");
                        $stmtInsWaCon->execute([$userId, $crmContactId, $fromWaId, $profileName, $timestamp]);
                        $waContactId = (int)$db->lastInsertId();
                    }
                    
                    // b. Extract message details
                    $bodyText = '';
                    $mediaUrl = null;
                    $mediaMimeType = null;
                    $mediaIdToDownload = null;
                    
                    if ($msgType === 'text') {
                        $bodyText = $msg['text']['body'] ?? '';
                    } elseif ($msgType === 'button') {
                        $bodyText = $msg['button']['text'] ?? '';
                    } elseif ($msgType === 'interactive') {
                        $bodyText = $msg['interactive']['button_reply']['title'] ?? ($msg['interactive']['list_reply']['title'] ?? '');
                    } elseif (in_array($msgType, ['image', 'video', 'document', 'audio'])) {
                        $mediaIdToDownload = $msg[$msgType]['id'] ?? null;
                        $mediaMimeType = $msg[$msgType]['mime_type'] ?? '';
                        $bodyText = $msg[$msgType]['caption'] ?? ($msg[$msgType]['filename'] ?? ucfirst($msgType) . " Attachment");
                    } else {
                        $bodyText = "[Unsupported message type: {$msgType}]";
                    }
                    
                    // Download Media from Meta Graph API if present
                    if ($mediaIdToDownload) {
                        try {
                            $downloaded = WhatsAppMetaService::downloadMedia($userId, $mediaIdToDownload);
                            $mediaUrl = $downloaded['local_path'];
                            $mediaMimeType = $downloaded['mime_type'];
                            
                            // Insert WhatsApp Media cache log
                            $stmtMedia = $db->prepare("INSERT IGNORE INTO whatsapp_media (user_id, media_id, mime_type, file_path, file_size) VALUES (?, ?, ?, ?, ?)");
                            $stmtMedia->execute([$userId, $mediaIdToDownload, $mediaMimeType, $mediaUrl, $downloaded['file_size']]);
                        } catch (Exception $mediaEx) {
                            $bodyText .= " (Media download failed: " . $mediaEx->getMessage() . ")";
                        }
                    }
                    
                    // c. Run AI Intelligence (Summarization, Replies, Sentiment, CRM Extraction)
                    $aiSummary = null;
                    $aiSuggestedReply = null;
                    $sentiment = 'neutral';
                    
                    if ($settings['ai_enabled'] && $msgType === 'text' && !empty($bodyText)) {
                        try {
                            // 1. Fetch Today's & Upcoming Meetings
                            $stmtMeetings = $db->prepare("SELECT title, description, start_time, location FROM crm_meetings WHERE user_id = ? AND start_time >= NOW() ORDER BY start_time ASC LIMIT 5");
                            $stmtMeetings->execute([$userId]);
                            $meetings = $stmtMeetings->fetchAll(PDO::FETCH_ASSOC);

                            // 2. Fetch Pending/Active Tasks
                            $stmtTasks = $db->prepare("SELECT title, description, status, due_date, priority FROM crm_tasks WHERE user_id = ? AND status != 'Completed' ORDER BY due_date ASC LIMIT 8");
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
                                $tasksCtx .= "- Task: {$t['title']} | Due: {$t['due_date']} | Status: {$t['status']} | Priority: {$t['priority']}\n";
                            }
                            
                            $meetingsCtx = "";
                            foreach ($meetings as $m) {
                                $meetingsCtx .= "- Meeting: {$m['title']} | Time: {$m['start_time']} | Location: {$m['location']}\n";
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
                            $userProfileName = $stmtUser->fetchColumn() ?: $accRow['business_name'];

                            $currentDate = date('Y-m-d');
                            $currentTime = date('H:i:s');
                            $systemPrompt = "You are a professional human representative/assistant for $userProfileName (representing the business '" . $accRow['business_name'] . "').
Your goal is to reply to incoming customer messages on behalf of $userProfileName / " . $accRow['business_name'] . ".

CRITICAL IDENTITY & CONVERSATIONAL RULES:
1. Never say you are an 'AI', 'AI CRM', 'bot', 'AI assistant', 'CRM assistant', or a chatbot.
2. You are a real professional human representing $userProfileName. If asked who you are, what your name is, or who you represent, reply that you are $userProfileName or representing $userProfileName.
3. Respond like a real professional human. Use natural, warm, confident, conversational language with occasional fillers such as \"Hmm...\", \"Okay\", \"I see\", \"Sure\", or \"Got it\" where they fit naturally.
4. Avoid sounding robotic, overly formal, or repetitive. Responses must feel like they are coming from a knowledgeable person having a genuine conversation, while remaining clear, concise, and respectful.
5. Stay strictly on-topic. Focus entirely on the customer's business relationship, leads, bookings, invoices, and sales inquiries. Do not discuss general knowledge or unrelated subjects.
6. If you need to schedule a meeting, check the meetings/availability list below. Propose slot options that do not conflict with existing meetings.
7. If you do not have enough information to close a deal or answer a technical question, ask polite clarifying questions.
8. You MUST return your response as a valid, parsable JSON block with the following keys, and nothing else (no extra markdown blocks outside JSON):
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
    \"priority\": \"high|medium|low\"
  }
}

NOTE:
- Only return \"extracted_task\" if the customer explicitly mentions or asks for a meeting, follow-up, call, task, or action item. If no task or meeting is requested or mentioned, do NOT include the \"extracted_task\" field in the JSON (set it to null or omit it).

TODAY'S DATE AND TIME: $currentDate $currentTime (relative offsets like 'tomorrow', 'next week', 'Friday at 2pm' must be calculated relative to this timestamp).

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

## CONVERSATION HISTORY (LAST 10 MESSAGES):
" . ($chatHistCtx ?: "No previous chat history.") . "
";

                            $userPrompt = "Sender Name: $profileName\nMessage: $bodyText";

                            $ai = callAI($systemPrompt, $userPrompt, $userId);
                            $aiRes = json_decode($ai['text'], true);
                            if ($aiRes) {
                                $aiSummary = $aiRes['summary'] ?? null;
                                $aiSuggestedReply = $aiRes['suggested_reply'] ?? null;
                                $sentiment = $aiRes['sentiment'] ?? 'neutral';
                                
                                // Auto CRM Lead Creation if name or company extracted
                                $leadInfo = $aiRes['extracted_lead'] ?? [];
                                if ($settings['auto_crm_creation'] && (!empty($leadInfo['person_name']) || !empty($leadInfo['company_name']))) {
                                    $leadName = $leadInfo['person_name'] ?: $profileName;
                                    $leadCompany = $leadInfo['company_name'] ?? '';
                                    $leadBudget = (float)($leadInfo['budget'] ?? 0.00);
                                    $leadServices = $leadInfo['services'] ?? '';
                                    $leadPriority = $leadInfo['priority'] ?? 'medium';
                                    
                                    // Verify if lead exists already
                                    $stmtLeadCheck = $db->prepare("SELECT id FROM crm_leads WHERE (phone = ? OR email = ?) AND user_id = ? LIMIT 1");
                                    $stmtLeadCheck->execute([$fromWaId, '', $userId]);
                                    if (!$stmtLeadCheck->fetchColumn()) {
                                        // Auto-create lead
                                        $stmtLeadIns = $db->prepare("INSERT INTO crm_leads (user_id, contact_id, name, phone, company, budget, services_required, priority, lead_source, stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'WhatsApp', 'New')");
                                        $stmtLeadIns->execute([$userId, $crmContactId, $leadName, $fromWaId, $leadCompany, $leadBudget, $leadServices, $leadPriority]);
                                        $newLeadId = $db->lastInsertId();
                                        
                                        $db->prepare("INSERT INTO crm_timeline (user_id, lead_id, contact_id, activity_type, description) VALUES (?, ?, ?, 'Lead Created', ?)")
                                           ->execute([$userId, $newLeadId, $crmContactId, "Lead '$leadName' automatically created via AI WhatsApp parser."]);
                                    }
                                }
                                
                                // Auto CRM Task Creation if task or meeting extracted
                                $taskInfo = $aiRes['extracted_task'] ?? null;
                                if ($taskInfo && !empty($taskInfo['title'])) {
                                    $taskTitle = trim($taskInfo['title']);
                                    $taskDesc = trim($taskInfo['description'] ?? '');
                                    $taskCategory = trim($taskInfo['category'] ?? 'General');
                                    $taskDueDate = !empty($taskInfo['due_date']) ? trim($taskInfo['due_date']) : date('Y-m-d');
                                    $taskDueTime = !empty($taskInfo['due_time']) ? trim($taskInfo['due_time']) : null;
                                    $taskPriority = trim($taskInfo['priority'] ?? 'medium');
                                    
                                    // Prefix title if categorized and not already prefixed
                                    if ($taskCategory !== 'General' && strpos($taskTitle, "[$taskCategory]") === false) {
                                        $taskTitle = "[$taskCategory] " . $taskTitle;
                                    }
                                    
                                    // Resolve company_id and lead_id
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
                                    
                                    // Insert the task
                                    $stmtTaskIns = $db->prepare("INSERT INTO crm_tasks (user_id, company_id, contact_id, lead_id, title, description, due_date, due_time, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                                    $stmtTaskIns->execute([
                                        $userId, $companyId, $crmContactId, $leadId, $taskTitle, $taskDesc, $taskDueDate, $taskDueTime, $taskPriority
                                    ]);
                                    $newTaskId = $db->lastInsertId();
                                    
                                    // Log to timeline
                                    $db->prepare("INSERT INTO crm_timeline (user_id, lead_id, contact_id, company_id, activity_type, description) VALUES (?, ?, ?, ?, 'Task Created', ?)")
                                       ->execute([$userId, $leadId, $crmContactId, $companyId, "Task '$taskTitle' automatically scheduled via AI WhatsApp agent (Due: $taskDueDate)."]);
                                }
                            }
                        } catch (Exception $aiEx) {
                            // Suppress AI errors to keep webhook delivery successful
                        }
                    }
                    
                    // d. Save WhatsApp Message
                    $stmtInsMsg = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, message_id, direction, type, body, media_url, media_mime_type, status, ai_summary, ai_suggested_reply, sentiment) VALUES (?, ?, ?, 'inbound', ?, ?, ?, ?, 'delivered', ?, ?, ?)");
                    $stmtInsMsg->execute([
                        $userId, $waContactId, $messageId, $msgType, $bodyText, $mediaUrl, $mediaMimeType, $aiSummary, $aiSuggestedReply, $sentiment
                    ]);
                    
                    // If AI Auto-Pilot is enabled, automatically transmit response to WhatsApp
                    if ($settings['ai_enabled'] && !empty($aiSuggestedReply)) {
                        $replyMsgId = '';
                        try {
                            if (!$isMock) {
                                $sendRes = WhatsAppMetaService::sendTextMessage($userId, $phoneNumberId, $fromWaId, $aiSuggestedReply, $accessToken);
                                $replyMsgId = $sendRes['messages'][0]['id'] ?? 'wamid.auto.' . uniqid();
                            } else {
                                $replyMsgId = 'wamid.MockAuto.' . uniqid();
                            }
                            
                            // Save outbound auto reply to database
                            $stmtInsAutoMsg = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, message_id, direction, type, body, status) VALUES (?, ?, ?, 'outbound', 'text', ?, 'sent')");
                            $stmtInsAutoMsg->execute([$userId, $waContactId, $replyMsgId, $aiSuggestedReply]);
                            
                            // Update last active activity
                            $db->prepare("UPDATE whatsapp_contacts SET last_message_at = NOW() WHERE id = ?")->execute([$waContactId]);
                            
                            // Log timeline
                            $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, activity_type, description) VALUES (?, ?, 'WhatsApp Outbound', ?)")
                               ->execute([$userId, $crmContactId, "AI Auto-Pilot replied to '$profileName': " . substr($aiSuggestedReply, 0, 100)]);
                        } catch (Exception $sendEx) {
                            // Save error log to whatsapp messages if failed
                            $stmtInsAutoErr = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, message_id, direction, type, body, status, error_message) VALUES (?, ?, ?, 'outbound', 'text', ?, 'failed', ?)");
                            $stmtInsAutoErr->execute([$userId, $waContactId, 'wamid.err.' . uniqid(), $aiSuggestedReply, $sendEx->getMessage()]);
                        }
                    }
                    
                    // Update user statistics
                    updateStatistic($userId, 'whatsapp_generated');
                    
                    // Log to CRM timeline
                    $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, activity_type, description) VALUES (?, ?, 'WhatsApp Inbound', ?)")
                       ->execute([$userId, $crmContactId, "Received WhatsApp message from '$profileName': " . substr($bodyText, 0, 100)]);
                    
                    $db->commit();
                } catch (Exception $trxEx) {
                    $db->rollBack();
                    throw $trxEx;
                }
            }
            
            // 4. Process Message Status Updates (Statuses)
            $statuses = $value['statuses'] ?? [];
            foreach ($statuses as $statusObj) {
                $statusMsgId = $statusObj['id'] ?? '';
                $statusType = $statusObj['status'] ?? ''; // 'sent', 'delivered', 'read', 'failed'
                $errorObj = $statusObj['errors'][0] ?? null;
                $errMsg = $errorObj ? ($errorObj['message'] ?? 'Meta API error code: ' . $errorObj['code']) : null;
                
                if (empty($statusMsgId) || empty($statusType)) {
                    continue;
                }
                
                $db->beginTransaction();
                try {
                    // Update main whatsapp_messages entry status
                    $stmtUpMsg = $db->prepare("UPDATE whatsapp_messages SET status = ?, error_message = ? WHERE message_id = ?");
                    $stmtUpMsg->execute([$statusType, $errMsg, $statusMsgId]);
                    
                    // Update campaigns log metrics if matched
                    $stmtUpCampLog = $db->prepare("UPDATE whatsapp_campaign_logs SET status = ?, error_message = ? WHERE message_id = ?");
                    $stmtUpCampLog->execute([$statusType, $errMsg, $statusMsgId]);
                    
                    // If matched campaign update success rates count
                    $stmtFindCamp = $db->prepare("
                        SELECT l.campaign_id, c.user_id 
                        FROM whatsapp_campaign_logs l 
                        JOIN whatsapp_campaigns c ON l.campaign_id = c.id 
                        WHERE l.message_id = ? 
                        LIMIT 1
                    ");
                    $stmtFindCamp->execute([$statusMsgId]);
                    $campRow = $stmtFindCamp->fetch();
                    
                    if ($campRow) {
                        $campId = (int)$campRow['campaign_id'];
                        $campUserId = (int)$campRow['user_id'];
                        
                        // Recalculate campaign statistics count
                        $stmtCount = $db->prepare("
                            SELECT 
                                SUM(CASE WHEN status='sent' OR status='delivered' OR status='read' THEN 1 ELSE 0 END) as sent_total,
                                SUM(CASE WHEN status='delivered' OR status='read' THEN 1 ELSE 0 END) as del_total,
                                SUM(CASE WHEN status='read' THEN 1 ELSE 0 END) as read_total,
                                SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as fail_total
                            FROM whatsapp_campaign_logs 
                            WHERE campaign_id = ?
                        ");
                        $stmtCount->execute([$campId]);
                        $counts = $stmtCount->fetch();
                        
                        $stmtUpdateCamp = $db->prepare("
                            UPDATE whatsapp_campaigns 
                            SET sent_count = ?, delivered_count = ?, read_count = ?, failed_count = ? 
                            WHERE id = ?
                        ");
                        $stmtUpdateCamp->execute([
                            (int)$counts['sent_total'],
                            (int)$counts['del_total'],
                            (int)$counts['read_total'],
                            (int)$counts['fail_total'],
                            $campId
                        ]);
                    }
                    
                    $db->commit();
                } catch (Exception $stEx) {
                    $db->rollBack();
                    throw $stEx;
                }
            }
        }
    }
    
    // Update Webhook logs status to processed
    if ($logId) {
        $db->prepare("UPDATE whatsapp_webhook_logs SET status = 'processed' WHERE id = ?")->execute([$logId]);
    }
    
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Webhook processed successfully."]);
    
} catch (Exception $e) {
    if ($logId) {
        $db->prepare("UPDATE whatsapp_webhook_logs SET status = 'failed', error_message = ? WHERE id = ?")->execute([$e->getMessage(), $logId]);
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Webhook Ingestion Failure: " . $e->getMessage()]);
}
