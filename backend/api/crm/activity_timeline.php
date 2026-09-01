<?php
// backend/api/crm/activity_timeline.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
    
    $contactId = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;
    $leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;
    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
    $channel = isset($_GET['channel']) ? trim($_GET['channel']) : '';
    
    $query = "SELECT * FROM crm_activity_timeline WHERE user_id = :user_id";
    $params = ['user_id' => $userId];
    
    if ($contactId > 0) {
        $query .= " AND contact_id = :contact_id";
        $params['contact_id'] = $contactId;
    }
    if ($leadId > 0) {
        $query .= " AND lead_id = :lead_id";
        $params['lead_id'] = $leadId;
    }
    if ($companyId > 0) {
        $query .= " AND company_id = :company_id";
        $params['company_id'] = $companyId;
    }
    if (!empty($channel) && in_array($channel, ['email', 'whatsapp', 'call'])) {
        $query .= " AND channel = :channel";
        $params['channel'] = $channel;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
    
    sendJsonResponse('success', 'Unified customer activity timeline fetched successfully', [
        'activities' => $activities
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
