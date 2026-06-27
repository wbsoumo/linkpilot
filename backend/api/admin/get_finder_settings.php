<?php
// backend/api/admin/get_finder_settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Require Admin Privilege
$user = JWTHelper::requireAdmin();

$db = Database::getConnection();

try {
    // 1. Fetch Email Finder Settings
    $stmtProviders = $db->query("SELECT id, provider_name, is_enabled, priority, api_key, api_secret FROM email_provider_settings ORDER BY priority ASC");
    $providers = $stmtProviders->fetchAll();

    $cleanProviders = [];
    foreach ($providers as $prov) {
        $cleanProviders[] = [
            'id' => $prov['id'],
            'name' => $prov['provider_name'],
            'is_enabled' => (int)$prov['is_enabled'],
            'priority' => (int)$prov['priority'],
            'has_key' => !empty($prov['api_key']),
            'has_secret' => !empty($prov['api_secret'])
        ];
    }

    // 2. Fetch Razorpay Settings
    $stmtPay = $db->query("SELECT id, provider_name, is_enabled, test_mode, key_id, secret_key, webhook_secret, currency, success_url, failure_url FROM payment_provider_settings WHERE provider_name = 'razorpay' LIMIT 1");
    $pay = $stmtPay->fetch();

    $cleanPay = null;
    if ($pay) {
        $cleanPay = [
            'id' => $pay['id'],
            'name' => $pay['provider_name'],
            'is_enabled' => (int)$pay['is_enabled'],
            'test_mode' => (int)$pay['test_mode'],
            'currency' => $pay['currency'],
            'success_url' => $pay['success_url'],
            'failure_url' => $pay['failure_url'],
            'has_key_id' => !empty($pay['key_id']),
            'has_secret_key' => !empty($pay['secret_key']),
            'has_webhook_secret' => !empty($pay['webhook_secret'])
        ];
    }

    sendJsonResponse('success', 'Admin finder and payment settings loaded.', [
        'providers' => $cleanProviders,
        'razorpay' => $cleanPay
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to retrieve admin settings: ' . $e->getMessage(), [], 500);
}
