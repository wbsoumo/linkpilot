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
if (count($parts) < 2) {
    die("Authorization callback error: Invalid state format.");
}

$userId = (int)$parts[0];
$hash = $parts[1];
$type = $parts[2] ?? 'login';

$expectedHash = md5($userId . ENCRYPTION_KEY);

if ($hash !== $expectedHash) {
    die("Authorization callback error: Security validation mismatch.");
}

$db = Database::getConnection();

// Fetch admin OAuth credentials
$creds = ExternalAppsHelper::getGoogleCredentials();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . '/backend/api/external_apps/callback.php';

if (!class_exists('Google\Client')) {
    die("Authorization callback error: Google API Client library is not installed on your server. Please run 'composer install' in the 'backend' directory on your server to install dependencies.");
}

try {
    // 2. Initialize official Google client
    $client = new Google\Client();
    $client->setClientId($creds['client_id']);
    $client->setClientSecret($creds['client_secret']);
    $client->setRedirectUri($redirectUri);
    
    // 3. Exchange authorization code for token
    $token = $client->fetchAccessTokenWithAuthCode($code);
    if (isset($token['error'])) {
        throw new Exception("Google Token Exchange failure: " . ($token['error_description'] ?? $token['error']));
    }
    
    // Check if the user checked the permission boxes for the requested scope
    $grantedScopesStr = $token['scope'] ?? '';
    $grantedScopesList = array_map('trim', explode(' ', $grantedScopesStr));
    
    if ($type === 'gmail') {
        $hasGmailScope = false;
        foreach ($grantedScopesList as $gs) {
            if ($gs === 'https://www.googleapis.com/auth/gmail.modify' || $gs === 'https://mail.google.com/') {
                $hasGmailScope = true;
                break;
            }
        }
        if (!$hasGmailScope) {
            $from = $parts[3] ?? '';
            $errParam = urlencode("Scope permission denied: You must check the Gmail permission box on the Google Sign-in screen to allow LinkPilot to access your emails.");
            if ($from === 'setup') {
                header("Location: {$protocol}://{$host}/dashboard/setup.html?google_error=" . $errParam);
            } elseif ($from === 'smtp') {
                header("Location: {$protocol}://{$host}/dashboard/smtp.html?google_error=" . $errParam);
            } else {
                header("Location: {$protocol}://{$host}/dashboard/index.html#/external-apps?google_error=" . $errParam);
            }
            exit;
        }
    }
    
    $accessToken = $token['access_token'];
    $refreshToken = $token['refresh_token'] ?? null;
    $expiresIn = $token['expires_in'] ?? 3600;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
    
    // 4. Fetch Google profile details using official service
    $client->setAccessToken($accessToken);
    $oauth2 = new Google\Service\Oauth2($client);
    $userInfo = $oauth2->userinfo->get();
    
    $googleUserId = $userInfo->getId();
    $googleEmail = $userInfo->getEmail();
    $googleName = $userInfo->getName();
    $avatar = $userInfo->getPicture();
    
    // 5. Encrypt sensitive tokens
    $encryptedAccess = encryptData($accessToken);
    
    // Retrieve existing connection to merge scopes and preserve refresh token
    $stmt = $db->prepare("SELECT id, refresh_token, scopes FROM external_app_connections WHERE user_id = ? AND provider = 'google' LIMIT 1");
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();
    
    // Scope tracking
    $newScopes = GoogleOAuthHelper::getScopes($type);
    $existingScopes = [];
    if (!empty($existing['scopes'])) {
        $existingScopes = explode(',', $existing['scopes']);
    }
    $mergedScopes = array_unique(array_merge($existingScopes, $newScopes));
    $scopesStr = implode(',', $mergedScopes);
    
    // Refresh token validation (never overwrite with empty)
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
                google_user_id = ?,
                access_token = ?,
                refresh_token = COALESCE(?, refresh_token),
                expires_at = ?,
                scopes = ?,
                status = 'connected',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $googleEmail, 
            $googleEmail, 
            $googleName, 
            $avatar, 
            $googleUserId, 
            $encryptedAccess, 
            $encryptedRefresh, 
            $expiresAt, 
            $scopesStr, 
            $existing['id']
        ]);
    } else {
        $stmtInsert = $db->prepare("
            INSERT INTO external_app_connections 
                (user_id, provider, email, connected_email, connected_name, avatar, google_user_id, access_token, refresh_token, expires_at, scopes, status, updated_at)
            VALUES 
                (?, 'google', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'connected', NOW())
        ");
        $stmtInsert->execute([
            $userId,
            $googleEmail,
            $googleEmail,
            $googleName,
            $avatar,
            $googleUserId,
            $encryptedAccess,
            $encryptedRefresh,
            $expiresAt,
            $scopesStr
        ]);
    }
    
    // Auto-create/activate email intelligence settings for Gmail Sync!
    if (strpos($scopesStr, 'gmail.modify') !== false) {
        $stmtSettingsCheck = $db->prepare("SELECT id FROM email_intelligence_settings WHERE user_id = ?");
        $stmtSettingsCheck->execute([$userId]);
        if (!$stmtSettingsCheck->fetch()) {
            $permissionsJson = json_encode([
                'read_emails' => true,
                'read_attachments' => true,
                'store_metadata' => true,
                'ai_processing' => true,
                'auto_sync' => true,
                'background_processing' => true
            ]);
            $stmtInsertSettings = $db->prepare("
                INSERT INTO email_intelligence_settings 
                    (user_id, is_active, sync_interval_minutes, business_type, industry, permissions_json)
                VALUES 
                    (?, 1, 60, 'Software Company', 'Technology', ?)
            ");
            $stmtInsertSettings->execute([$userId, $permissionsJson]);
        } else {
            $stmtUpdateSettings = $db->prepare("UPDATE email_intelligence_settings SET is_active = 1 WHERE user_id = ?");
            $stmtUpdateSettings->execute([$userId]);
        }
    }
    
    // Log timeline activity
    $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, activity_type, description) VALUES (?, 'Integration Connected', ?)");
    $timelineStmt->execute([$userId, "Connected Google external application integrations: " . ucfirst($type) . " ($googleEmail)."]);
    
} catch (Exception $e) {
    error_log("Google OAuth callback exchange failure: " . $e->getMessage());
    die("Authorization callback error: " . $e->getMessage());
}

// Redirect back to origin source (e.g. setup, smtp settings) or fallback to marketplace
$from = $parts[3] ?? '';
if ($from === 'setup') {
    header("Location: {$protocol}://{$host}/dashboard/setup.html?google_connected=true");
} elseif ($from === 'smtp') {
    header("Location: {$protocol}://{$host}/dashboard/smtp.html?google_connected=true");
} else {
    header("Location: {$protocol}://{$host}/dashboard/index.html#/external-apps");
}
exit;
