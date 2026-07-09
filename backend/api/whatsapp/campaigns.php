<?php
// backend/api/whatsapp/campaigns.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

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
        if (isset($_GET['id'])) {
            $campaignId = (int)$_GET['id'];
            
            // Fetch campaign details with dynamic replies_count
            $stmt = $db->prepare("
                SELECT c.*,
                       COALESCE((
                           SELECT COUNT(DISTINCT m.wa_contact_id)
                           FROM whatsapp_messages m
                           JOIN whatsapp_campaign_logs l ON m.wa_contact_id = l.wa_contact_id
                           WHERE l.campaign_id = c.id
                             AND m.direction = 'inbound'
                             AND l.sent_at IS NOT NULL
                             AND m.created_at >= l.sent_at
                       ), 0) as replies_count
                FROM whatsapp_campaigns c
                WHERE c.id = ? AND c.user_id = ?
            ");
            $stmt->execute([$campaignId, $userId]);
            $campaign = $stmt->fetch();
            if (!$campaign) {
                sendJsonResponse('error', 'Campaign not found.', [], 404);
            }
            
            // Fetch logs for this campaign
            $stmtLogs = $db->prepare("
                SELECT l.*, c.wa_id, c.profile_name 
                FROM whatsapp_campaign_logs l
                JOIN whatsapp_contacts c ON l.wa_contact_id = c.id
                WHERE l.campaign_id = ?
            ");
            $stmtLogs->execute([$campaignId]);
            $campaign['logs'] = $stmtLogs->fetchAll();
            
            sendJsonResponse('success', 'Campaign retrieved successfully.', ['campaign' => $campaign]);
            
        } else {
            // List campaigns with dynamic replies_count
            $stmt = $db->prepare("
                SELECT c.*,
                       COALESCE((
                           SELECT COUNT(DISTINCT m.wa_contact_id)
                           FROM whatsapp_messages m
                           JOIN whatsapp_campaign_logs l ON m.wa_contact_id = l.wa_contact_id
                           WHERE l.campaign_id = c.id
                             AND m.direction = 'inbound'
                             AND l.sent_at IS NOT NULL
                             AND m.created_at >= l.sent_at
                       ), 0) as replies_count
                FROM whatsapp_campaigns c
                WHERE c.user_id = ?
                ORDER BY c.id DESC
            ");
            $stmt->execute([$userId]);
            $campaigns = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Campaigns list loaded.', ['campaigns' => $campaigns]);
        }
    }
    
    elseif ($method === 'CREATE') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $name = trim($input['name'] ?? '');
        $templateName = trim($input['template_name'] ?? '');
        $templateLanguage = trim($input['template_language'] ?? 'en');
        $filters = $input['filters'] ?? [];
        $recipients = $input['recipients'] ?? [];
        
        if (empty($name) || empty($templateName)) {
            sendJsonResponse('error', 'Campaign Name and Template Name are required.', [], 400);
        }
        
        $matchingContacts = [];
        $recipientVars = [];
        
        if (!empty($recipients)) {
            // Use custom manual / CSV / My Contacts recipients directly
            foreach ($recipients as $rec) {
                $rawPhone = preg_replace('/[^0-9]/', '', $rec['phone'] ?? '');
                if (empty($rawPhone)) continue;
                
                if (strlen($rawPhone) === 10) {
                    $rawPhone = '91' . $rawPhone;
                }
                
                $matchingContacts[] = [
                    'id' => null,
                    'name' => trim($rec['name'] ?? 'WhatsApp Contact'),
                    'phone' => $rawPhone
                ];
                
                $recipientVars[$rawPhone] = [
                    'name' => trim($rec['name'] ?? 'WhatsApp Contact'),
                    'val1' => trim($rec['val1'] ?? ''),
                    'val2' => trim($rec['val2'] ?? '')
                ];
            }
        } else {
            // Fetch matching CRM contacts based on audience filters
            $sql = "SELECT DISTINCT c.id, c.name, c.phone FROM crm_contacts c LEFT JOIN crm_companies co ON c.company_id = co.id WHERE c.user_id = :user_id AND c.phone IS NOT NULL AND c.phone != ''";
            $params = ['user_id' => $userId];
            
            if (!empty($filters['company'])) {
                $sql .= " AND (co.name LIKE :company OR c.notes LIKE :company)";
                $params['company'] = "%{$filters['company']}%";
            }
            if (!empty($filters['industry'])) {
                $sql .= " AND co.industry LIKE :industry";
                $params['industry'] = "%{$filters['industry']}%";
            }
            if (!empty($filters['city'])) {
                $sql .= " AND (c.location LIKE :city OR co.address LIKE :city)";
                $params['city'] = "%{$filters['city']}%";
            }
            if (!empty($filters['tags'])) {
                $sql .= " AND (c.notes LIKE :tags OR co.tags LIKE :tags)";
                $params['tags'] = "%{$filters['tags']}%";
            }
            
            $stmtC = $db->prepare($sql);
            $stmtC->execute($params);
            $matchingContacts = $stmtC->fetchAll();
        }
        
        if (empty($matchingContacts)) {
            sendJsonResponse('error', 'No contacts selected or matched the filters.', [], 400);
        }
        
        // Merge recipient variables into filters json for storage
        $filters['recipients_vars'] = $recipientVars;
        
        $db->beginTransaction();
        try {
            // 2. Create whatsapp_campaigns entry
            $stmtCamp = $db->prepare("
                INSERT INTO whatsapp_campaigns (user_id, name, template_name, template_language, filters_json, status, total_contacts)
                VALUES (?, ?, ?, ?, ?, 'draft', ?)
            ");
            $stmtCamp->execute([
                $userId, $name, $templateName, $templateLanguage, json_encode($filters), count($matchingContacts)
            ]);
            $campaignId = $db->lastInsertId();
            
            // 3. Create whatsapp_contacts and whatsapp_campaign_logs entries
            $stmtInsLog = $db->prepare("INSERT INTO whatsapp_campaign_logs (campaign_id, wa_contact_id, status) VALUES (?, ?, 'pending')");
            
            foreach ($matchingContacts as $mc) {
                $rawPhone = preg_replace('/[^0-9]/', '', $mc['phone']);
                if (empty($rawPhone)) continue;
                
                if (strlen($rawPhone) === 10) {
                    $rawPhone = '91' . $rawPhone;
                }
                
                // Locate or create whatsapp_contacts entry
                $stmtWCon = $db->prepare("SELECT id FROM whatsapp_contacts WHERE user_id = ? AND wa_id = ?");
                $stmtWCon->execute([$userId, $rawPhone]);
                $waContactId = $stmtWCon->fetchColumn();
                
                if (!$waContactId) {
                    $crmId = !empty($mc['id']) ? $mc['id'] : null;
                    $stmtInsWCon = $db->prepare("INSERT INTO whatsapp_contacts (user_id, contact_id, wa_id, profile_name, last_message_at, unread_count) VALUES (?, ?, ?, ?, NOW(), 0)");
                    $stmtInsWCon->execute([$userId, $crmId, $rawPhone, $mc['name']]);
                    $waContactId = $db->lastInsertId();
                }
                
                // Insert Campaign Log
                $stmtInsLog->execute([$campaignId, $waContactId]);
            }
            
            $db->commit();
        } catch (Exception $txEx) {
            $db->rollBack();
            throw $txEx;
        }
        
        sendJsonResponse('success', 'WhatsApp Campaign draft created successfully.', ['campaign_id' => $campaignId]);
    }
    
    elseif ($method === 'SEND') {
        // Broadcast / Execute Campaign
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $campaignId = (int)($input['campaign_id'] ?? 0);
        
        if ($campaignId <= 0) {
            sendJsonResponse('error', 'Campaign ID is required.', [], 400);
        }
        
        // Fetch Connected Phone Number ID & Token
        $stmtAcc = $db->prepare("SELECT phone_number_id FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
        $stmtAcc->execute([$userId]);
        $phoneId = $stmtAcc->fetchColumn();
        if (!$phoneId) {
            sendJsonResponse('error', 'No connected WhatsApp Business Account found.', [], 400);
        }
        
        // Fetch Campaign details
        $stmtCamp = $db->prepare("SELECT * FROM whatsapp_campaigns WHERE id = ? AND user_id = ?");
        $stmtCamp->execute([$campaignId, $userId]);
        $campaign = $stmtCamp->fetch();
        if (!$campaign) {
            sendJsonResponse('error', 'Campaign not found.', [], 404);
        }
        
        // Fetch pending logs
        $stmtLogs = $db->prepare("
            SELECT l.id as log_id, c.wa_id 
            FROM whatsapp_campaign_logs l
            JOIN whatsapp_contacts c ON l.wa_contact_id = c.id
            WHERE l.campaign_id = ? AND l.status IN ('pending', 'failed')
        ");
        $stmtLogs->execute([$campaignId]);
        $pendingLogs = $stmtLogs->fetchAll();
        
        if (empty($pendingLogs)) {
            sendJsonResponse('error', 'No pending dispatch targets found for this campaign.', [], 400);
        }
        
        $db->beginTransaction();
        try {
            // Queue each message target
            $stmtQueue = $db->prepare("
                INSERT INTO whatsapp_queue (user_id, phone_number_id, recipient_number, payload_json, type, status)
                VALUES (?, ?, ?, ?, 'template', 'pending')
            ");
            
            $filters = json_decode($campaign['filters_json'], true) ?: [];
            $recipientVars = $filters['recipients_vars'] ?? [];
            
            foreach ($pendingLogs as $pLog) {
                $components = [];
                $vars = $recipientVars[$pLog['wa_id']] ?? [];
                
                if (!empty($vars)) {
                    $parameters = [];
                    if (!empty($vars['val1'])) {
                        $parameters[] = ['type' => 'text', 'text' => $vars['val1']];
                    }
                    if (!empty($vars['val2'])) {
                        $parameters[] = ['type' => 'text', 'text' => $vars['val2']];
                    }
                    if (!empty($parameters)) {
                        $components[] = [
                            'type' => 'body',
                            'parameters' => $parameters
                        ];
                    }
                }
                
                $payload = [
                    'template_name' => $campaign['template_name'],
                    'language_code' => $campaign['template_language'],
                    'components' => $components,
                    'campaign_log_id' => $pLog['log_id']
                ];
                
                $stmtQueue->execute([
                    $userId,
                    $phoneId,
                    $pLog['wa_id'],
                    json_encode($payload)
                ]);
                
                // Update log status to queued
                $db->prepare("UPDATE whatsapp_campaign_logs SET status = 'queued' WHERE id = ?")->execute([$pLog['log_id']]);
            }
            
            // Set Campaign status to sending/scheduled
            $db->prepare("UPDATE whatsapp_campaigns SET status = 'sending' WHERE id = ?")->execute([$campaignId]);
            
            $db->commit();
        } catch (Exception $tx) {
            $db->rollBack();
            throw $tx;
        }
        
        logActivity($userId, "Started WhatsApp Broadcast Campaign: {$campaign['name']}");
        
        sendJsonResponse('success', 'WhatsApp Campaign queued for background broadcast execution successfully.', [
            'queued_targets' => count($pendingLogs)
        ]);
    }
    
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $campaignId = (int)($input['campaign_id'] ?? $_GET['id'] ?? 0);
        
        if ($campaignId <= 0) {
            sendJsonResponse('error', 'Campaign ID is required.', [], 400);
        }
        
        // Verify ownership
        $stmtCheck = $db->prepare("SELECT id FROM whatsapp_campaigns WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$campaignId, $userId]);
        if (!$stmtCheck->fetchColumn()) {
            sendJsonResponse('error', 'Campaign not found.', [], 404);
        }
        
        $db->prepare("DELETE FROM whatsapp_campaigns WHERE id = ?")->execute([$campaignId]);
        
        sendJsonResponse('success', 'Campaign deleted successfully.');
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Campaign operation failed: ' . $e->getMessage(), [], 500);
}
