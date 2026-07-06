<?php
// backend/api/whatsapp/webhook.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

// Disable error display to avoid output pollution on Webhook responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

$db = Database::getConnection();

// --- 1. HANDLE WEBHOOK VERIFICATION (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $verifyToken = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    // Fetch Verify Token from admin_settings
    $stmtToken = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_webhook_verify_token'");
    $stmtToken->execute();
    $dbToken = $stmtToken->fetchColumn();
    $expectedToken = $dbToken ?: 'LINKPILOT_VERIFY_2026';
    
    if ($mode === 'subscribe' && $verifyToken === $expectedToken) {
        http_response_code(200);
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        echo "Verification failed. Invalid verify token.";
        exit;
    }
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
            $stmtAcc = $db->prepare("SELECT user_id FROM whatsapp_accounts WHERE phone_number_id = ? AND status = 'connected' LIMIT 1");
            $stmtAcc->execute([$phoneNumberId]);
            $accRow = $stmtAcc->fetch();
            if (!$accRow) {
                continue; // WhatsApp Account not linked to any user inside LinkPilot
            }
            $userId = (int)$accRow['user_id'];
            
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
                        $systemPrompt = "You are an AI WhatsApp Assistant. Extract CRM attributes, classify conversation sentiment, formulate a summary, and draft a response.
                        
                        You MUST reply in a valid, parsable JSON structure only, with the following properties:
                        {
                          \"summary\": \"Brief summary of this message\",
                          \"suggested_reply\": \"A helpful, professional response to write back\",
                          \"sentiment\": \"positive|neutral|negative\",
                          \"extracted_lead\": {
                            \"person_name\": \"...\",
                            \"company_name\": \"...\",
                            \"budget\": 0.00,
                            \"services\": \"...\",
                            \"timeline\": \"...\",
                            \"priority\": \"high|medium|low\"
                          }
                        }";
                        
                        $userPrompt = "Sender Name: $profileName\nMessage: $bodyText";
                        
                        try {
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
