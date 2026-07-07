<?php
// backend/api/external_apps/status.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../external_apps_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

// Trigger self-healing database migration
ExternalAppsHelper::checkDatabaseSchema();

try {
    $db = Database::getConnection();

    $stmt = $db->prepare("SELECT email, connected_email, connected_name, avatar, scopes, status, updated_at FROM external_app_connections WHERE user_id = ? AND provider = 'google' LIMIT 1");
    $stmt->execute([$userId]);
    $conn = $stmt->fetch();

    if ($conn && $conn['status'] === 'connected') {
        $scopesArray = !empty($conn['scopes']) ? explode(',', $conn['scopes']) : [];
        sendJsonResponse('success', 'Connection retrieved successfully.', [
            'connected' => true,
            'email' => !empty($conn['connected_email']) ? $conn['connected_email'] : $conn['email'],
            'name' => $conn['connected_name'],
            'avatar' => $conn['avatar'],
            'last_sync' => $conn['updated_at'],
            'scopes' => $scopesArray,
            'profile_connected' => in_array('openid', $scopesArray) || in_array('email', $scopesArray) || in_array('profile', $scopesArray),
            'calendar_connected' => in_array('https://www.googleapis.com/auth/calendar.events', $scopesArray),
            'gmail_connected' => in_array('https://www.googleapis.com/auth/gmail.send', $scopesArray)
        ]);
    } else {
        sendJsonResponse('success', 'No active Google connection found.', [
            'connected' => false,
            'email' => null,
            'name' => null,
            'avatar' => null,
            'last_sync' => null,
            'scopes' => [],
            'profile_connected' => false,
            'calendar_connected' => false,
            'gmail_connected' => false
        ]);
    }
} catch (Exception $e) {
    sendJsonResponse('success', 'Database access failed: ' . $e->getMessage(), [
        'connected' => false,
        'email' => null,
        'name' => null,
        'avatar' => null,
        'last_sync' => null,
        'scopes' => [],
        'profile_connected' => false,
        'calendar_connected' => false,
        'gmail_connected' => false,
        'error' => $e->getMessage()
    ]);
}
