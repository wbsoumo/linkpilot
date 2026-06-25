<?php
// backend/api/google/auth.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../google_sheets_helper.php';

// Ensure DB tables are configured
GoogleSheetsHelper::checkDatabaseSchema();

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if (!defined('GOOGLE_CLIENT_ID') || !defined('GOOGLE_CLIENT_SECRET') || !defined('GOOGLE_REDIRECT_URI') || 
    GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID' || empty(GOOGLE_CLIENT_ID)) {
    sendJsonResponse('error', 'Google OAuth credentials are not configured. Please define GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in backend/config.php.', [], 400);
}

$scopes = [
    'https://www.googleapis.com/auth/userinfo.email',
    'https://www.googleapis.com/auth/userinfo.profile',
    'https://www.googleapis.com/auth/spreadsheets'
];

// Pack state to match user context
$state = json_encode(['user_id' => $userId]);
$stateEncoded = urlencode(base64_encode($state));

$authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => implode(' ', $scopes),
    'access_type' => 'offline',
    'prompt' => 'consent',
    'state' => $stateEncoded
]);

sendJsonResponse('success', 'Auth URL generated successfully.', ['auth_url' => $authUrl]);
