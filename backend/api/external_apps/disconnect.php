<?php
// backend/api/external_apps/disconnect.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$db = Database::getConnection();

$stmt = $db->prepare("DELETE FROM external_app_connections WHERE user_id = ? AND provider = 'google'");
$stmt->execute([$userId]);

// Log connection disconnection details
$timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, activity_type, description) VALUES (?, 'Integration Disconnected', ?)");
$timelineStmt->execute([$userId, "Disconnected Google integrations account."]);

sendJsonResponse('success', 'Google integration disconnected successfully.');
