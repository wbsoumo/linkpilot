<?php
// backend/api/whatsapp/inbox.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

// Log raw request
@file_put_contents(__DIR__ . '/request_log.txt', "[" . date('Y-m-d H:i:s') . "] REQUEST: METHOD=" . $_SERVER['REQUEST_METHOD'] . " URI=" . $_SERVER['REQUEST_URI'] . " BODY=" . file_get_contents('php://input') . "\n", FILE_APPEND);
@chmod(__DIR__ . '/request_log.txt', 0777);

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
            
            // 2. Load message history (latest 100 messages chronologically)
            $stmtMsgs = $db->prepare("SELECT * FROM whatsapp_messages WHERE wa_contact_id = ? ORDER BY created_at DESC LIMIT 100");
            $stmtMsgs->execute([$waContactId]);
            $messages = array_reverse($stmtMsgs->fetchAll());
            
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
            
            $sql = "SELECT c.*, 
                           (SELECT body FROM whatsapp_messages WHERE wa_contact_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_body,
                           (SELECT type FROM whatsapp_messages WHERE wa_contact_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_type
                    FROM whatsapp_contacts c 
                    WHERE c.user_id = :user_id";
            $params = ['user_id' => $userId];
            
            if ($search !== '') {
                $sql .= " AND (c.profile_name LIKE :search OR c.wa_id LIKE :search)";
                $params['search'] = "%{$search}%";
            }
            if ($tag !== '') {
                $sql .= " AND c.tags LIKE :tag";
                $params['tag'] = "%{$tag}%";
            }
            
            $sql .= " ORDER BY c.last_message_at DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $threads = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Conversation thread list loaded.', [
                'threads' => $threads
            ]);
        }
    }
    
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        if ($action === 'resolve_contact') {
            $phone = trim($input['phone'] ?? '');
            if (empty($phone)) {
                sendJsonResponse('error', 'Phone number is required.', [], 400);
            }
            
            // Clean phone number
            $phoneClean = preg_replace('/[^0-9]/', '', $phone);
            if (empty($phoneClean)) {
                sendJsonResponse('error', 'Invalid phone number format.', [], 400);
            }
            
            // 1. Check if thread already exists in whatsapp_contacts
            $stmt = $db->prepare("SELECT id FROM whatsapp_contacts WHERE user_id = ? AND wa_id = ?");
            $stmt->execute([$userId, $phoneClean]);
            $existingId = $stmt->fetchColumn();
            
            if ($existingId) {
                sendJsonResponse('success', 'Contact thread resolved.', ['wa_contact_id' => (int)$existingId]);
            }
            
            // 2. Look up CRM contact or lead info
            $crmContactId = null;
            $profileName = 'WhatsApp Contact';
            
            // Search in crm_contacts
            $stmtContact = $db->prepare("SELECT id, name FROM crm_contacts WHERE (phone = ? OR whatsapp = ?) AND user_id = ? LIMIT 1");
            $stmtContact->execute([$phoneClean, $phoneClean, $userId]);
            $contactRow = $stmtContact->fetch();
            
            if ($contactRow) {
                $crmContactId = $contactRow['id'];
                $profileName = $contactRow['name'];
            } else {
                // Search in crm_leads
                $stmtLead = $db->prepare("SELECT contact_id, name, phone FROM crm_leads WHERE (phone = ? OR email = ?) AND user_id = ? LIMIT 1");
                $stmtLead->execute([$phoneClean, $phoneClean, $userId]);
                $leadRow = $stmtLead->fetch();
                if ($leadRow) {
                    $crmContactId = $leadRow['contact_id'] ?: null;
                    $profileName = $leadRow['name'];
                }
            }
            
            // 3. Create new whatsapp_contacts row
            $stmtIns = $db->prepare("INSERT INTO whatsapp_contacts (user_id, contact_id, wa_id, profile_name, last_message_at, unread_count) VALUES (?, ?, ?, ?, NOW(), 0)");
            $stmtIns->execute([$userId, $crmContactId, $phoneClean, $profileName]);
            $newId = $db->lastInsertId();
            
            sendJsonResponse('success', 'Contact thread created.', ['wa_contact_id' => (int)$newId]);
        }
        
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
        
        // Manual messaging is free for all users now, no credit check required
        $isUser = ($user['role'] !== 'admin');
        
        // 3. Dispatch to Meta Cloud API immediately
        $response = null;
        $metaMsgId = '';
        
        $isMock = (strpos($accessToken, 'Mock') !== false || $accessToken === 'EAAGemini' || $accessToken === 'EAAGeminiTest');
        if (!$isMock) {
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
            } elseif ($type === 'template') {
                $templateName = trim($input['template_name'] ?? '');
                $lang = trim($input['template_lang'] ?? 'en_US');
                if (empty($templateName)) {
                    sendJsonResponse('error', 'Template name is required.', [], 400);
                }
                
                $components = [];
                if (!empty($input['components'])) {
                    $components = $input['components'];
                } elseif (!empty($input['variables'])) {
                    $params = [];
                    foreach ($input['variables'] as $v) {
                        $params[] = [
                            "type" => "text",
                            "text" => (string)$v
                        ];
                    }
                    if (!empty($params)) {
                        $components[] = [
                            "type" => "body",
                            "parameters" => $params
                        ];
                    }
                    
                    // Retrieve template details from local database to parse parameters
                    $stmtTpl = $db->prepare("SELECT category, components_json FROM whatsapp_templates WHERE name = ? AND user_id = ? LIMIT 1");
                    $stmtTpl->execute([$templateName, $userId]);
                    $tplData = $stmtTpl->fetch(PDO::FETCH_ASSOC);
                    
                    if ($tplData) {
                        $componentsList = json_decode($tplData['components_json'] ?? '[]', true) ?: [];
                        
                        // Parse buttons to dynamically inject URL button variables if they exist
                        $buttonIndex = 0;
                        foreach ($componentsList as $comp) {
                            if (strtoupper($comp['type'] ?? '') === 'BUTTONS') {
                                $buttons = $comp['buttons'] ?? [];
                                foreach ($buttons as $btn) {
                                    $btnType = strtoupper($btn['type'] ?? '');
                                    
                                    // If URL button has dynamic parameter
                                    if ($btnType === 'URL' && strpos($btn['url'] ?? '', '{{1}}') !== false) {
                                        $components[] = [
                                            "type" => "button",
                                            "sub_type" => "url",
                                            "index" => $buttonIndex,
                                            "parameters" => [
                                                [
                                                    "type" => "text",
                                                    "text" => (string)$input['variables'][0]
                                                ]
                                            ]
                                        ];
                                    }
                                    
                                    $buttonIndex++;
                                }
                            }
                        }
                    }
                }
                
                $response = WhatsAppMetaService::sendTemplateMessage($userId, $phoneNumberId, $recipient, $templateName, $lang, $components, $accessToken);
                $bodyText = "[Template: {$templateName}]";
            } else {
                sendJsonResponse('error', 'Unsupported message type: ' . $type, [], 400);
            }
            $metaMsgId = $response['messages'][0]['id'] ?? '';
        } else {
            $metaMsgId = 'wamid.HBgLOTE5OTk5OTk5OTk5FQIAERg5M0RCMDZFQzg2Q0I4OEFEOAA=' . uniqid();
            if ($type === 'template') {
                $bodyText = "[Template: " . ($input['template_name'] ?? 'hello_world') . "]";
            } elseif ($type !== 'text') {
                $bodyText = $input['filename'] ?? "Sent " . ucfirst($type);
            }
        }
        
        // 4. Log outbound message to database
        $db->beginTransaction();
        try {
            // Manual messaging is free, no credit deduction needed
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
            // Write input debug log
            file_put_contents(__DIR__ . '/../../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] INPUTS: systemPrompt=[$systemPrompt], userPrompt=[$userPrompt], userId=[$userId]\n", FILE_APPEND);

            $ai = callAI($systemPrompt, $userPrompt, $userId);
            $aiReplyText = $ai['text'] ?? '';
            
            // Write output debug log
            file_put_contents(__DIR__ . '/../../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] OUTPUTS: text=[$aiReplyText], raw=" . print_r($ai, true) . "\n", FILE_APPEND);

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
            file_put_contents(__DIR__ . '/../../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $aiEx->getMessage() . "\n", FILE_APPEND);
            sendJsonResponse('error', 'AI suggested reply generation failed: ' . $aiEx->getMessage(), [], 500);
        }
    }
    
    elseif ($method === 'CREATE_AND_LINK_CONTACT') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $waContactId = (int)($input['wa_contact_id'] ?? 0);
        
        if ($waContactId <= 0) {
            sendJsonResponse('error', 'wa_contact_id is required.', [], 400);
        }
        
        // 1. Fetch whatsapp contact info
        $stmtCon = $db->prepare("SELECT * FROM whatsapp_contacts WHERE id = ? AND user_id = ?");
        $stmtCon->execute([$waContactId, $userId]);
        $waContact = $stmtCon->fetch();
        if (!$waContact) {
            sendJsonResponse('error', 'WhatsApp contact thread not found.', [], 404);
        }
        
        // 2. Clean/Format details
        $profileName = $waContact['profile_name'] ?: 'WhatsApp Contact';
        $phone = $waContact['wa_id'];
        
        // Check if a CRM contact with this phone already exists to avoid duplicate
        $stmtExist = $db->prepare("SELECT id FROM crm_contacts WHERE (phone = ? OR whatsapp = ?) AND user_id = ? LIMIT 1");
        $stmtExist->execute([$phone, $phone, $userId]);
        $crmContactId = $stmtExist->fetchColumn();
        
        if (!$crmContactId) {
            // Verify contact limit (max 100 contacts for non-admins)
            if (!checkContactLimit($userId)) {
                sendJsonResponse('error', 'Contact limit reached. Your free tier allows up to 100 contacts. Please upgrade your plan to link more.', [], 403);
            }
            // Create CRM Contact
            $insStmt = $db->prepare("INSERT INTO crm_contacts (user_id, name, phone, whatsapp) VALUES (?, ?, ?, ?)");
            $insStmt->execute([$userId, $profileName, $phone, $phone]);
            $crmContactId = $db->lastInsertId();
            
            // Log to timeline
            $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, activity_type, description) VALUES (?, ?, 'Contact Created', ?)");
            $timelineStmt->execute([$userId, $crmContactId, "Contact '$profileName' was auto-created from WhatsApp chat."]);
        }
        
        // 3. Link contact to whatsapp thread
        $db->prepare("UPDATE whatsapp_contacts SET contact_id = ? WHERE id = ? AND user_id = ?")->execute([$crmContactId, $waContactId, $userId]);
        
        sendJsonResponse('success', 'CRM Contact created and linked successfully.', [
            'contact_id' => (int)$crmContactId,
            'name' => $profileName
        ]);
    }
    
    elseif ($method === 'LINK_CRM_CONTACT') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $waContactId = (int)($input['wa_contact_id'] ?? 0);
        $crmContactId = (int)($input['contact_id'] ?? 0);
        
        if ($waContactId <= 0 || $crmContactId <= 0) {
            sendJsonResponse('error', 'wa_contact_id and contact_id are required.', [], 400);
        }
        
        // Verify WhatsApp Contact ownership
        $stmtWa = $db->prepare("SELECT id FROM whatsapp_contacts WHERE id = ? AND user_id = ?");
        $stmtWa->execute([$waContactId, $userId]);
        if (!$stmtWa->fetch()) {
            sendJsonResponse('error', 'WhatsApp contact thread not found.', [], 404);
        }
        
        // Verify CRM Contact ownership
        $stmtCrm = $db->prepare("SELECT id, name FROM crm_contacts WHERE id = ? AND user_id = ?");
        $stmtCrm->execute([$crmContactId, $userId]);
        $crmContact = $stmtCrm->fetch();
        if (!$crmContact) {
            sendJsonResponse('error', 'CRM Contact not found.', [], 404);
        }
        
        // Link them
        $db->prepare("UPDATE whatsapp_contacts SET contact_id = ? WHERE id = ? AND user_id = ?")->execute([$crmContactId, $waContactId, $userId]);
        
        sendJsonResponse('success', 'WhatsApp thread successfully linked to CRM Contact.', [
            'contact_id' => $crmContactId,
            'name' => $crmContact['name']
        ]);
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    WhatsAppMetaService::logDebug("Inbox operation exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendJsonResponse('error', 'Inbox operation failed: ' . $e->getMessage(), [], 500);
}
