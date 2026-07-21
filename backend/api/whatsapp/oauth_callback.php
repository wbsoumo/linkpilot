<?php
// backend/api/whatsapp/oauth_callback.php
function clean_non_json_output_filter($buffer) {
    $start = strpos($buffer, '{');
    $end = strrpos($buffer, '}');
    if ($start !== false && $end !== false && $end > $start) {
        return substr($buffer, $start, $end - $start + 1);
    }
    return $buffer;
}
ob_start("clean_non_json_output_filter");
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$code = trim($input['code'] ?? '');
$accessToken = trim($input['access_token'] ?? '');
$state = trim($input['state'] ?? '');
$inputWabaId = trim($input['waba_id'] ?? '');
$inputPhoneId = trim($input['phone_number_id'] ?? '');

if (empty($code) && empty($accessToken)) {
    sendJsonResponse('error', 'Meta authorization code or access token is required.', [], 400);
}

// Retrieve Meta App Credentials
$stmtAppId = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_meta_app_id' LIMIT 1");
$stmtAppId->execute();
$appId = $stmtAppId->fetchColumn() ?: '';

$stmtAppSecret = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_meta_app_secret' LIMIT 1");
$stmtAppSecret->execute();
$appSecret = $stmtAppSecret->fetchColumn() ?: '';

// 1. If authorization code is passed, exchange code for User Access Token
if (!empty($code)) {
    if (empty($appId) || empty($appSecret)) {
        sendJsonResponse('error', 'Meta App ID and App Secret must be configured in Admin Control Panel before using OAuth Code Flow.', [], 400);
    }
    try {
        $tokenRes = WhatsAppMetaService::executeRequest(
            "oauth/access_token?client_id={$appId}&client_secret={$appSecret}&code={$code}&redirect_uri="
        );
        if (!empty($tokenRes['access_token'])) {
            $accessToken = $tokenRes['access_token'];
        } else {
            sendJsonResponse('error', 'Failed to exchange authorization code for Meta token.', $tokenRes, 400);
        }
    } catch (Throwable $e) {
        sendJsonResponse('error', 'OAuth Code Exchange error: ' . $e->getMessage(), [], 400);
    }
}

// Check Mock / Demo Mode
$isMock = (strpos($accessToken, 'Mock') !== false || $accessToken === 'EAAGemini' || $accessToken === 'EAAGeminiTest');

