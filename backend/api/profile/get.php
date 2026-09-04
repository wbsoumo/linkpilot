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
    $userData = [];
    try {
        $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $ex) {}
    
    if (!$userData) {
        $userData = ['id' => $userId, 'name' => $user['name'] ?? 'Admin', 'email' => $user['email'] ?? '', 'role' => $user['role'] ?? 'admin'];
    }
    
    // Check if there is at least one key in user_ai_keys table for each provider (non-invalid)
    $keysCounts = [];
    try {
        $stmtKeysCount = $db->prepare("SELECT provider, COUNT(*) as cnt FROM user_ai_keys WHERE user_id = ? AND status != 'invalid' GROUP BY provider");
        $stmtKeysCount->execute([$userId]);
        $keysCounts = $stmtKeysCount->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $ex) {}

    $userData['has_openrouter_key'] = !empty($keysCounts['openrouter']);
    $userData['has_github_key'] = !empty($keysCounts['github_models']);
    $userData['has_google_key'] = !empty($keysCounts['google_ai_studio']);
    $userData['active_ai_provider'] = $userData['active_ai_provider'] ?? 'github_models';
    $userData['active_ai_model'] = $userData['active_ai_model'];
    unset($userData['openrouter_key']);
    unset($userData['github_key']);
    unset($userData['google_key']);
    
    // 2. Fetch Profile details
    $profileData = null;
    try {
        $stmtProfile = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
        $stmtProfile->execute([$userId]);
        $profileData = $stmtProfile->fetch();
    } catch (Exception $ex) {}
    
    // 3. Fetch SMTP account (only status and configuration meta, no decrypted passwords)
    $smtpData = null;
    try {
        $stmtSMTP = $db->prepare("SELECT host, port, username, sender_name, sender_email, smtp_type, updated_at FROM smtp_accounts WHERE user_id = ?");
        $stmtSMTP->execute([$userId]);
        $smtpData = $stmtSMTP->fetch();
    } catch (Exception $ex) {}
    
    sendJsonResponse('success', 'Profile loaded.', [
        'user' => $userData,
        'profile' => $profileData ?: null,
        'smtp' => $smtpData ?: null
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error loading profile: ' . $e->getMessage(), [], 500);
}
