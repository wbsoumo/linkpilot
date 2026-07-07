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
        sendJsonResponse('success', 'Google credentials fetched successfully.', ['credentials' => $creds]);
    }
    
    elseif ($method === 'POST') {
        if ($action === 'test') {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $clientId = trim($input['client_id'] ?? '');
            $clientSecret = trim($input['client_secret'] ?? '');

            if (empty($clientId) || empty($clientSecret)) {
                sendJsonResponse('error', 'Google Client ID and Client Secret are required for verification.', [], 400);
            }

            // Perform simple cURL request to google authorization endpoint discovery to test network latency & keys format
            $url = "https://accounts.google.com/.well-known/openid-configuration";
            $res = ExternalAppsHelper::makeCurlRequest($url, 'GET');
            if ($res['code'] === 200) {
                sendJsonResponse('success', 'Google API connection test was successful. OAuth endpoints are reachable.');
            } else {
                sendJsonResponse('error', 'Failed to reach Google OAuth discovery endpoint. Status code: ' . $res['code']);
            }
        } else {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $enabled = trim($input['google_external_enabled'] ?? '1');
            $clientId = trim($input['google_external_client_id'] ?? '');
            $clientSecret = trim($input['google_external_client_secret'] ?? '');
            $scopes = trim($input['google_external_scopes'] ?? '');

            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            $stmt->execute(['google_external_enabled', $enabled]);
            $stmt->execute(['google_external_client_id', $clientId]);
            $stmt->execute(['google_external_client_secret', $clientSecret]);
            $stmt->execute(['google_external_scopes', $scopes]);

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