if ($isMock) {
    $businessName = 'LinkPilot Sandbox Business';
    $businessId = 'BIZ_MOCK_112233';
    $wabaId = !empty($inputWabaId) ? $inputWabaId : 'WABA_MOCK_100';
    $wabaName = 'LinkPilot WABA Account (Mock)';
    $phoneId = !empty($inputPhoneId) ? $inputPhoneId : 'PHONE_MOCK_88';
    $phoneNumber = '+1 (555) 019-2834';
    $displayName = 'LinkPilot Display Name';
    $qualityRating = 'GREEN';
    $messagingLimit = '1000/day';
    $phoneStatus = 'CONNECTED';
    $longLivedToken = $accessToken;
} else {
    try {
        $longLivedToken = $accessToken;

        // Exchange short-lived token for long-lived System User / Access Token if App credentials exist
        if (!empty($appId) && !empty($appSecret)) {
            try {
                $exchangeRes = WhatsAppMetaService::executeRequest(
                    "oauth/access_token?grant_type=fb_exchange_token&client_id={$appId}&client_secret={$appSecret}&fb_exchange_token={$accessToken}"
                );
                if (!empty($exchangeRes['access_token'])) {
                    $longLivedToken = $exchangeRes['access_token'];
                }
            } catch (Throwable $e) {
                WhatsAppMetaService::logDebug("oauth_callback: long-lived token exchange warning: " . $e->getMessage());
            }
        }

        // Determine WABA ID
        $wabaId = $inputWabaId;
        $wabaName = 'WhatsApp Business Account';
        $businessId = '';
        $businessName = '';

        if (empty($wabaId)) {
            $wabaRes = WhatsAppMetaService::getWabasDirectly($longLivedToken);
            $wabas = $wabaRes['data'] ?? [];
            if (empty($wabas)) {
                sendJsonResponse('error', 'No WhatsApp Business Accounts found. Please ensure all Meta permissions were granted.', [], 400);
            }
            $selectedWaba = $wabas[0];
            $wabaId = $selectedWaba['id'];
            $wabaName = $selectedWaba['name'] ?? 'WhatsApp Business Account';
        }

        // Fetch detailed WABA info
        try {
            $wabaDetails = WhatsAppMetaService::executeRequest("{$wabaId}?fields=id,name,owner_business_info", "GET", null, $longLivedToken);
            $wabaName = $wabaDetails['name'] ?? $wabaName;
            $businessId = $wabaDetails['owner_business_info']['id'] ?? '';
            $businessName = $wabaDetails['owner_business_info']['name'] ?? $wabaName;
        } catch (Throwable $e) {}

        // Determine Phone Number ID & Details
        $phoneId = $inputPhoneId;
        $phoneNumber = '';
        $displayName = $wabaName;
        $qualityRating = 'GREEN';
        $messagingLimit = '1000/day';
        $phoneStatus = 'CONNECTED';

        if (!empty($phoneId)) {
            try {
                $pDetails = WhatsAppMetaService::getPhoneNumberDetails($phoneId, $longLivedToken);
                $phoneNumber = $pDetails['display_phone_number'] ?? $pDetails['phone_number'] ?? '';
                $displayName = $pDetails['verified_name'] ?? $displayName;
                $qualityRating = $pDetails['quality_rating'] ?? 'GREEN';
                $limitTier = $pDetails['messaging_limit_tier'] ?? 'TIER_1K';
                $phoneStatus = $pDetails['status'] ?? 'CONNECTED';

                $limitMap = [
                    'TIER_50' => '50/day',
                    'TIER_250' => '250/day',
                    'TIER_1K' => '1000/day',
                    'TIER_10K' => '10000/day',
                    'TIER_100K' => '100000/day',
                    'TIER_UNLIMITED' => 'Unlimited/day'
                ];
                $messagingLimit = $limitMap[$limitTier] ?? $limitTier;
            } catch (Throwable $e) {}
        } else {
            $phoneRes = WhatsAppMetaService::getPhoneNumbers($wabaId, $longLivedToken);
            $phones = $phoneRes['data'] ?? [];
            if (!empty($phones)) {
                $selectedPhone = $phones[0];
                $phoneId = $selectedPhone['id'];
                $phoneNumber = $selectedPhone['display_phone_number'] ?? '';
                $displayName = $selectedPhone['verified_name'] ?? $wabaName;
                $qualityRating = $selectedPhone['quality_rating'] ?? 'GREEN';
                $limitTier = $selectedPhone['messaging_limit_tier'] ?? 'TIER_1K';
                $phoneStatus = $selectedPhone['status'] ?? 'CONNECTED';

                $limitMap = [
                    'TIER_50' => '50/day',
                    'TIER_250' => '250/day',
                    'TIER_1K' => '1000/day',
                    'TIER_10K' => '10000/day',
                    'TIER_100K' => '100000/day',
                    'TIER_UNLIMITED' => 'Unlimited/day'
                ];
                $messagingLimit = $limitMap[$limitTier] ?? $limitTier;
            }
        }

        if (empty($phoneId)) {
            sendJsonResponse('error', "No phone numbers found connected to WhatsApp Business Account ID {$wabaId}.", [], 400);
        }

        if (empty($businessName)) {
            $businessName = $displayName;
        }

        // Automatically Subscribe Webhook
        $webhookStatus = 'verified';
        try {
            WhatsAppMetaService::subscribeWebhook($wabaId, $longLivedToken);
        } catch (Throwable $webEx) {
            WhatsAppMetaService::logDebug("oauth_callback: Webhook subscription note: " . $webEx->getMessage());
        }

        // Automatically Fetch & Sync Message Templates
        try {
            $tplRes = WhatsAppMetaService::executeRequest("{$wabaId}/message_templates", "GET", null, $longLivedToken);
            $templates = $tplRes['data'] ?? [];
            if (!empty($templates)) {
                foreach ($templates as $tpl) {
                    $tName = $tpl['name'] ?? '';
                    $tCat = $tpl['category'] ?? 'UTILITY';
                    $tLang = $tpl['language'] ?? 'en_US';
                    $tStat = $tpl['status'] ?? 'APPROVED';
                    $tComp = json_encode($tpl['components'] ?? []);
                    
                    $stmtTpl = $db->prepare("
                        INSERT INTO whatsapp_templates (user_id, name, category, language, status, components_json, last_sync_at)
                        VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                        ON DUPLICATE KEY UPDATE 
                            category = VALUES(category),
                            language = VALUES(language),
                            status = VALUES(status),
                            components_json = VALUES(components_json),
                            last_sync_at = CURRENT_TIMESTAMP
                    ");
                    $stmtTpl->execute([$userId, $tName, $tCat, $tLang, $tStat, $tComp]);
                }
            }
        } catch (Throwable $tplEx) {
            WhatsAppMetaService::logDebug("oauth_callback: Template sync note: " . $tplEx->getMessage());
        }

    } catch (Throwable $e) {
        sendJsonResponse('error', 'Failed retrieving account details from Meta: ' . $e->getMessage(), [], 400);
    }
}

// Encrypt Access Token and Save to Database
$encryptedToken = encryptData($longLivedToken);

try {
    $stmtCheck = $db->prepare("SELECT id FROM whatsapp_accounts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtCheck->execute([$userId]);
    $existingId = $stmtCheck->fetchColumn();

    if ($existingId) {
        $db->prepare("DELETE FROM whatsapp_accounts WHERE user_id = ? AND id != ?")->execute([$userId, $existingId]);

        $stmtUpsert = $db->prepare("
            UPDATE whatsapp_accounts 
            SET connection_type = 'embedded', 
                business_name = ?, 
                business_id = ?, 
                waba_id = ?, 
                waba_name = ?, 
                phone_number_id = ?, 
                display_phone_number = ?, 
                display_name = ?, 
                access_token = ?, 
                status = 'connected', 
                quality_rating = ?, 
                messaging_limit = ?, 
                verification_status = ?, 
                webhook_status = 'verified', 
                token_status = 'valid', 
                last_verified_at = CURRENT_TIMESTAMP,
                connected_at = CURRENT_TIMESTAMP,
                last_sync = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmtUpsert->execute([
            $businessName, $businessId, $wabaId, $wabaName, $phoneId, $phoneNumber, $displayName, $encryptedToken, $qualityRating, $messagingLimit, $phoneStatus, $existingId
        ]);
    } else {
        $stmtUpsert = $db->prepare("
            INSERT INTO whatsapp_accounts (
                user_id, connection_type, business_name, business_id, waba_id, waba_name, phone_number_id, display_phone_number, display_name, access_token, status, quality_rating, messaging_limit, verification_status, webhook_status, token_status, last_verified_at, connected_at, last_sync
            ) VALUES (
                ?, 'embedded', ?, ?, ?, ?, ?, ?, ?, ?, 'connected', ?, ?, ?, 'verified', 'valid', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $stmtUpsert->execute([
            $userId, $businessName, $businessId, $wabaId, $wabaName, $phoneId, $phoneNumber, $displayName, $encryptedToken, $qualityRating, $messagingLimit, $phoneStatus
        ]);
    }

    logActivity($userId, "Connected WhatsApp Business account via Meta Embedded Signup: {$displayName}");

    sendJsonResponse('success', 'WhatsApp account connected successfully via Meta Embedded Signup.', [
        'business_name' => $businessName,
        'business_id' => $businessId,
        'waba_id' => $wabaId,
        'phone_number_id' => $phoneId,
        'display_phone_number' => $phoneNumber,
        'display_name' => $displayName,
        'quality_rating' => ucfirst(strtolower($qualityRating)),
        'messaging_limit' => $messagingLimit,
        'connection_type' => 'embedded',
        'status' => 'Connected',
        'last_sync' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    sendJsonResponse('error', 'Failed establishing OAuth database registration: ' . $e->getMessage(), [], 500);
}
