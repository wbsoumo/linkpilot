<?php
// backend/api/google/settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$syncEnabled = isset($input['sync_enabled']) ? (int)(bool)$input['sync_enabled'] : 1;
$syncNewLeads = isset($input['sync_new_leads']) ? (int)(bool)$input['sync_new_leads'] : 1;
$syncUpdateLeads = isset($input['sync_update_leads']) ? (int)(bool)$input['sync_update_leads'] : 1;
$syncStatus = isset($input['sync_status']) ? (int)(bool)$input['sync_status'] : 1;
$syncNotes = isset($input['sync_notes']) ? (int)(bool)$input['sync_notes'] : 1;
$syncFollowups = isset($input['sync_followups']) ? (int)(bool)$input['sync_followups'] : 1;
$syncLeadScore = isset($input['sync_lead_score']) ? (int)(bool)$input['sync_lead_score'] : 1;

try {
    $db = Database::getConnection();
    
    // Make sure connection exists
    $stmtCheck = $db->prepare("SELECT id FROM google_sheet_connections WHERE user_id = ?");
    $stmtCheck->execute([$userId]);
    if (!$stmtCheck->fetch()) {
        sendJsonResponse('error', 'Google Sheets connection not configured yet.', [], 400);
    }
    
    $stmtUpdate = $db->prepare("
        UPDATE google_sheet_connections 
        SET sync_enabled = ?, sync_new_leads = ?, sync_update_leads = ?, sync_status = ?, sync_notes = ?, sync_followups = ?, sync_lead_score = ?
        WHERE user_id = ?
    ");
    $stmtUpdate->execute([
        $syncEnabled, $syncNewLeads, $syncUpdateLeads, $syncStatus, $syncNotes, $syncFollowups, $syncLeadScore, $userId
    ]);
    
    sendJsonResponse('success', 'Google Sheets synchronization settings updated successfully.');
} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to save settings: ' . $e->getMessage(), [], 500);
}
