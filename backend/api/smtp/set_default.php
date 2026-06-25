<?php
// backend/api/smtp/set_default.php

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
$id = isset($input['id']) ? (int)$input['id'] : null;

if (!$id) {
    sendJsonResponse('error', 'SMTP Account ID is required.', [], 400);
}

$db = Database::getConnection();

try {
    // Check if account exists and belongs to user
    $stmtCheck = $db->prepare("SELECT id FROM smtp_accounts WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$id, $userId]);
    $account = $stmtCheck->fetch();
    
    if (!$account) {
        sendJsonResponse('error', 'SMTP account not found or unauthorized.', [], 404);
    }
    
    // Set all accounts for this user to NOT default
    $stmtReset = $db->prepare("UPDATE smtp_accounts SET is_default = 0 WHERE user_id = ?");
    $stmtReset->execute([$userId]);
    
    // Set this specific account as default
    $stmtSet = $db->prepare("UPDATE smtp_accounts SET is_default = 1 WHERE id = ?");
    $stmtSet->execute([$id]);
    
    logActivity($userId, "Set SMTP configuration ID " . $id . " as active default.");
    
    sendJsonResponse('success', 'Default SMTP account updated successfully.');
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error updating SMTP settings: ' . $e->getMessage(), [], 500);
}
