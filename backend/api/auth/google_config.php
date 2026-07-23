<?php
// backend/api/auth/google_config.php

require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$db = Database::getConnection();
$clientId = '';

try {
    $stmt = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute(['google_sheets_client_id']);
    $clientId = trim($stmt->fetchColumn() ?: '');
} catch (Exception $e) {}

if (empty($clientId)) {
    $clientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
}

// Ensure we don't return the default placeholder if it is still set
if ($clientId === 'YOUR_GOOGLE_CLIENT_ID') {
    $clientId = '';
}

sendJsonResponse('success', 'Google Client ID fetched.', [
    'client_id' => $clientId
]);
