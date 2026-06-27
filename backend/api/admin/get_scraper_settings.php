<?php
// backend/api/admin/get_scraper_settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Require Admin Privilege
$user = JWTHelper::requireAdmin();

$db = Database::getConnection();

try {
    $stmt = $db->prepare("SELECT is_enabled, api_key, api_secret, priority FROM email_provider_settings WHERE provider_name = ? LIMIT 1");
    $stmt->execute(['linkedin_scraper']);
    $settings = $stmt->fetch();

    if (!$settings) {
        // Fallback seed
        $stmtSeed = $db->prepare("INSERT INTO email_provider_settings (provider_name, is_enabled, api_key, api_secret, priority) VALUES ('linkedin_scraper', 0, 'http://localhost:8000', '15', 0)");
        $stmtSeed->execute();
        
        $settings = [
            'is_enabled' => 0,
            'api_key' => 'http://localhost:8000',
            'api_secret' => '15',
            'priority' => 0
        ];
    }

    sendJsonResponse('success', 'LinkedIn Scraper settings retrieved.', [
        'is_enabled' => (int)$settings['is_enabled'],
        'api_url' => $settings['api_key'],
        'timeout' => (int)$settings['api_secret'],
        'debug_logging' => (int)$settings['priority']
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to retrieve scraper settings: ' . $e->getMessage(), [], 500);
}
