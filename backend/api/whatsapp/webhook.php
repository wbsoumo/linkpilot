<?php
// backend/api/whatsapp/webhook.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../wallet_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';
require_once __DIR__ . '/../../external_apps_helper.php';

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
            // 1. Locate the connected account by phone_number_id (or fallback to latest connected account)
            $metadata = $value['metadata'] ?? [];
            $phoneNumberId = $metadata['phone_number_id'] ?? '';
            $accRow = null;
            if (!empty($phoneNumberId)) {
                $stmtAcc = $db->prepare("SELECT user_id, access_token, business_name FROM whatsapp_accounts WHERE phone_number_id = ? AND status = 'connected' LIMIT 1");
                $stmtAcc->execute([$phoneNumberId]);
                $accRow = $stmtAcc->fetch();
            }
            if (!$accRow) {
                // Fallback to any connected account if phone_number_id differs slightly or is absent in Meta payload
                $stmtAccFallback = $db->query("SELECT user_id, access_token, business_name FROM whatsapp_accounts WHERE status = 'connected' ORDER BY updated_at DESC LIMIT 1");
                $accRow = $stmtAccFallback->fetch();
            }
            if (!$accRow) {
                WhatsAppMetaService::logDebug("WhatsApp Webhook skipped: No connected account found for phone_number_id '$phoneNumberId'");
                continue; // WhatsApp Account not linked to any user inside LinkPilot
            }
            $userId = (int)$accRow['user_id'];
            $encryptedToken = $accRow['access_token'];
            $decrypted = decryptData($encryptedToken);
            $accessToken = ($decrypted !== false) ? $decrypted : $encryptedToken;
            $isMock = (strpos($accessToken, 'Mock') !== false || $accessToken === 'EAAGemini' || $accessToken === 'EAAGeminiTest');
            
            // Check and Reset Monthly Credits lazily
            checkAndResetMonthlyCredits($userId);
            
            // 2. Fetch User Settings
            $stmtSettings = $db->prepare("SELECT * FROM whatsapp_settings WHERE user_id = ? LIMIT 1");
            $stmtSettings->execute([$userId]);
            $settings = $stmtSettings->fetch() ?: [
                'ai_enabled' => 1,
                'auto_crm_creation' => 1,
                'auto_contact_detection' => 1,
                'auto_lead_detection' => 1,
                'auto_company_detection' => 1,
                'auto_reply_suggestions' => 1,
                'auto_summarize_history' => 1
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
                    $stmtContact = $db->prepare("SELECT id, contact_id, chat_summary, last_message_at FROM whatsapp_contacts WHERE user_id = ? AND RIGHT(wa_id, 10) = RIGHT(?, 10) ORDER BY last_message_at DESC LIMIT 1");
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
                    } elseif (in_array($msgType, ['image', 'video', 'document', 'audio', 'voice', 'sticker'])) {
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
                    
                    // c. Save Inbound WhatsApp Message immediately
                    $stmtInsMsg = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, message_id, direction, type, body, media_url, media_mime_type, status) VALUES (?, ?, ?, 'inbound', ?, ?, ?, ?, 'delivered')");
                    $stmtInsMsg->execute([
                        $userId, $waContactId, $messageId, $msgType, $bodyText, $mediaUrl, $mediaMimeType
                    ]);
                    $inboundMsgDbId = (int)$db->lastInsertId();
                    
                    // Update user statistics
                    updateStatistic($userId, 'whatsapp_generated');
                    
                    // Log to CRM timeline
                    $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, activity_type, description) VALUES (?, ?, 'WhatsApp Inbound', ?)")
                       ->execute([$userId, $crmContactId, "Received WhatsApp message from '$profileName': " . substr($bodyText, 0, 100)]);

                    // d. If AI is enabled and message has text, enqueue for async processing via whatsapp_queue
                    if ($settings['ai_enabled'] && $msgType === 'text' && !empty($bodyText)) {
                        $queuePayload = json_encode([
                            'user_id' => $userId,
                            'wa_contact_id' => $waContactId,
                            'crm_contact_id' => $crmContactId,
                            'message_db_id' => $inboundMsgDbId,
                            'message_id' => $messageId,
                            'from_wa_id' => $fromWaId,
                            'phone_number_id' => $phoneNumberId,
                            'profile_name' => $profileName,
                            'body_text' => $bodyText,
                            'msg_type' => $msgType,
                            'access_token' => $encryptedToken,
                            'is_mock' => $isMock,
                            'business_name' => $accRow['business_name'] ?? 'Business'
                        ]);

                        $stmtQueueIn = $db->prepare("INSERT INTO whatsapp_queue (user_id, phone_number_id, recipient_number, payload_json, type, status) VALUES (?, ?, ?, ?, 'inbound_ai_reply', 'pending')");
                        $stmtQueueIn->execute([$userId, $phoneNumberId, $fromWaId, $queuePayload]);
                    }
                    
                    // Always commit transaction first to ensure message is persisted to DB
                    $db->commit();

                    // Immediately process WhatsApp AI queue synchronously in isolated scope
                    if ($settings['ai_enabled'] && $msgType === 'text' && !empty($bodyText)) {
                        try {
                            require_once __DIR__ . '/../../queue_worker.php';
                            QueueWorker::processWhatsAppQueue();
                        } catch (Throwable $qEx) {
                            WhatsAppMetaService::logDebug("Synchronous queue worker error: " . $qEx->getMessage());
                        }
                    }

                    // Trigger active visual workflows for whatsapp_received
                    try {
                        require_once __DIR__ . '/../../workflow_runner.php';
                        $stmtWfs = $db->prepare("SELECT * FROM automation_workflows WHERE user_id = ? AND trigger_type = 'whatsapp_received' AND is_active = 1");
                        $stmtWfs->execute([$userId]);
                        $wfs = $stmtWfs->fetchAll();
                        
                        $wfContext = [
                            'sender_phone' => $fromWaId,
                            'sender_name' => $profileName,
                            'message_body' => $bodyText,
                            'message' => $bodyText,
                            'contact_id' => $crmContactId
                        ];
                        
                        foreach ($wfs as $wf) {
                            $trVal = trim($wf['trigger_value'] ?? '');
                            if (!empty($trVal) && strtolower($trVal) !== 'all') {
                                // Clean both numbers for comparison
                                $cleanTrVal = preg_replace('/[^0-9]/', '', $trVal);
                                $cleanFrom = preg_replace('/[^0-9]/', '', $fromWaId);
                                if (!empty($cleanTrVal) && $cleanTrVal !== $cleanFrom) {
                                    continue; // Filter on specific sender phone number
                                }
                            }
                            WorkflowRunner::execute($userId, $wf, $wfContext);
                        }
                    } catch (Throwable $wfEx) {
                        WhatsAppMetaService::logDebug("WhatsApp visual workflow execution error: " . $wfEx->getMessage());
                    }
                } catch (Throwable $trxEx) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    WhatsAppMetaService::logDebug("WhatsApp message processing error: " . $trxEx->getMessage());
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
