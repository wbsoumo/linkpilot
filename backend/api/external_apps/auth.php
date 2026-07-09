<?php
// backend/api/external_apps/auth.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../external_apps_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$creds = ExternalAppsHelper::getGoogleCredentials();

// We dynamically construct the Redirect URI to match the current origin host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . '/backend/api/external_apps/callback.php';

// Validate configuration
$validationResult = GoogleOAuthHelper::validateConfiguration($creds, $redirectUri);
if ($validationResult !== true) {
    sendJsonResponse('error', $validationResult, [], 400);
}

if ($creds['enabled'] !== '1') {
    sendJsonResponse('error', 'Google Integration is disabled by the administrator.', [], 400);
}

// Get scope type (login, calendar, gmail)
$type = $_GET['type'] ?? 'login';
if (!in_array($type, ['login', 'calendar', 'gmail'])) {
    sendJsonResponse('error', 'Invalid scope flow type specified.', [], 400);
}

$scopes = GoogleOAuthHelper::getScopes($type);
if (empty($scopes)) {
    sendJsonResponse('error', 'No valid scopes configured internally for type: ' . $type, [], 400);
}

// Securely encode user state context, appending integration type and origin
$hash = md5($userId . ENCRYPTION_KEY);
$from = $_GET['from'] ?? '';
$fromClean = preg_replace('/[^a-zA-Z0-9]/', '', $from);
$stateEncoded = $userId . '-' . $hash . '-' . $type . ($fromClean ? '-' . $fromClean : '');

if (!class_exists('Google\Client')) {
    sendJsonResponse('error', 'Google API Client library is not installed on your server. Please run "composer install" in the "backend" directory on your server to install dependencies.', [], 500);
}

try {
    $client = new Google\Client();
    $client->setClientId($creds['client_id']);
    $client->setClientSecret($creds['client_secret']);
    $client->setRedirectUri($redirectUri);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setState($stateEncoded);
    $client->setScopes($scopes);
    
    // Enable Incremental Auth if not initial login
    if ($type !== 'login') {
        $client->setIncludeGrantedScopes(true);
    }
    
    $authUrl = $client->createAuthUrl();
    sendJsonResponse('success', 'Auth URL generated successfully.', ['auth_url' => $authUrl]);
} catch (Exception $e) {
    error_log("Google Client URL generation error: " . $e->getMessage());
    sendJsonResponse('error', 'Google API Client initialization failed: ' . $e->getMessage(), [], 500);
}
