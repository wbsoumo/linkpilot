<?php
// backend/api/smtp/delete.php

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
    $stmtCheck = $db->prepare("SELECT is_default FROM smtp_accounts WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$id, $userId]);
    $account = $stmtCheck->fetch();
    
    if (!$account) {
        sendJsonResponse('error', 'SMTP account not found or unauthorized.', [], 404);
    }
    
    $wasDefault = $account['is_default'];
    
    // Delete account
    $stmtDelete = $db->prepare("DELETE FROM smtp_accounts WHERE id = ? AND user_id = ?");
    $stmtDelete->execute([$id, $userId]);
    
    logActivity($userId, "Deleted SMTP configuration ID: " . $id);
    
    // If deleted account was default, set another account as default (if exists)
    if ($wasDefault) {
        $stmtFallback = $db->prepare("SELECT id FROM smtp_accounts WHERE user_id = ? ORDER BY id ASC LIMIT 1");
        $stmtFallback->execute([$userId]);
        $fallback = $stmtFallback->fetch();
        
        if ($fallback) {
            $stmtSetDefault = $db->prepare("UPDATE smtp_accounts SET is_default = 1 WHERE id = ?");
            $stmtSetDefault->execute([$fallback['id']]);
            logActivity($userId, "Set SMTP configuration ID " . $fallback['id'] . " as fallback default.");
        }
    }
    
    sendJsonResponse('success', 'SMTP account deleted successfully.');
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error deleting SMTP settings: ' . $e->getMessage(), [], 500);
}
