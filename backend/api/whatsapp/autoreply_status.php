<?php
// backend/api/whatsapp/autoreply_status.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

try {
    // 1. Check AI enabled in Settings
    $stmtSettings = $db->prepare("SELECT ai_enabled FROM whatsapp_settings WHERE user_id = ? LIMIT 1");
    $stmtSettings->execute([$userId]);
    $aiEnabled = (int)$stmtSettings->fetchColumn();

    if ($aiEnabled !== 1) {
        sendJsonResponse('success', 'Auto-Reply is inactive.', [
            'live' => false,
            'status' => 'paused',
            'reason' => 'AI Autopilot (Auto-Reply) is disabled in your WhatsApp settings.',
            'fix_action' => 'enable_autopilot'
        ]);
    }

    // 2. Check WhatsApp connection status
    $stmtAcc = $db->prepare("SELECT status FROM whatsapp_accounts WHERE user_id = ? LIMIT 1");
    $stmtAcc->execute([$userId]);
    $waStatus = $stmtAcc->fetchColumn();

    if ($waStatus !== 'connected') {
        sendJsonResponse('success', 'Auto-Reply is inactive.', [
            'live' => false,
            'status' => 'disconnected',
            'reason' => 'Your WhatsApp Business account instance is disconnected.',
            'fix_action' => 'connect_whatsapp'
        ]);
    }

    // 3. Check wallet credit balance
    $stmtWallet = $db->prepare("SELECT remaining_credits FROM user_email_credits WHERE user_id = ?");
    $stmtWallet->execute([$userId]);
    $remainingCredits = (int)$stmtWallet->fetchColumn();

    if ($remainingCredits <= 0) {
        sendJsonResponse('success', 'Auto-Reply is inactive.', [
            'live' => false,
            'status' => 'no_credits',
            'reason' => 'Your workspace has run out of credits. Auto-replies are paused.',
            'fix_action' => 'recharge_wallet'
        ]);
    }

    // 4. Check AI Keys status
    $stmtKeys = $db->prepare("SELECT status, error_message FROM user_ai_keys WHERE user_id = ?");
    $stmtKeys->execute([$userId]);
    $keys = $stmtKeys->fetchAll(PDO::FETCH_ASSOC);

    if (count($keys) === 0) {
        sendJsonResponse('success', 'Auto-Reply is inactive.', [
            'live' => false,
            'status' => 'no_keys',
            'reason' => 'No AI API keys are configured for your account.',
            'fix_action' => 'configure_keys'
        ]);
    }

    $activeKeyCount = 0;
    $hasKeyErrors = false;
    $errorMessage = "";

    foreach ($keys as $k) {
        if ($k['status'] === 'active') {
            $activeKeyCount++;
            if (!empty($k['error_message'])) {
                $hasKeyErrors = true;
                $errorMessage = $k['error_message'];
            }
        }
    }

    if ($activeKeyCount === 0) {
        sendJsonResponse('success', 'Auto-Reply is inactive.', [
            'live' => false,
            'status' => 'keys_paused',
            'reason' => 'All of your AI API keys are currently paused.',
            'fix_action' => 'configure_keys'
        ]);
    }

    if ($hasKeyErrors) {
        sendJsonResponse('success', 'Auto-Reply has key error.', [
            'live' => false,
            'status' => 'key_error',
            'reason' => 'Your active AI API key is experiencing errors: ' . $errorMessage,
            'fix_action' => 'configure_keys'
        ]);
    }

    // 5. Everything is OK!
    sendJsonResponse('success', 'Auto-Reply is live.', [
        'live' => true,
        'status' => 'live',
        'reason' => 'AI Autopilot is active and responding to customer chats.',
        'fix_action' => ''
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Status check failed: ' . $e->getMessage(), [], 500);
}
