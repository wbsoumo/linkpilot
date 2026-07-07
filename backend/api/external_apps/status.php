<?php
// backend/api/external_apps/status.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$db = Database::getConnection();

$stmt = $db->prepare("SELECT email, status, updated_at FROM external_app_connections WHERE user_id = ? AND provider = 'google' LIMIT 1");
$stmt->execute([$userId]);
$conn = $stmt->fetch();

if ($conn && $conn['status'] === 'connected') {
    sendJsonResponse('success', 'Connection retrieved successfully.', [
        'connected' => true,
        'email' => $conn['email'],
        'last_sync' => $conn['updated_at']
    ]);
} else {
    sendJsonResponse('success', 'No active Google connection found.', [
        'connected' => false,
        'email' => null,
        'last_sync' => null
    ]);
}
