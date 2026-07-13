<?php
// backend/api/external_apps/zoom_callback.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../external_apps_helper.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code) || empty($state)) {
    die("Authorization callback error: Missing code or state parameters.");
}

$parts = explode('-', $state);
if (count($parts) < 2) {
    die("Authorization callback error: Invalid state format.");
}

$userId = (int)$parts[0];
$hash = $parts[1];
$from = $parts[2] ?? '';

$expectedHash = md5($userId . ENCRYPTION_KEY);
if ($hash !== $expectedHash) {
    die("Authorization callback error: Security validation mismatch.");
}

$db = Database::getConnection();
$creds = ExternalAppsHelper::getZoomCredentials();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . '/backend/api/external_apps/zoom_callback.php';

try {
    // 1. Exchange code for access & refresh token
    $authHeader = base64_encode($creds['client_id'] . ':' . $creds['client_secret']);
    
    $url = 'https://zoom.us/oauth/token';
    $body = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri
    ];
    $headers = [
        'Authorization' => 'Basic ' . $authHeader,
        'Content-Type' => 'application/x-www-form-urlencoded'
    ];

    $res = ExternalAppsHelper::makeCurlRequest($url, 'POST', $body, $headers);
    if ($res['code'] !== 200 || !isset($res['data']['access_token'])) {
        throw new Exception("Zoom Token Exchange failure (HTTP " . $res['code'] . "): " . $res['body']);
    }

    $accessToken = $res['data']['access_token'];
    $refreshToken = $res['data']['refresh_token'] ?? null;
    $expiresIn = $res['data']['expires_in'] ?? 3600;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

    // 2. Fetch Zoom User Profile details
    $profileUrl = 'https://api.zoom.us/v2/users/me';
    $profileHeaders = [
        'Authorization' => 'Bearer ' . $accessToken
    ];
    
    $profileRes = ExternalAppsHelper::makeCurlRequest($profileUrl, 'GET', null, $profileHeaders);
    if ($profileRes['code'] !== 200 || !isset($profileRes['data']['email'])) {
        throw new Exception("Failed to fetch Zoom profile details.");
    }

    $zoomEmail = $profileRes['data']['email'];
    $zoomName = ($profileRes['data']['first_name'] ?? '') . ' ' . ($profileRes['data']['last_name'] ?? '');
    $zoomName = trim($zoomName) ?: 'Zoom User';
    $avatar = $profileRes['data']['pic_url'] ?? null;

    $encryptedAccess = encryptData($accessToken);

    // 3. Save connection into database
    $stmt = $db->prepare("SELECT id, refresh_token FROM external_app_connections WHERE user_id = ? AND provider = 'zoom' LIMIT 1");
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();

    $finalRefreshToken = $refreshToken;
    if (empty($finalRefreshToken) && !empty($existing['refresh_token'])) {
        $finalRefreshToken = decryptData($existing['refresh_token']);
    }
    $encryptedRefresh = $finalRefreshToken ? encryptData($finalRefreshToken) : null;

    if ($existing) {
        $stmtUpdate = $db->prepare("
            UPDATE external_app_connections 
            SET email = ?, 
                connected_email = ?,
                connected_name = ?,
                avatar = ?,
                access_token = ?,
                refresh_token = COALESCE(?, refresh_token),
                expires_at = ?,
                status = 'connected',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $zoomEmail, 
            $zoomEmail, 
            $zoomName, 
            $avatar, 
            $encryptedAccess, 
            $encryptedRefresh, 
            $expiresAt, 
            $existing['id']
        ]);
    } else {
        $stmtInsert = $db->prepare("
            INSERT INTO external_app_connections 
                (user_id, provider, email, connected_email, connected_name, avatar, access_token, refresh_token, expires_at, status, updated_at)
            VALUES 
                (?, 'zoom', ?, ?, ?, ?, ?, ?, ?, 'connected', NOW())
        ");
        $stmtInsert->execute([
            $userId,
            $zoomEmail,
            $zoomEmail,
            $zoomName,
            $avatar,
            $encryptedAccess,
            $encryptedRefresh,
            $expiresAt
        ]);
    }

    // Log activity
    $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, activity_type, description) VALUES (?, 'Integration Connected', ?)");
    $timelineStmt->execute([$userId, "Connected Zoom external app integration ($zoomEmail)."]);

} catch (Exception $e) {
    error_log("Zoom OAuth callback exchange failure: " . $e->getMessage());
    die("Authorization callback error: " . $e->getMessage());
}

// Redirect back
if ($from === 'meetings') {
    header("Location: {$protocol}://{$host}/dashboard/index.html?zoom_connected=true#/meetings");
} else {
    header("Location: {$protocol}://{$host}/dashboard/index.html#/external-apps");
}
exit;
