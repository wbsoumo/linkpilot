<?php
// backend/api/whatsapp/exchange_code.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

header('Content-Type: application/json');

try {
    $user = JWTHelper::requireAuth();
    $userId = $user['id'];
    $db = Database::getConnection();

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'POST') {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $code = trim($input['code'] ?? '');

    if (empty($code)) {
        sendJsonResponse('error', 'Authorization code is required.', [], 400);
    }

    $isMock = (strpos($code, 'Mock') !== false || strpos($code, 'EAAGemini') !== false);
    $accessToken = '';
    $businessId = '';
    $businessName = '';
    $wabaId = '';
    $wabaName = '';
    $phoneNumberId = '';
    $displayNo = '';
    $displayName = '';
    $qualityRating = 'unknown';
    $limitTier = 'TIER_50';

    if (!$isMock) {
        // 1. Fetch App ID and App Secret
        $stmtAppId = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_meta_app_id' LIMIT 1");
        $stmtAppId->execute();
        $appId = $stmtAppId->fetchColumn();

        $stmtAppSec = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_meta_app_secret' LIMIT 1");
        $stmtAppSec->execute();
        $appSecret = $stmtAppSec->fetchColumn();

        if (empty($appId) || empty($appSecret)) {
            sendJsonResponse('error', 'Meta App credentials are not configured in admin settings.', [], 400);
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'linkpilot.work';
        $redirectUri = $protocol . $host . "/dashboard/index.html";

        // 2. Exchange authorization code for permanent user access token
        $url = "https://graph.facebook.com/v20.0/oauth/access_token?client_id=" . urlencode($appId) . "&client_secret=" . urlencode($appSecret) . "&code=" . urlencode($code) . "&redirect_uri=" . urlencode($redirectUri);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $tokenRes = json_decode($res, true);
        if ($httpCode !== 200 || empty($tokenRes['access_token'])) {
            $msg = $tokenRes['error']['message'] ?? 'Unable to exchange Meta authorization code.';
            sendJsonResponse('error', "Token exchange failed: " . $msg, [], 400);
        }
        $accessToken = $tokenRes['access_token'];

        // Development Logging
        error_log("[LinkPilot Dev] Token Exchange Response: " . json_encode($tokenRes));

        // 3. Verify permissions
        $perms = WhatsAppMetaService::getTokenPermissions($accessToken);
        error_log("[LinkPilot Dev] Token Permissions Response: " . json_encode($perms));

        $granted = [];
        if (!empty($perms['data'])) {
            foreach ($perms['data'] as $p) {
                if ($p['status'] === 'granted') {
                    $granted[$p['permission']] = true;
                }
            }
        }
        $required = ['business_management', 'whatsapp_business_management', 'whatsapp_business_messaging'];
        foreach ($required as $r) {
            if (empty($granted[$r])) {
                sendJsonResponse('error', "Permission denied: Missing required permission: {$r}.", [], 400);
            }
        }

        // 4. Retrieve Business Manager
        $meData = WhatsAppMetaService::getUserBusinesses($accessToken);
        error_log("[LinkPilot Dev] Business Discovery Response: " . json_encode($meData));

        $businesses = $meData['businesses']['data'] ?? [];
        if (empty($businesses)) {
            sendJsonResponse('error', 'No Business Manager found on this Facebook account.', [], 400);
        }
        $businessId = $businesses[0]['id'];
        $businessName = $businesses[0]['name'];

        // 5. Retrieve WhatsApp Business Account (WABA)
        $wabaRes = WhatsAppMetaService::executeRequest("me/whatsapp_business_accounts?fields=id,name,status", "GET", null, $accessToken);
        error_log("[LinkPilot Dev] WABA Discovery Response: " . json_encode($wabaRes));

        $wabas = $wabaRes['data'] ?? [];
        if (empty($wabas)) {
            sendJsonResponse('error', 'No WhatsApp Business Account found.', [], 400);
        }
        $wabaId = $wabas[0]['id'];
        $wabaName = $wabas[0]['name'] ?? 'WhatsApp Business Account';

        // 6. Retrieve Phone Number
        $phoneRes = WhatsAppMetaService::getPhoneNumbers($wabaId, $accessToken);
        error_log("[LinkPilot Dev] Phone Discovery Response: " . json_encode($phoneRes));

        $phones = $phoneRes['data'] ?? [];
        if (empty($phones)) {
            sendJsonResponse('error', 'No phone number found.', [], 400);
        }
        $phoneNumberId = $phones[0]['id'];

        // 7. Get detailed phone details to verify Cloud API active and status
        $phoneDetails = WhatsAppMetaService::getPhoneNumberDetails($phoneNumberId, $accessToken);
        $displayNo = $phoneDetails['display_phone_number'] ?? '';
        $displayName = $phoneDetails['verified_name'] ?? $wabaName;
        $qualityRating = $phoneDetails['quality_rating'] ?? 'unknown';
        $limitTier = $phoneDetails['messaging_limit_tier'] ?? 'TIER_50';
        $phoneStatus = $phoneDetails['status'] ?? '';
        $codeStatus = $phoneDetails['code_verification_status'] ?? '';

        if ($phoneStatus === 'PENDING') {
            sendJsonResponse('error', 'Phone number registration is pending with Meta.', [], 400);
        }

        if ($codeStatus !== 'VERIFIED') {
            sendJsonResponse('error', 'Phone number has not been verified or registered on Meta.', [], 400);
        }

        // 8. Subscribe Webhook dynamically
        try {
            WhatsAppMetaService::subscribeWebhook($wabaId, $accessToken);
        } catch (Exception $webEx) {
            // Log warning but don't fail onboarding
        }
    } else {
        // Mock simulation
        $accessToken = $code;
        $businessId = 'BIZ' . rand(100000, 999999);
        $businessName = 'Taskbazi Corp';
        $wabaId = 'WABA' . rand(100000, 999999);
        $wabaName = 'Taskbazi Main Account';
        $phoneNumberId = 'PHID' . rand(100000, 999999);
        $displayNo = '+91 80162 22991';
        $displayName = 'Taskbazi';
        $qualityRating = 'GREEN';
        $limitTier = 'TIER_1K';
    }

    $limitMap = [
        'TIER_50' => '50/day',
        'TIER_250' => '250/day',
        'TIER_1K' => '1000/day',
        'TIER_10K' => '10000/day',
        'TIER_100K' => '100000/day',
        'TIER_UNLIMITED' => 'Unlimited/day'
    ];
    $messagingLimitText = $limitMap[$limitTier] ?? $limitTier;

    // Encrypt token
    $encryptedToken = encryptData($accessToken);

    // Check if account row already exists to avoid duplicates
    $stmtCheck = $db->prepare("SELECT id FROM whatsapp_accounts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtCheck->execute([$userId]);
    $existingId = $stmtCheck->fetchColumn();

    if ($existingId) {
        $db->prepare("DELETE FROM whatsapp_accounts WHERE user_id = ? AND id != ?")->execute([$userId, $existingId]);

        $stmtUpsert = $db->prepare("
            UPDATE whatsapp_accounts 
            SET business_name = ?, business_id = ?, waba_id = ?, phone_number_id = ?, display_phone_number = ?, access_token = ?, status = 'connected', quality_rating = ?, messaging_limit = ?
            WHERE id = ?
        ");
        $stmtUpsert->execute([
            $displayName, $businessId, $wabaId, $phoneNumberId, $displayNo, $encryptedToken, $qualityRating, $messagingLimitText, $existingId
        ]);
    } else {
        $stmtUpsert = $db->prepare("
            INSERT INTO whatsapp_accounts (user_id, business_name, business_id, waba_id, phone_number_id, display_phone_number, access_token, status, quality_rating, messaging_limit)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'connected', ?, ?)
        ");
        $stmtUpsert->execute([
            $userId, $displayName, $businessId, $wabaId, $phoneNumberId, $displayNo, $encryptedToken, $qualityRating, $messagingLimitText
        ]);
    }

    logActivity($userId, "Connected WhatsApp Business account via Embedded Signup: {$displayName}");

    echo json_encode([
        'success' => true,
        'access_token' => $accessToken,
        'business_id' => $businessId,
        'business_name' => $businessName,
        'waba_id' => $wabaId,
        'waba_name' => $wabaName,
        'phone_number_id' => $phoneNumberId,
        'phone_number' => $displayNo,
        'display_name' => $displayName,
        'messaging_limit' => $messagingLimitText,
        'quality_rating' => ucfirst(strtolower($qualityRating)),
        'status' => 'Connected'
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Token exchange failed: ' . $e->getMessage(), [], 500);
}

function sendJsonResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'success' => $status === 'success',
        'status' => $status,
        'message' => $message
    ], $data));
    exit;
}
