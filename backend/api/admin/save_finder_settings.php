<?php
// backend/api/admin/save_finder_settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Require Admin Privilege
$user = JWTHelper::requireAdmin();

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['providers'])) {
    sendJsonResponse('error', 'Invalid JSON input or missing providers payload.', [], 400);
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    foreach ($input['providers'] as $provData) {
        $name = trim($provData['name'] ?? '');
        $isEnabled = isset($provData['is_enabled']) ? (int)$provData['is_enabled'] : 0;
        $priority = isset($provData['priority']) ? (int)$provData['priority'] : 1;
        $apiKey = trim($provData['api_key'] ?? '');
        $apiSecret = trim($provData['api_secret'] ?? '');

        if (empty($name)) continue;

        // Fetch current values
        $stmtCurr = $db->prepare("SELECT api_key, api_secret FROM email_provider_settings WHERE provider_name = ? LIMIT 1");
        $stmtCurr->execute([$name]);
        $current = $stmtCurr->fetch();

        // Prepare updates
        $sql = "UPDATE email_provider_settings SET is_enabled = :is_enabled, priority = :priority";
        $params = [
            'is_enabled' => $isEnabled,
            'priority' => $priority,
            'provider_name' => $name
        ];

        // Only update API Key if it's new and not the masked placeholder
        if ($apiKey !== '' && $apiKey !== '••••••••') {
            $sql .= ", api_key = :api_key";
            $params['api_key'] = encryptData($apiKey);
        }

        // Only update API Secret if it's new and not the masked placeholder
        if ($apiSecret !== '' && $apiSecret !== '••••••••') {
            $sql .= ", api_secret = :api_secret";
            $params['api_secret'] = encryptData($apiSecret);
        }

        $sql .= " WHERE provider_name = :provider_name";

        $stmtUpdate = $db->prepare($sql);
        $stmtUpdate->execute($params);
    }

    $db->commit();

    logActivity($user['id'], "Updated Email Finder provider configurations settings.");

    sendJsonResponse('success', 'Email Finder provider settings updated successfully.');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Failed to save finder settings: ' . $e->getMessage(), [], 500);
}
