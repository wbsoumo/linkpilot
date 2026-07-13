<?php
// backend/api/external_apps/zoom_auth.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../external_apps_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$creds = ExternalAppsHelper::getZoomCredentials();

if ($creds['enabled'] !== '1') {
    sendJsonResponse('error', 'Zoom Integration is disabled by the administrator.', [], 400);
}
if (empty($creds['client_id']) || empty($creds['client_secret'])) {
    sendJsonResponse('error', 'Zoom Client ID or Client Secret is not configured in the Admin Portal.', [], 400);
}

// We dynamically construct the Redirect URI to match the current origin host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . '/backend/api/external_apps/zoom_callback.php';

// Securely encode user state context
$hash = md5($userId . ENCRYPTION_KEY);
$from = $_GET['from'] ?? '';
$fromClean = preg_replace('/[^a-zA-Z0-9]/', '', $from);
$stateEncoded = $userId . '-' . $hash . '-' . $fromClean;

$authUrl = "https://zoom.us/oauth/authorize?" . http_build_query([
    'response_type' => 'code',
    'client_id' => $creds['client_id'],
    'redirect_uri' => $redirectUri,
    'state' => $stateEncoded
]);

sendJsonResponse('success', 'Zoom Auth URL generated successfully.', ['auth_url' => $authUrl]);
