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

    // 1. Google connection
    $stmt = $db->prepare("SELECT email, connected_email, connected_name, avatar, scopes, status, updated_at FROM external_app_connections WHERE user_id = ? AND provider = 'google' LIMIT 1");
    $stmt->execute([$userId]);
    $googleConn = $stmt->fetch();

    $googleData = [
        'connected' => false,
        'email' => null,
        'name' => null,
        'avatar' => null,
        'last_sync' => null,
        'scopes' => [],
        'profile_connected' => false,
        'calendar_connected' => false,
        'gmail_connected' => false
    ];

    if ($googleConn && $googleConn['status'] === 'connected') {
        $scopesArray = !empty($googleConn['scopes']) ? explode(',', $googleConn['scopes']) : [];
        $googleData = [
            'connected' => true,
            'email' => !empty($googleConn['connected_email']) ? $googleConn['connected_email'] : $googleConn['email'],
            'name' => $googleConn['connected_name'],
            'avatar' => $googleConn['avatar'],
            'last_sync' => $googleConn['updated_at'],
            'scopes' => $scopesArray,
            'profile_connected' => in_array('openid', $scopesArray) || in_array('email', $scopesArray) || in_array('profile', $scopesArray),
            'calendar_connected' => in_array('https://www.googleapis.com/auth/calendar.events', $scopesArray),
            'gmail_connected' => (in_array('https://www.googleapis.com/auth/gmail.send', $scopesArray) && in_array('https://www.googleapis.com/auth/gmail.modify', $scopesArray)) || in_array('https://mail.google.com/', $scopesArray)
        ];
    }

    // 2. Zoom connection
    $stmt = $db->prepare("SELECT email, connected_email, connected_name, avatar, status, updated_at FROM external_app_connections WHERE user_id = ? AND provider = 'zoom' LIMIT 1");
    $stmt->execute([$userId]);
    $zoomConn = $stmt->fetch();

    $zoomData = [
        'connected' => false,
        'email' => null,
        'name' => null,
        'avatar' => null,
        'last_sync' => null
    ];

    if ($zoomConn && $zoomConn['status'] === 'connected') {
        $zoomData = [
            'connected' => true,
            'email' => !empty($zoomConn['connected_email']) ? $zoomConn['connected_email'] : $zoomConn['email'],
            'name' => $zoomConn['connected_name'],
            'avatar' => $zoomConn['avatar'],
            'last_sync' => $zoomConn['updated_at']
        ];
    }

    sendJsonResponse('success', 'External integrations status retrieved successfully.', [
        'google' => $googleData,
        'zoom' => $zoomData,
        // Keep fallback properties for backward compatibility
        'connected' => $googleData['connected'],
        'email' => $googleData['email'],
        'name' => $googleData['name'],
        'avatar' => $googleData['avatar'],
        'last_sync' => $googleData['last_sync'],
        'scopes' => $googleData['scopes'],
        'profile_connected' => $googleData['profile_connected'],
        'calendar_connected' => $googleData['calendar_connected'],
        'gmail_connected' => $googleData['gmail_connected']
    ]);

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
