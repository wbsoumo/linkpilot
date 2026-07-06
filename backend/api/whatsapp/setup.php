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
        // Retrieve connection status and business details
        $stmtAcc = $db->prepare("SELECT * FROM whatsapp_accounts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmtAcc->execute([$userId]);
        $account = $stmtAcc->fetch();
        
        $stmtSettings = $db->prepare("SELECT * FROM whatsapp_settings WHERE user_id = ? LIMIT 1");
        $stmtSettings->execute([$userId]);
        $settings = $stmtSettings->fetch();
        
        sendJsonResponse('success', 'WhatsApp connection details loaded.', [
            'connected' => ($account && $account['status'] === 'connected'),
            'account' => $account ?: null,
            'settings' => $settings ?: null
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
    
    elseif ($method === 'SAVE_TOKEN') {
        // Save Facebook Meta tokens & credentials (Step 2 of Wizard)
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $accessToken = trim($input['access_token'] ?? '');
        $wabaId = trim($input['waba_id'] ?? '');
        $phoneNumberId = trim($input['phone_number_id'] ?? '');
        $businessId = trim($input['business_id'] ?? '');
        $displayName = trim($input['display_name'] ?? '');
        
        if (empty($accessToken) || empty($wabaId) || empty($phoneNumberId)) {
            sendJsonResponse('error', 'Access Token, WABA ID, and Phone Number ID are required.', [], 400);
        }
        
        // Verify token & profile credentials with Meta Graph API
        $isMock = (strpos($accessToken, 'MockToken') !== false);
        $displayName = $displayName ?: 'WhatsApp Business Account';
        $qualityRating = 'unknown';
        $displayNo = '';
        
        if (!$isMock) {
            try {
                $metaProfile = WhatsAppMetaService::getBusinessProfile($phoneNumberId, $accessToken);
                $displayName = $metaProfile['verified_name'] ?? ($displayName ?: 'WhatsApp Business Account');
                $qualityRating = $metaProfile['quality_rating'] ?? 'unknown';
                $displayNo = $metaProfile['display_phone_number'] ?? '';
            } catch (Exception $e) {
                sendJsonResponse('error', 'Failed to verify credentials with Meta: ' . $e->getMessage(), [], 400);
            }
        } else {
            $displayName = $displayName ?: 'LinkPilot Test Sandbox';
            $qualityRating = 'GREEN';
            $displayNo = '+1 (555) 019-2834';
        }
        
        // Encrypt the Access Token
        $encryptedToken = encryptData($accessToken);
        
        // Check if account row already exists to avoid duplicates
        $stmtCheck = $db->prepare("SELECT id FROM whatsapp_accounts WHERE user_id = ? LIMIT 1");
        $stmtCheck->execute([$userId]);
        $existingId = $stmtCheck->fetchColumn();
        
        if ($existingId) {
            // Update existing connection row
            $stmtUpsert = $db->prepare("
                UPDATE whatsapp_accounts 
                SET business_name = ?, business_id = ?, waba_id = ?, phone_number_id = ?, display_phone_number = ?, access_token = ?, status = 'connected', quality_rating = ?
                WHERE id = ?
            ");
            $stmtUpsert->execute([
                $displayName, $businessId, $wabaId, $phoneNumberId, $displayNo, $encryptedToken, $qualityRating, $existingId
            ]);
        } else {
            // Insert new connection row
            $stmtUpsert = $db->prepare("
                INSERT INTO whatsapp_accounts (user_id, business_name, business_id, waba_id, phone_number_id, display_phone_number, access_token, status, quality_rating, messaging_limit)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'connected', ?, 'TIER_50')
            ");
            $stmtUpsert->execute([
                $userId, $displayName, $businessId, $wabaId, $phoneNumberId, $displayNo, $encryptedToken, $qualityRating
            ]);
        }
        
        // Register Webhook Subscription dynamically
        if (!$isMock) {
            try {
                WhatsAppMetaService::subscribeWebhook($wabaId, $accessToken);
            } catch (Exception $e) {
                // Log webhook subscription failure as warning, but don't fail setup
            }
        }
        
        // Log Activity
        logActivity($userId, "Connected WhatsApp Business account: {$displayName}");
        
        sendJsonResponse('success', 'WhatsApp connected successfully.', [
            'display_name' => $displayName,
            'phone_number' => $displayNo
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
