<?php
// backend/api/whatsapp/inbox.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'GET') {
        if ($action === 'messages') {
            // Load messages for a single thread
            $waContactId = (int)($_GET['wa_contact_id'] ?? 0);
            if ($waContactId <= 0) {
                sendJsonResponse('error', 'wa_contact_id is required.', [], 400);
            }
            
            // 1. Check thread ownership
            $stmtCon = $db->prepare("SELECT * FROM whatsapp_contacts WHERE id = ? AND user_id = ?");
            $stmtCon->execute([$waContactId, $userId]);
            $thread = $stmtCon->fetch();
            if (!$thread) {
                sendJsonResponse('error', 'Conversation thread not found.', [], 404);
            }
            
            // 2. Load message history
            $stmtMsgs = $db->prepare("SELECT * FROM whatsapp_messages WHERE wa_contact_id = ? ORDER BY created_at ASC LIMIT 100");
            $stmtMsgs->execute([$waContactId]);
            $messages = $stmtMsgs->fetchAll();
            
            // 3. Clear unread badge
            $db->prepare("UPDATE whatsapp_contacts SET unread_count = 0 WHERE id = ?")->execute([$waContactId]);
            
            // 4. Gather CRM profile details
            $crmContact = null;
            $crmCompany = null;
            $crmLead = null;
            $crmTimeline = [];
            $crmNotes = [];
            $crmTasks = [];
            $crmDeals = [];
            
            $crmContactId = $thread['contact_id'];
            if ($crmContactId) {
                $stmtCrmCon = $db->prepare("SELECT * FROM crm_contacts WHERE id = ? AND user_id = ?");
                $stmtCrmCon->execute([$crmContactId, $userId]);
                $crmContact = $stmtCrmCon->fetch();
                
                if ($crmContact) {
                    $companyId = $crmContact['company_id'];
                    
                    // Company Details
                    if ($companyId) {
                        $stmtCrmComp = $db->prepare("SELECT * FROM crm_companies WHERE id = ? AND user_id = ?");
                        $stmtCrmComp->execute([$companyId, $userId]);
                        $crmCompany = $stmtCrmComp->fetch();
                    }
                    
                    // Lead Details
                    $stmtCrmLead = $db->prepare("SELECT * FROM crm_leads WHERE contact_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
                    $stmtCrmLead->execute([$crmContactId, $userId]);
                    $crmLead = $stmtCrmLead->fetch();
                    
                    // Timeline
                    $stmtTimeline = $db->prepare("
                        SELECT * FROM crm_timeline 
                        WHERE user_id = ? AND (contact_id = ? OR company_id = ? OR lead_id = ?) 
                        ORDER BY created_at DESC LIMIT 15
                    ");
                    $stmtTimeline->execute([$userId, $crmContactId, $companyId, $crmLead ? $crmLead['id'] : null]);
                    $crmTimeline = $stmtTimeline->fetchAll();
                    
                    // Notes
                    $stmtNotes = $db->prepare("SELECT * FROM crm_notes WHERE user_id = ? AND (contact_id = ? OR company_id = ?) ORDER BY created_at DESC");
                    $stmtNotes->execute([$userId, $crmContactId, $companyId]);
                    $crmNotes = $stmtNotes->fetchAll();
                    
                    // Tasks
                    $stmtTasks = $db->prepare("SELECT * FROM crm_tasks WHERE user_id = ? AND (contact_id = ? OR company_id = ? OR lead_id = ?) AND status != 'Completed' ORDER BY due_date ASC");
                    $stmtTasks->execute([$userId, $crmContactId, $companyId, $crmLead ? $crmLead['id'] : null]);
                    $crmTasks = $stmtTasks->fetchAll();
                    
                    // Deals
                    $stmtDeals = $db->prepare("SELECT * FROM crm_deals WHERE user_id = ? AND (contact_id = ? OR company_id = ? OR lead_id = ?) ORDER BY closing_date ASC");
                    $stmtDeals->execute([$userId, $crmContactId, $companyId, $crmLead ? $crmLead['id'] : null]);
                    $crmDeals = $stmtDeals->fetchAll();
                }
            }
            
            // 5. Gather last AI summary/suggested reply if exists
            $stmtAI = $db->prepare("
                SELECT ai_summary, ai_suggested_reply, sentiment 
                FROM whatsapp_messages 
                WHERE wa_contact_id = ? AND direction = 'inbound' AND (ai_summary IS NOT NULL OR ai_suggested_reply IS NOT NULL)
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmtAI->execute([$waContactId]);
            $aiData = $stmtAI->fetch() ?: null;
            
            sendJsonResponse('success', 'Conversation details loaded.', [
                'thread' => $thread,
                'messages' => $messages,
                'ai' => $aiData,
                'crm' => [
                    'contact' => $crmContact,
                    'company' => $crmCompany,
                    'lead' => $crmLead,
                    'timeline' => $crmTimeline,
                    'notes' => $crmNotes,
                    'tasks' => $crmTasks,
                    'deals' => $crmDeals
                ]
            ]);
            
        } else {
            // Load conversations thread list (Left panel)
            $search = trim($_GET['search'] ?? '');
            $tag = trim($_GET['tag'] ?? '');
            
            $sql = "SELECT * FROM whatsapp_contacts WHERE user_id = :user_id";
            $params = ['user_id' => $userId];
            
            if ($search !== '') {
                $sql .= " AND (profile_name LIKE :search OR wa_id LIKE :search)";
                $params['search'] = "%{$search}%";
            }
            if ($tag !== '') {
                $sql .= " AND tags LIKE :tag";
                $params['tag'] = "%{$tag}%";
            }
            
            $sql .= " ORDER BY last_message_at DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $threads = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Conversation thread list loaded.', [
                'threads' => $threads
            ]);
        }
    }
    
    elseif ($method === 'POST') {
        // Send a message (Live Send immediately)
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $waContactId = (int)($input['wa_contact_id'] ?? 0);
        $bodyText = trim($input['body'] ?? '');
        $type = $input['type'] ?? 'text'; // 'text', 'media' (image/video/doc/audio)
        
        // Target number if starting a new thread
        $recipient = trim($input['recipient'] ?? '');
        
        if ($waContactId <= 0 && empty($recipient)) {
            sendJsonResponse('error', 'Either wa_contact_id or recipient phone number is required.', [], 400);
        }
        
        if (empty($bodyText) && $type === 'text') {
            sendJsonResponse('error', 'Message body text cannot be empty.', [], 400);
        }
        
        // 1. Fetch connected WhatsApp Account
        $stmtAcc = $db->prepare("SELECT access_token, phone_number_id FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
        $stmtAcc->execute([$userId]);
        $acc = $stmtAcc->fetch();
        if (!$acc) {
            sendJsonResponse('error', 'No connected WhatsApp Business Account found. Connect account first.', [], 400);
        }
        $phoneNumberId = $acc['phone_number_id'];
        $encryptedToken = $acc['access_token'];
        $decrypted = decryptData($encryptedToken);
        $accessToken = ($decrypted !== false) ? $decrypted : $encryptedToken;
        
        // 2. Resolve target number and waContactId
        if ($waContactId > 0) {
            $stmtT = $db->prepare("SELECT wa_id FROM whatsapp_contacts WHERE id = ? AND user_id = ?");
            $stmtT->execute([$waContactId, $userId]);
            $recipient = $stmtT->fetchColumn();
            if (!$recipient) {
                sendJsonResponse('error', 'Conversation thread not found.', [], 404);
            }
        }
        
        // Clean phone number (strip whitespace, symbols, ensure it has country code)
        $recipient = preg_replace('/[^0-9]/', '', $recipient);
        
        // 3. Dispatch to Meta Cloud API immediately
        $response = null;
        if ($type === 'text') {
            $response = WhatsAppMetaService::sendTextMessage($userId, $phoneNumberId, $recipient, $bodyText, $accessToken);
        } elseif (in_array($type, ['image', 'video', 'document', 'audio'])) {
            $mediaId = $input['media_id'] ?? '';
            $filename = $input['filename'] ?? null;
            if (empty($mediaId)) {
                sendJsonResponse('error', 'Media ID parameter is required to send attachment.', [], 400);
            }
            $response = WhatsAppMetaService::sendMediaMessage($userId, $phoneNumberId, $recipient, $type, $mediaId, $filename, $accessToken);
            $bodyText = $filename ?: "Sent " . ucfirst($type);
        } else {
            sendJsonResponse('error', 'Unsupported message type: ' . $type, [], 400);
        }
        
        $metaMsgId = $response['messages'][0]['id'] ?? '';
        
        // 4. Log outbound message to database
        $db->beginTransaction();
        try {
            if ($waContactId <= 0) {
                // Check if contact already exists
                $stmtExist = $db->prepare("SELECT id FROM whatsapp_contacts WHERE user_id = ? AND wa_id = ?");
                $stmtExist->execute([$userId, $recipient]);
                $waContactId = $stmtExist->fetchColumn();
                
                if (!$waContactId) {
                    // Create default basic contact link
                    $stmtCrmCon = $db->prepare("SELECT id FROM crm_contacts WHERE (phone = ? OR whatsapp = ?) AND user_id = ? LIMIT 1");
                    $stmtCrmCon->execute([$recipient, $recipient, $userId]);
                    $crmContactId = $stmtCrmCon->fetchColumn() ?: null;
                    
                    $stmtInsWaCon = $db->prepare("INSERT INTO whatsapp_contacts (user_id, contact_id, wa_id, profile_name, last_message_at, unread_count) VALUES (?, ?, ?, ?, NOW(), 0)");
                    $stmtInsWaCon->execute([$userId, $crmContactId, $recipient, 'WhatsApp Contact']);
                    $waContactId = (int)$db->lastInsertId();
                }
            }
            
            // Save Message
            $stmtInsMsg = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, message_id, direction, type, body, status) VALUES (?, ?, ?, 'outbound', ?, ?, 'sent')");
            $stmtInsMsg->execute([$userId, $waContactId, $metaMsgId, $type, $bodyText]);
            
            // Update last_message_at
            $db->prepare("UPDATE whatsapp_contacts SET last_message_at = NOW() WHERE id = ?")->execute([$waContactId]);
            
            $db->commit();
        } catch (Exception $trxEx) {
            $db->rollBack();
            throw $trxEx;
        }
        
        sendJsonResponse('success', 'Message sent successfully.', [
            'wa_contact_id' => $waContactId,
            'message_id' => $metaMsgId,
            'body' => $bodyText,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    elseif ($method === 'APPLY_AI_REPLY') {
        // Request one-click AI reply suggestion
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $waContactId = (int)($input['wa_contact_id'] ?? 0);
        
        if ($waContactId <= 0) {
            sendJsonResponse('error', 'wa_contact_id is required.', [], 400);
        }
        
        // 1. Fetch thread messages (last 6 to get context)
        $stmtMsgs = $db->prepare("
            SELECT direction, body, type, created_at 
            FROM whatsapp_messages 
            WHERE wa_contact_id = ? 
            ORDER BY created_at DESC 
            LIMIT 6
        ");
        $stmtMsgs->execute([$waContactId]);
        $history = array_reverse($stmtMsgs->fetchAll());
        
        if (empty($history)) {
            sendJsonResponse('error', 'Conversation history is empty.', [], 400);
        }
        
        // 2. Fetch thread profile name
        $stmtName = $db->prepare("SELECT profile_name FROM whatsapp_contacts WHERE id = ?");
        $stmtName->execute([$waContactId]);
        $profileName = $stmtName->fetchColumn();
        
        // 3. Compile prompt
        $systemPrompt = "You are a professional sales assistant for LinkPilot CRM. Your job is to draft a helpful, context-appropriate reply to the last incoming WhatsApp message from the client. Keep the message natural, friendly, and under 3 sentences.";
        
        $chatLog = "";
        foreach ($history as $h) {
            $role = ($h['direction'] === 'inbound') ? $profileName : 'You';
            $chatLog .= "[$role - {$h['created_at']}]: {$h['body']}\n";
        }
        
        $userPrompt = "Chat logs history:\n$chatLog\nDraft response for You:";
        
        try {
            $ai = callAI($systemPrompt, $userPrompt, $userId);
            $aiReplyText = $ai['text'];
            
            // Save the suggestion in the last inbound message log
            $db->prepare("
                UPDATE whatsapp_messages 
                SET ai_suggested_reply = ? 
                WHERE wa_contact_id = ? AND direction = 'inbound' 
                ORDER BY created_at DESC LIMIT 1
            ")->execute([$aiReplyText, $waContactId]);
            
            sendJsonResponse('success', 'AI suggested reply generated successfully.', [
                'suggested_reply' => $aiReplyText
            ]);
        } catch (Exception $aiEx) {
            sendJsonResponse('error', 'AI suggested reply generation failed: ' . $aiEx->getMessage(), [], 500);
        }
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Inbox operation failed: ' . $e->getMessage(), [], 500);
}
