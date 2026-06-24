<?php
// backend/api/profile/get.php

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
    // 1. Fetch User details
    $stmtUser = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch();
    
    if (!$userData) {
        sendJsonResponse('error', 'User not found.', [], 404);
    }
    
    // 2. Fetch Profile details
    $stmtProfile = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $stmtProfile->execute([$userId]);
    $profileData = $stmtProfile->fetch();
    
    // 3. Fetch SMTP account (only status and configuration meta, no decrypted passwords)
    $stmtSMTP = $db->prepare("SELECT host, port, username, sender_name, sender_email, updated_at FROM smtp_accounts WHERE user_id = ?");
    $stmtSMTP->execute([$userId]);
    $smtpData = $stmtSMTP->fetch();
    
    sendJsonResponse('success', 'Profile loaded.', [
        'user' => $userData,
        'profile' => $profileData ?: null,
        'smtp' => $smtpData ?: null
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error loading profile: ' . $e->getMessage(), [], 500);
}
