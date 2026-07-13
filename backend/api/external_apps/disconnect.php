<?php
// backend/api/external_apps/disconnect.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$provider = $_GET['provider'] ?? $_POST['provider'] ?? 'google';
if ($provider !== 'google' && $provider !== 'zoom') {
    $provider = 'google';
}

$db = Database::getConnection();

$stmt = $db->prepare("DELETE FROM external_app_connections WHERE user_id = ? AND provider = ?");
$stmt->execute([$userId, $provider]);

// Log connection disconnection details
$timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, activity_type, description) VALUES (?, 'Integration Disconnected', ?)");
$providerLabel = ($provider === 'google') ? 'Google' : 'Zoom';
$timelineStmt->execute([$userId, "Disconnected {$providerLabel} integrations account."]);

sendJsonResponse('success', "{$providerLabel} integration disconnected successfully.");
