<?php
// backend/api/smtp/list.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$db = Database::getConnection();

try {
    $stmt = $db->prepare("SELECT id, host, port, username, sender_name, sender_email, smtp_type, is_default, updated_at FROM smtp_accounts WHERE user_id = ? ORDER BY is_default DESC, id ASC");
    $stmt->execute([$userId]);
    $accounts = $stmt->fetchAll();
    
    // Format response, mask password presence
    foreach ($accounts as &$account) {
        $account['password'] = '••••••••';
    }
    
    // Also fetch the user's active email template preference
    $stmtTemplate = $db->prepare("SELECT active_email_template FROM users WHERE id = ?");
    $stmtTemplate->execute([$userId]);
    $userRes = $stmtTemplate->fetch();
    $activeTemplate = $userRes['active_email_template'] ?? 'minimalist';
    
    sendJsonResponse('success', 'SMTP accounts loaded.', [
        'accounts' => $accounts,
        'active_email_template' => $activeTemplate
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error listing SMTP settings: ' . $e->getMessage(), [], 500);
}
