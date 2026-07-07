<?php
// backend/api/external_apps/auth.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../external_apps_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$creds = ExternalAppsHelper::getGoogleCredentials();

if (empty($creds['client_id']) || empty($creds['client_secret'])) {
    sendJsonResponse('error', 'Google OAuth credentials are not configured by the administrator in settings.', [], 400);
}

if ($creds['enabled'] !== '1') {
    sendJsonResponse('error', 'Google Integration is disabled by the administrator.', [], 400);
}

// Securely encode user state context
$hash = md5($userId . ENCRYPTION_KEY);
$stateEncoded = $userId . '-' . $hash;

// We dynamically construct the Redirect URI to match the current origin host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . '/backend/api/external_apps/callback.php';

$authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $creds['client_id'],
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => $creds['scopes'],
    'access_type' => 'offline',
    'prompt' => 'consent',
    'state' => $stateEncoded
]);

sendJsonResponse('success', 'Auth URL generated successfully.', ['auth_url' => $authUrl]);
