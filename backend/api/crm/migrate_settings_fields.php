<?php
// backend/api/crm/migrate_settings_fields.php

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

$db = Database::getConnection();

// Secure migrations: Require admin check if an admin user already exists.
$hasAdmin = false;
try {
    $stmtAdminCheck = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_verified = 1");
    if ($stmtAdminCheck && (int)$stmtAdminCheck->fetchColumn() > 0) {
        $hasAdmin = true;
    }
} catch (Exception $e) {
    // Normal if tables do not exist yet on fresh installations
}

if ($hasAdmin) {
    require_once __DIR__ . '/../../jwt_helper.php';
    JWTHelper::requireAdmin();
}

$messages = [];

try {
    $columns = [
        'business_address' => "VARCHAR(500) DEFAULT NULL",
        'tax_id' => "VARCHAR(100) DEFAULT NULL",
        'support_email' => "VARCHAR(255) DEFAULT NULL",
        'currency' => "VARCHAR(10) DEFAULT 'INR'",
        'timezone' => "VARCHAR(100) DEFAULT 'Asia/Kolkata'",
        'webhook_url' => "VARCHAR(500) DEFAULT NULL",
        'notification_leads' => "TINYINT(1) DEFAULT 1",
        'notification_tasks' => "TINYINT(1) DEFAULT 1",
        'notification_digest' => "TINYINT(1) DEFAULT 0",
        'notification_errors' => "TINYINT(1) DEFAULT 1",
        'two_factor_enabled' => "TINYINT(1) DEFAULT 0",
        'email_open_tracking' => "TINYINT(1) DEFAULT 1"
    ];

    foreach ($columns as $col => $def) {
        $stmt = $db->query("SHOW COLUMNS FROM `user_profiles` LIKE '{$col}'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `user_profiles` ADD COLUMN `{$col}` {$def}");
            $messages[] = "Added column '{$col}' to 'user_profiles'.";
        } else {
            $messages[] = "Column '{$col}' already exists in 'user_profiles'.";
        }
    }

    sendJsonResponse('success', 'Settings database migrations executed successfully.', [
        'details' => $messages
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Migration failed: ' . $e->getMessage(), [
        'details' => $messages
    ], 500);
}
