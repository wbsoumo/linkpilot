<?php
// backend/api/admin/save_payment_settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Require Admin Privilege
$user = JWTHelper::requireAdmin();

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input.', [], 400);
}

$isEnabled = isset($input['is_enabled']) ? (int)$input['is_enabled'] : 0;
$testMode = isset($input['test_mode']) ? (int)$input['test_mode'] : 1;
$keyId = trim($input['key_id'] ?? '');
$secretKey = trim($input['secret_key'] ?? '');
$webhookSecret = trim($input['webhook_secret'] ?? '');
$currency = trim($input['currency'] ?? 'INR');
$successUrl = trim($input['success_url'] ?? 'recharge.html?status=success');
$failureUrl = trim($input['failure_url'] ?? 'recharge.html?status=failure');

$db = Database::getConnection();

try {
    // Check if configuration exists
    $stmtCheck = $db->query("SELECT COUNT(*) FROM payment_provider_settings WHERE provider_name = 'razorpay'");
    $exists = (int)$stmtCheck->fetchColumn() > 0;

    if (!$exists) {
        // Insert empty base row
        $db->exec("INSERT INTO payment_provider_settings (provider_name, is_enabled) VALUES ('razorpay', 0)");
    }

    $sql = "UPDATE payment_provider_settings SET 
            is_enabled = :is_enabled, 
            test_mode = :test_mode,
            currency = :currency,
            success_url = :success_url,
            failure_url = :failure_url";

    $params = [
        'is_enabled' => $isEnabled,
        'test_mode' => $testMode,
        'currency' => $currency,
        'success_url' => $successUrl,
        'failure_url' => $failureUrl
    ];

    // Conditionally update credentials if new keys are provided
    if ($keyId !== '' && $keyId !== '••••••••') {
        $sql .= ", key_id = :key_id";
        $params['key_id'] = encryptData($keyId);
    }
    if ($secretKey !== '' && $secretKey !== '••••••••') {
        $sql .= ", secret_key = :secret_key";
        $params['secret_key'] = encryptData($secretKey);
    }
    if ($webhookSecret !== '' && $webhookSecret !== '••••••••') {
        $sql .= ", webhook_secret = :webhook_secret";
        $params['webhook_secret'] = encryptData($webhookSecret);
    }

    $sql .= " WHERE provider_name = 'razorpay'";

    $stmtUpdate = $db->prepare($sql);
    $stmtUpdate->execute($params);

    logActivity($user['id'], "Updated Razorpay payment configurations settings.");

    sendJsonResponse('success', 'Razorpay payment settings updated successfully.');

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to update payment settings: ' . $e->getMessage(), [], 500);
}
