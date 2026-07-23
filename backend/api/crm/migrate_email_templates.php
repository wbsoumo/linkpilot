<?php
// backend/api/crm/migrate_email_templates.php

require_once __DIR__ . '/../../config.php';

$is_direct = (basename($_SERVER['SCRIPT_FILENAME']) === 'migrate_email_templates.php');
$db = Database::getConnection();

if ($is_direct) {
    header('Content-Type: application/json');
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    $hasAdmin = false;
    try {
        $stmtAdminCheck = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_verified = 1");
        if ($stmtAdminCheck && (int)$stmtAdminCheck->fetchColumn() > 0) {
            $hasAdmin = true;
        }
    } catch (Exception $e) {
        // Fresh setup
    }
    
    if ($hasAdmin) {
        require_once __DIR__ . '/../../jwt_helper.php';
        JWTHelper::requireAdmin();
    }
}

$messages = [];

try {
    // 1. custom_email_templates
    $db->exec("CREATE TABLE IF NOT EXISTS `custom_email_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) DEFAULT NULL,
        `category` VARCHAR(50) DEFAULT 'Sales',
        `tag` VARCHAR(50) DEFAULT 'Outreach',
        `json_data` LONGTEXT DEFAULT NULL,
        `html_content` LONGTEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_custom_email_templates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'custom_email_templates' checked/created.";

    if ($is_direct) {
        sendJsonResponse('success', 'Email templates database migrated successfully.', [
            'messages' => $messages
        ]);
    }
} catch (Exception $e) {
    if ($is_direct) {
        sendJsonResponse('error', 'Migration failed: ' . $e->getMessage(), [], 500);
    } else {
        throw $e;
    }
}
