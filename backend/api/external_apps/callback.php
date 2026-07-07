<?php
// backend/api/external_apps/callback.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../external_apps_helper.php';

// Disable error display to avoid disrupting redirect headers
ini_set('display_errors', 0);
error_reporting(E_ALL);

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code) || empty($state)) {
    die("Authorization callback error: Missing code or state parameters.");
}

// 1. Verify user state token
$parts = explode('-', $state);
if (count($parts) !== 2) {
    die("Authorization callback error: Invalid state format.");
}

$userId = (int)$parts[0];
$hash = $parts[1];
$expectedHash = md5($userId . ENCRYPTION_KEY);

if ($hash !== $expectedHash) {
    die("Authorization callback error: Security validation mismatch.");
}

$db = Database::getConnection();

// 2. Fetch admin OAuth credentials
$creds = ExternalAppsHelper::getGoogleCredentials();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . '/backend/api/external_apps/callback.php';

// 3. Exchange authorization code for token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$payload = [
    'code' => $code,
    'client_id' => $creds['client_id'],
    'client_secret' => $creds['client_secret'],
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
];

$res = ExternalAppsHelper::makeCurlRequest($tokenUrl, 'POST', $payload, [
    'Content-Type' => 'application/x-www-form-urlencoded'
]);

if ($res['code'] !== 200 || !isset($res['data']['access_token'])) {
    die("Failed to exchange authorization code: " . ($res['data']['error_description'] ?? $res['body']));
}

$accessToken = $res['data']['access_token'];
$refreshToken = $res['data']['refresh_token'] ?? null; // Refresh token only returned on first consent prompt
$expiresIn = $res['data']['expires_in'] ?? 3600;
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

// 4. Fetch Google User email profile details
$profileUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$profileRes = ExternalAppsHelper::makeCurlRequest($profileUrl, 'GET', null, [
    'Authorization' => "Bearer $accessToken"
]);

$googleEmail = null;
if ($profileRes['code'] === 200 && isset($profileRes['data']['email'])) {
    $googleEmail = $profileRes['data']['email'];
}

// 5. Encrypt sensitive tokens
$encryptedAccess = encryptData($accessToken);

// Save or update Google integration connection logs
if ($refreshToken) {
    $encryptedRefresh = encryptData($refreshToken);
    $stmt = $db->prepare("
        INSERT INTO external_app_connections (user_id, provider, email, access_token, refresh_token, expires_at, status, updated_at)
        VALUES (?, 'google', ?, ?, ?, ?, 'connected', NOW())
        ON DUPLICATE KEY UPDATE 
            email = VALUES(email),
            access_token = VALUES(access_token),
            refresh_token = VALUES(refresh_token),
            expires_at = VALUES(expires_at),
            status = 'connected',
            updated_at = NOW()
    ");
    $stmt->execute([$userId, $googleEmail, $encryptedAccess, $encryptedRefresh, $expiresAt]);
} else {
    // If user already authenticated once, Google may omit the refresh token in subsequent exchanges.
    // Try to update existing access token and keep previous refresh token.
    $stmt = $db->prepare("
        INSERT INTO external_app_connections (user_id, provider, email, access_token, expires_at, status, updated_at)
        VALUES (?, 'google', ?, ?, ?, 'connected', NOW())
        ON DUPLICATE KEY UPDATE 
            email = VALUES(email),
            access_token = VALUES(access_token),
            expires_at = VALUES(expires_at),
            status = 'connected',
            updated_at = NOW()
    ");
    $stmt->execute([$userId, $googleEmail, $encryptedAccess, $expiresAt]);
}

// Log connection timeline details
$timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, activity_type, description) VALUES (?, 'Integration Connected', ?)");
$timelineStmt->execute([$userId, "Connected Google external applications account ($googleEmail) successfully."]);

// 6. Redirect back to External Apps marketplace view in CRM
header("Location: {$protocol}://{$host}/dashboard/index.html#/external-apps");
exit;
