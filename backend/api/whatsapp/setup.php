<?php
// backend/api/whatsapp/setup.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'GET') {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        // Retrieve connection status and business details
        $stmtAcc = $db->prepare("SELECT * FROM whatsapp_accounts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmtAcc->execute([$userId]);
        $account = $stmtAcc->fetch();
        
        $stmtSettings = $db->prepare("SELECT * FROM whatsapp_settings WHERE user_id = ? LIMIT 1");
        $stmtSettings->execute([$userId]);
        $settings = $stmtSettings->fetch();
        
        // Fetch global Meta App ID from admin_settings
        $stmtAppId = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_meta_app_id' LIMIT 1");
        $stmtAppId->execute();
        $metaAppId = $stmtAppId->fetchColumn() ?: '';
        
        // Diagnostics Log to backend/setup_debug.log
        $logInfo = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'account_present' => !empty($account),
            'db_status' => $account ? ($account['status'] ?? 'null') : 'no_row',
            'is_connected' => ($account && $account['status'] === 'connected')
        ];
        file_put_contents(__DIR__ . '/../../setup_debug.log', json_encode($logInfo) . "\n", FILE_APPEND);
        
        sendJsonResponse('success', 'WhatsApp connection details loaded.', [
            'connected' => ($account && $account['status'] === 'connected'),
            'account' => $account ?: null,
            'settings' => $settings ?: null,
            'meta_app_id' => $metaAppId
        ]);
    }
    
    elseif ($method === 'POST') {
        // Save business info (Step 1 of Wizard)
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $maxNumbers = 1;
        // Map numbers by user role/subscription
        if ($user['role'] === 'admin') {
            $maxNumbers = 999;
        }
        
        // Upsert whatsapp_settings
        $stmtSet = $db->prepare("
            INSERT INTO whatsapp_settings (user_id, max_numbers) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
        ");
        $stmtSet->execute([$userId, $maxNumbers]);
        
        sendJsonResponse('success', 'Business configuration saved successfully.');
    }
    
    elseif ($method === 'DISCOVER') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $code = trim($input['code'] ?? '');
        $accessToken = trim($input['access_token'] ?? '');
        $wabaId = trim($input['waba_id'] ?? '');
        
        $isMock = false;
        
        // 1. Resolve Code Exchange if present
        if (!empty($code)) {
            if (strpos($code, 'Mock') !== false || strpos($code, 'EAAGemini') !== false) {
                $accessToken = 'EAAGeminiMockToken' . time();
                $isMock = true;
            } else {
                $stmtAppId = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_meta_app_id' LIMIT 1");
                $stmtAppId->execute();
                $appId = $stmtAppId->fetchColumn();
                
                $stmtAppSec = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_meta_app_secret' LIMIT 1");
                $stmtAppSec->execute();
                $appSecret = $stmtAppSec->fetchColumn();
                
                if (empty($appId) || empty($appSecret)) {
                    $accessToken = 'EAAGeminiMockToken' . time();
                    $isMock = true;
                } else {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                    $host = $_SERVER['HTTP_HOST'] ?? 'linkpilot.work';
                    $redirectUri = $protocol . $host . "/dashboard/index.html";
                    
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
                        sendJsonResponse('error', "Meta Graph API returned: " . $msg, [], 400);
                    }
                    $accessToken = $tokenRes['access_token'];
                }
            }
        }
        
        if (empty($accessToken)) {
            sendJsonResponse('error', 'Access token or code is required.', [], 400);
        }
        
        $isMock = $isMock || (strpos($accessToken, 'Mock') !== false || strpos($accessToken, 'EAAGemini') !== false);
        
        if ($isMock) {
            // Simulated Mock Discovery
            $mockBusinesses = [
                ['id' => 'BIZ112731', 'name' => 'Taskbazi Corp']
            ];
            $mockWabas = [
                [
                    'id' => 'WABA718557',
                    'name' => 'Taskbazi Main Account',
                    'business_id' => 'BIZ112731',
                    'business_name' => 'Taskbazi Corp',
                    'status' => 'APPROVED'
                ]
            ];
            $mockPhones = [
                [
                    'id' => 'PHID441681',
                    'display_phone_number' => '+1 (555) 019-2834',
                    'verified_name' => 'taskbazi',
                    'quality_rating' => 'GREEN',
                    'messaging_limit_tier' => 'TIER_50',
                    'status' => 'APPROVED',
                    'code_verification_status' => 'VERIFIED'
                ]
            ];
            
            sendJsonResponse('success', 'Mock assets discovered.', [
                'access_token' => $accessToken,
                'businesses' => $mockBusinesses,
                'wabas' => $mockWabas,
                'phones' => empty($wabaId) ? [] : $mockPhones,
                'is_mock' => true
            ]);
        }
        
        try {
            // A. Validate permissions
            $perms = WhatsAppMetaService::getTokenPermissions($accessToken);
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
                    sendJsonResponse('error', "Missing required permission: {$r}.", [], 400);
                }
            }
            
            // B. Fetch Businesses
            $meData = WhatsAppMetaService::getUserBusinesses($accessToken);
            $businesses = $meData['businesses']['data'] ?? [];
            if (empty($businesses)) {
                sendJsonResponse('error', 'No Business Manager found on this Facebook account.', [], 400);
            }
            
            // C. Fetch WABAs for all businesses
            $wabas = [];
            foreach ($businesses as $biz) {
                $bizId = $biz['id'];
                $bizName = $biz['name'];
                try {
                    $wabaData = WhatsAppMetaService::getOwnedWabas($bizId, $accessToken);
                    if (!empty($wabaData['data'])) {
                        foreach ($wabaData['data'] as $w) {
                            $wabas[] = [
                                'id' => $w['id'],
                                'name' => $w['name'] ?? 'WhatsApp Business Account',
                                'business_id' => $bizId,
                                'business_name' => $bizName,
                                'status' => $w['status'] ?? 'unknown'
                            ];
                        }
                    }
                } catch (Exception $wEx) {
                    // Ignore single business errors
                }
            }
            
            if (empty($wabas)) {
                sendJsonResponse('error', 'No WhatsApp Business Account attached to your Business Manager.', [], 400);
            }
            
            // D. Fetch Phone Numbers if WABA ID is specified
            $phones = [];
            if (!empty($wabaId)) {
                $phoneData = WhatsAppMetaService::getPhoneNumbers($wabaId, $accessToken);
                if (!empty($phoneData['data'])) {
                    foreach ($phoneData['data'] as $p) {
                        $phones[] = [
                            'id' => $p['id'],
                            'display_phone_number' => $p['display_phone_number'] ?? '',
                            'verified_name' => $p['verified_name'] ?? '',
                            'quality_rating' => $p['quality_rating'] ?? 'unknown',
                            'messaging_limit_tier' => $p['messaging_limit_tier'] ?? 'TIER_50',
                            'status' => $p['status'] ?? 'unknown',
                            'code_verification_status' => $p['code_verification_status'] ?? 'NOT_VERIFIED'
                        ];
                    }
                }
            }
            
            sendJsonResponse('success', 'Meta assets discovered successfully.', [
                'access_token' => $accessToken,
                'businesses' => $businesses,
                'wabas' => $wabas,
                'phones' => $phones,
                'is_mock' => false
            ]);
            
        } catch (Exception $e) {
            sendJsonResponse('error', 'Meta Graph API returned: ' . $e->getMessage(), [], 400);
        }
    }
    
    elseif ($method === 'VALIDATE_AND_SAVE') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $accessToken = trim($input['access_token'] ?? '');
        $businessId = trim($input['business_id'] ?? '');
        $businessName = trim($input['business_name'] ?? '');
        $wabaId = trim($input['waba_id'] ?? '');
        $wabaName = trim($input['waba_name'] ?? '');
        $phoneNumberId = trim($input['phone_number_id'] ?? '');
        
        if (empty($accessToken) || empty($wabaId) || empty($phoneNumberId)) {
            sendJsonResponse('error', 'Missing access_token, waba_id, or phone_number_id parameters.', [], 400);
        }
        
        $isMock = (strpos($accessToken, 'Mock') !== false || strpos($accessToken, 'EAAGemini') !== false);
        
        $displayNo = '';
        $displayName = '';
        $qualityRating = 'unknown';
        $limitTier = 'TIER_50';
        
        if (!$isMock) {
            try {
                // 1. Detailed phone checks (Verification status & Cloud API check)
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
                
                // 2. Subscribe Webhook dynamically
                try {
                    WhatsAppMetaService::subscribeWebhook($wabaId, $accessToken);
                } catch (Exception $webEx) {
                    // Ignore webhook config warnings
                }
                
                // 3. Test Connection with a direct validation API request
                WhatsAppMetaService::getBusinessProfile($phoneNumberId, $accessToken);
                
            } catch (Exception $e) {
                sendJsonResponse('error', 'Meta Graph API returned: ' . $e->getMessage(), [], 400);
            }
        } else {
            $displayName = $displayName ?: 'Taskbazi';
            $displayNo = '+91 80162 22991';
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
        
        // Encrypt the Access Token
        $encryptedToken = encryptData($accessToken);
        
        // Check if account row already exists to avoid duplicates (fetch latest)
        $stmtCheck = $db->prepare("SELECT id FROM whatsapp_accounts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmtCheck->execute([$userId]);
        $existingId = $stmtCheck->fetchColumn();
        
        if ($existingId) {
            // Delete other older duplicate rows to ensure database consistency
            $db->prepare("DELETE FROM whatsapp_accounts WHERE user_id = ? AND id != ?")->execute([$userId, $existingId]);
            
            // Update existing connection row
            $stmtUpsert = $db->prepare("
                UPDATE whatsapp_accounts 
                SET business_name = ?, business_id = ?, waba_id = ?, phone_number_id = ?, display_phone_number = ?, access_token = ?, status = 'connected', quality_rating = ?, messaging_limit = ?
                WHERE id = ?
            ");
            $stmtUpsert->execute([
                $displayName, $businessId, $wabaId, $phoneNumberId, $displayNo, $encryptedToken, $qualityRating, $messagingLimitText, $existingId
            ]);
        } else {
            // Insert new connection row
            $stmtUpsert = $db->prepare("
                INSERT INTO whatsapp_accounts (user_id, business_name, business_id, waba_id, phone_number_id, display_phone_number, access_token, status, quality_rating, messaging_limit)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'connected', ?, ?)
            ");
            $stmtUpsert->execute([
                $userId, $displayName, $businessId, $wabaId, $phoneNumberId, $displayNo, $encryptedToken, $qualityRating, $messagingLimitText
            ]);
        }
        
        // Log Activity
        logActivity($userId, "Connected WhatsApp Business account: {$displayName}");
        
        sendJsonResponse('success', 'WhatsApp connection established.', [
            'business_name' => $displayName,
            'waba_name' => $wabaName,
            'phone_number' => $displayNo,
            'messaging_limit' => $messagingLimitText,
            'quality_rating' => ucfirst(strtolower($qualityRating)),
            'status' => 'Connected'
        ]);
    }
    
    elseif ($method === 'DISCONNECT') {
        // Disconnect account connection
        $db->prepare("UPDATE whatsapp_accounts SET status = 'disconnected', access_token = NULL WHERE user_id = ?")
           ->execute([$userId]);
        
        logActivity($userId, "Disconnected WhatsApp Business account");
        
        sendJsonResponse('success', 'WhatsApp account disconnected.');
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Connection setup failed: ' . $e->getMessage(), [], 500);
}
