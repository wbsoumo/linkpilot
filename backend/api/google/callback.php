<?php
// backend/api/google/callback.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../google_sheets_helper.php';

// Parse query params
$code = trim($_GET['code'] ?? '');
$stateEncoded = trim($_GET['state'] ?? '');
$error = trim($_GET['error'] ?? '');

if ($error !== '') {
    header("Location: ../../../dashboard/index.html?google_sheets_error=" . urlencode($error));
    exit;
}

if ($code === '' || $stateEncoded === '') {
    header("Location: ../../../dashboard/index.html?google_sheets_error=missing_parameters");
    exit;
}

try {
    // 1. Decode state to verify user context securely
    $parts = explode('-', $stateEncoded);
    if (count($parts) !== 2) {
        throw new Exception("Invalid state format.");
    }
    $userId = (int)$parts[0];
    $hash = $parts[1];
    
    if ($hash !== md5($userId . ENCRYPTION_KEY)) {
        throw new Exception("State verification failed.");
    }
    
    // 2. Exchange authorization code for tokens
    $creds = GoogleSheetsHelper::getClientCredentials();
    
    $ch = curl_init('https://oauth2.googleapis.com/token');
    $payload = [
        'code' => $code,
        'client_id' => $creds['client_id'],
        'client_secret' => $creds['client_secret'],
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $tokenData = json_decode($response, true);
    
    if ($httpCode !== 200 || !isset($tokenData['access_token'])) {
        throw new Exception("Token exchange failed: " . ($tokenData['error_description'] ?? $response));
    }
    
    $accessToken = $tokenData['access_token'];
    $refreshToken = $tokenData['refresh_token'] ?? null; // Null if user didn't consent again
    $expiresIn = $tokenData['expires_in'];
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
    
    // 3. Fetch user profile from Google to get email/name details
    $chProfile = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($chProfile, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chProfile, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Accept: application/json"
    ]);
    curl_setopt($chProfile, CURLOPT_SSL_VERIFYPEER, true);
    
    $profileRes = curl_exec($chProfile);
    $profileHttpCode = curl_getinfo($chProfile, CURLINFO_HTTP_CODE);
    curl_close($chProfile);
    
    $profileData = json_decode($profileRes, true);
    
    if ($profileHttpCode !== 200 || !isset($profileData['email'])) {
        throw new Exception("Profile fetch failed: " . $profileRes);
    }
    
    $googleEmail = $profileData['email'];
    $googleName = $profileData['name'] ?? '';
    
    // 4. Save/Update connection record in database
    $db = Database::getConnection();
    
    // Encrypt sensitive tokens
    $encryptedAccess = encryptData($accessToken);
    
    // Check if connection already exists
    $stmtCheck = $db->prepare("SELECT id, refresh_token FROM google_sheet_connections WHERE user_id = ?");
    $stmtCheck->execute([$userId]);
    $existing = $stmtCheck->fetch();
    
    if ($existing) {
        // If Google did not return a new refresh token (e.g. user already consented previously),
        // we preserve the old refresh token from database.
        $encryptedRefresh = $refreshToken ? encryptData($refreshToken) : $existing['refresh_token'];
        
        $stmtUpdate = $db->prepare("
            UPDATE google_sheet_connections 
            SET google_email = ?, google_name = ?, access_token = ?, refresh_token = ?, expires_at = ?, sync_enabled = 1
            WHERE user_id = ?
        ");
        $stmtUpdate->execute([$googleEmail, $googleName, $encryptedAccess, $encryptedRefresh, $expiresAt, $userId]);
    } else {
        if (!$refreshToken) {
            throw new Exception("No refresh token returned by Google. Please disconnect and connect again.");
        }
        $encryptedRefresh = encryptData($refreshToken);
        
        $stmtInsert = $db->prepare("
            INSERT INTO google_sheet_connections 
            (user_id, google_email, google_name, access_token, refresh_token, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([$userId, $googleEmail, $googleName, $encryptedAccess, $encryptedRefresh, $expiresAt]);
    }
    
    // Log Activity
    logActivity($userId, "Connected Google Account: " . $googleEmail);
    
    // 5. Success redirect to frontend dashboard page
    header("Location: ../../../dashboard/index.html?google_sheets=connected");
    exit;

} catch (Exception $e) {
    error_log("Google OAuth Callback Exception: " . $e->getMessage());
    header("Location: ../../../dashboard/index.html?google_sheets_error=" . urlencode($e->getMessage()));
    exit;
}
