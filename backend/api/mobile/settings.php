<?php
// backend/api/mobile/settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

header('Content-Type: application/json');

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

try {
    $stmtUser = $db->prepare("SELECT id, name, email, phone_number, role, created_at FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch() ?: $user;

    // Check connected services
    $stmtSmtp = $db->prepare("SELECT COUNT(*) FROM smtp_accounts WHERE user_id = ?");
    $stmtSmtp->execute([$userId]);
    $gmailConnected = ((int)$stmtSmtp->fetchColumn()) > 0;

    $stmtWaCheck = $db->prepare("SELECT COUNT(*) FROM whatsapp_contacts WHERE user_id = ?");
    $stmtWaCheck->execute([$userId]);
    $whatsappConnected = ((int)$stmtWaCheck->fetchColumn()) > 0;

    sendJsonResponse('success', 'Settings loaded', [
        'user' => $userData,
        'integrations' => [
            'gmail' => [
                'connected' => $gmailConnected,
                'label' => $gmailConnected ? 'Connected' : 'Not Connected'
            ],
            'whatsapp' => [
                'connected' => $whatsappConnected,
                'label' => $whatsappConnected ? 'Connected' : 'Not Connected'
            ]
        ],
        'app_version' => '1.0.0',
        'website_url' => 'https://linkpilot.work'
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to load settings: ' . $e->getMessage(), [], 500);
}
