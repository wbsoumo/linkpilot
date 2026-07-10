<?php
// backend/api/crm/migrate_external_apps.php

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

// Disable error display to avoid corrupting JSON output
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
    // 1. Create external_app_connections table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `external_app_connections` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `provider` VARCHAR(50) NOT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `access_token` TEXT DEFAULT NULL,
            `refresh_token` TEXT DEFAULT NULL,
            `expires_at` TIMESTAMP NULL DEFAULT NULL,
            `status` VARCHAR(50) DEFAULT 'connected',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_external_app_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            UNIQUE KEY `idx_user_provider` (`user_id`, `provider`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $messages[] = "Table 'external_app_connections' checked/created.";

    // 2. Alter crm_meetings to append google_event_id and meet_link
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `crm_meetings` LIKE 'google_event_id'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `crm_meetings` ADD COLUMN `google_event_id` VARCHAR(255) DEFAULT NULL AFTER `status`");
            $messages[] = "Added column 'google_event_id' to 'crm_meetings'.";
        }
    } catch (Exception $e) {}

    try {
        $stmt = $db->query("SHOW COLUMNS FROM `crm_meetings` LIKE 'meet_link'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `crm_meetings` ADD COLUMN `meet_link` VARCHAR(500) DEFAULT NULL AFTER `google_event_id`");
            $messages[] = "Added column 'meet_link' to 'crm_meetings'.";
        }
    } catch (Exception $e) {}

    // 3. Alter crm_tasks to append sync_to_calendar and google_event_id
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `crm_tasks` LIKE 'sync_to_calendar'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `crm_tasks` ADD COLUMN `sync_to_calendar` TINYINT(1) DEFAULT 0 AFTER `status`");
            $messages[] = "Added column 'sync_to_calendar' to 'crm_tasks'.";
        }
    } catch (Exception $e) {}

    try {
        $stmt = $db->query("SHOW COLUMNS FROM `crm_tasks` LIKE 'google_event_id'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `crm_tasks` ADD COLUMN `google_event_id` VARCHAR(255) DEFAULT NULL AFTER `sync_to_calendar`");
            $messages[] = "Added column 'google_event_id' to 'crm_tasks'.";
        }
    } catch (Exception $e) {}

    sendJsonResponse('success', 'External Apps Database Migrations executed successfully.', [
        'details' => $messages
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Migration failed: ' . $e->getMessage(), [
        'details' => $messages
    ], 500);
}
