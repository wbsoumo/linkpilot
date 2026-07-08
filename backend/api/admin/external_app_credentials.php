<?php
// backend/api/admin/external_app_credentials.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../external_apps_helper.php';

// Require Admin Auth
$user = JWTHelper::requireAdmin();
$userId = $user['id'];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$db = Database::getConnection();

try {
    if ($method === 'GET') {
        $creds = ExternalAppsHelper::getGoogleCredentials();
        $stmt = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute(['google_sheets_client_id']);
        $creds['sheets_client_id'] = trim($stmt->fetchColumn() ?: '');
        $stmt->execute(['google_sheets_client_secret']);
        $creds['sheets_client_secret'] = trim($stmt->fetchColumn() ?: '');
        sendJsonResponse('success', 'Google credentials fetched successfully.', ['credentials' => $creds]);
    }
    
    elseif ($method === 'POST') {
        if ($action === 'test') {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $clientId = trim($input['client_id'] ?? $input['google_external_client_id'] ?? '');
            $clientSecret = trim($input['client_secret'] ?? $input['google_external_client_secret'] ?? '');

            if (empty($clientId) || empty($clientSecret)) {
                sendJsonResponse('error', 'Google Client ID and Client Secret are required for verification.', [], 400);
            }

            if (!class_exists('Google\Client')) {
                sendJsonResponse('error', 'Google API Client library is not installed on your server. Please run "composer install" in the "backend" directory on your server to install dependencies.', [], 500);
            }

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $redirectUri = $protocol . '://' . $host . '/backend/api/external_apps/callback.php';

            try {
                // Initialize client and validate settings
                $client = new Google\Client();
                $client->setClientId($clientId);
                $client->setClientSecret($clientSecret);
                $client->setRedirectUri($redirectUri);
                
                // Test reachability of endpoints
                $discoveryUrl = "https://accounts.google.com/.well-known/openid-configuration";
                $res = ExternalAppsHelper::makeCurlRequest($discoveryUrl, 'GET');
                if ($res['code'] !== 200) {
                    throw new Exception("Google OpenID discovery endpoint is unreachable. HTTP Status: " . $res['code']);
                }

                sendJsonResponse('success', 'Google OAuth configuration validation successful. Developer settings and redirect paths match.');
            } catch (Exception $e) {
                sendJsonResponse('error', 'Google OAuth validation failed: ' . $e->getMessage());
            }
        } else {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $enabled = trim($input['google_external_enabled'] ?? '1');
            $clientId = trim($input['google_external_client_id'] ?? '');
            $clientSecret = trim($input['google_external_client_secret'] ?? '');
            $sheetsClientId = trim($input['google_sheets_client_id'] ?? '');
            $sheetsClientSecret = trim($input['google_sheets_client_secret'] ?? '');

            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            $stmt->execute(['google_external_enabled', $enabled]);
            $stmt->execute(['google_external_client_id', $clientId]);
            $stmt->execute(['google_external_client_secret', $clientSecret]);
            $stmt->execute(['google_sheets_client_id', $sheetsClientId]);
            $stmt->execute(['google_sheets_client_secret', $sheetsClientSecret]);

            logActivity($userId, "Admin saved Google developer credentials.");

            $db->commit();
            sendJsonResponse('success', 'Google credentials saved successfully.');
        }
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Admin credential action failed: ' . $e->getMessage(), [], 500);
}
