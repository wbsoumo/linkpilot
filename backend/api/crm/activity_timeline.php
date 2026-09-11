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
    
    $query = "SELECT id, user_id, contact_id, channel, direction, summary, metadata_json, created_at FROM crm_activity_timeline WHERE user_id = :user_id";
    $params = ['user_id' => $userId];
    
    if ($contactId > 0) {
        $query .= " AND contact_id = :contact_id";
        $params['contact_id'] = $contactId;
    }
    if (!empty($channel) && in_array($channel, ['email', 'whatsapp', 'call', 'task', 'ticket', 'system'])) {
        $query .= " AND channel = :channel";
        $params['channel'] = $channel;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If unified timeline is empty or has few records, merge legacy crm_timeline records
    if (count($activities) < 5 && ($contactId > 0 || $leadId > 0 || $companyId > 0)) {
        $legQuery = "SELECT id, user_id, contact_id, 'system' as channel, 'system' as direction, description as summary, NULL as metadata_json, created_at FROM crm_timeline WHERE user_id = :user_id";
        $legParams = ['user_id' => $userId];
        if ($contactId > 0) {
            $legQuery .= " AND contact_id = :contact_id";
            $legParams['contact_id'] = $contactId;
        }
        $legQuery .= " ORDER BY created_at DESC LIMIT 20";
        $stmtLeg = $db->prepare($legQuery);
        $stmtLeg->execute($legParams);
        $legActivities = $stmtLeg->fetchAll(PDO::FETCH_ASSOC);

        $activities = array_merge($activities, $legActivities);
        usort($activities, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
    
    sendJsonResponse('success', 'Unified customer activity timeline fetched successfully', [
        'activities' => $activities
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
