<?php
// backend/api/whatsapp/contacts.php

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
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, (int)($_GET['limit'] ?? 20));
        $offset = ($page - 1) * $limit;
        
        $sql = "FROM whatsapp_contacts w LEFT JOIN crm_contacts c ON w.contact_id = c.id WHERE w.user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($search !== '') {
            $sql .= " AND (w.profile_name LIKE :search OR w.wa_id LIKE :search OR c.name LIKE :search)";
            $params['search'] = "%{$search}%";
        }
        
        // Count
        $stmtCount = $db->prepare("SELECT COUNT(*) " . $sql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();
        
        // Fetch
        $stmtData = $db->prepare("
            SELECT w.*, c.name AS crm_name, c.email AS crm_email, c.designation AS crm_title 
            " . $sql . " 
            ORDER BY w.last_message_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        
        $stmtData->bindValue(':user_id', $userId, PDO::PARAM_INT);
        if ($search !== '') {
            $stmtData->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
        }
        $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtData->execute();
        
        $contacts = $stmtData->fetchAll();
        
        sendJsonResponse('success', 'Contacts list loaded.', [
            'contacts' => $contacts,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }
    
    elseif ($method === 'UPDATE_TAGS') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $waContactId = (int)($input['wa_contact_id'] ?? 0);
        $tags = trim($input['tags'] ?? '');
        
        if ($waContactId <= 0) {
            sendJsonResponse('error', 'wa_contact_id is required.', [], 400);
        }
        
        $stmt = $db->prepare("UPDATE whatsapp_contacts SET tags = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$tags, $waContactId, $userId]);
        
        sendJsonResponse('success', 'Tags updated successfully.');
    }
    
    elseif ($method === 'LINK_CRM_CONTACT') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $waContactId = (int)($input['wa_contact_id'] ?? 0);
        $crmContactId = !empty($input['crm_contact_id']) ? (int)$input['crm_contact_id'] : null;
        
        if ($waContactId <= 0) {
            sendJsonResponse('error', 'wa_contact_id is required.', [], 400);
        }
        
        $stmt = $db->prepare("UPDATE whatsapp_contacts SET contact_id = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$crmContactId, $waContactId, $userId]);
        
        sendJsonResponse('success', 'CRM Contact associated successfully.');
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Contacts operation failed: ' . $e->getMessage(), [], 500);
}
