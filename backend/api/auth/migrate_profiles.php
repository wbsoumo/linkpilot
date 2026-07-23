<?php
// backend/api/auth/migrate_profiles.php

require_once __DIR__ . '/../../config.php';

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Database Migration</title><style>body{font-family:monospace;background:#f8fafc;color:#1e293b;padding:20px;line-height:1.6;}h2{color:#6D5EF5;}.success{color:#16a34a;}.info{color:#475569;}.error{color:#dc2626;font-weight:bold;}</style></head><body>";
    echo "<h2>Starting user_profiles table columns migration...</h2><hr><pre>";
} else {
    echo "Starting user_profiles table columns migration...\n";
}

$db = Database::getConnection();

// Create custom_email_templates table if not exists
try {
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
    if ($is_cli) {
        echo "SUCCESS: Created/verified table custom_email_templates\n";
    } else {
        echo "<span class='success'>[SUCCESS]</span> Created/verified table custom_email_templates<br>";
    }
} catch (PDOException $e) {
    if ($is_cli) {
        echo "ERROR: Failed to create table custom_email_templates: " . $e->getMessage() . "\n";
    } else {
        echo "<span class='error'>[ERROR]</span> Failed to create table custom_email_templates: " . $e->getMessage() . "<br>";
    }
}

$queries = [
    "ALTER TABLE `user_profiles` ADD COLUMN `company_size` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `user_profiles` ADD COLUMN `industry` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `user_profiles` ADD COLUMN `location` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `user_profiles` ADD COLUMN `business_address` VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE `user_profiles` ADD COLUMN `tax_id` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `user_profiles` ADD COLUMN `support_email` VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `user_profiles` ADD COLUMN `currency` VARCHAR(10) DEFAULT 'USD'",
    "ALTER TABLE `user_profiles` ADD COLUMN `timezone` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `user_profiles` ADD COLUMN `webhook_url` VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE `user_profiles` ADD COLUMN `notification_leads` TINYINT(1) DEFAULT 1",
    "ALTER TABLE `user_profiles` ADD COLUMN `notification_tasks` TINYINT(1) DEFAULT 1",
    "ALTER TABLE `user_profiles` ADD COLUMN `notification_digest` TINYINT(1) DEFAULT 1",
    "ALTER TABLE `user_profiles` ADD COLUMN `notification_errors` TINYINT(1) DEFAULT 1",
    "ALTER TABLE `user_profiles` ADD COLUMN `two_factor_enabled` TINYINT(1) DEFAULT 0",
    "ALTER TABLE `user_profiles` ADD COLUMN `email_open_tracking` TINYINT(1) DEFAULT 1"
];

foreach ($queries as $q) {
    try {
        $db->exec($q);
        if ($is_cli) {
            echo "SUCCESS: $q\n";
        } else {
            echo "<span class='success'>[SUCCESS]</span> $q<br>";
        }
    } catch (PDOException $e) {
        // If column already exists (SQLSTATE 42S21 or duplicate column), ignore it
        if ($e->getCode() === '42S21' || strpos($e->getMessage(), 'Duplicate column name') !== false) {
            if ($is_cli) {
                echo "INFO: Column already exists.\n";
            } else {
                echo "<span class='info'>[INFO]</span> Column already exists for: $q<br>";
            }
        } else {
            if ($is_cli) {
                echo "ERROR: Failed to run query: $q. Message: " . $e->getMessage() . "\n";
            } else {
                echo "<span class='error'>[ERROR]</span> Failed to run query: $q. Message: " . $e->getMessage() . "<br>";
            }
        }
    }
}

if ($is_cli) {
    echo "Migration complete.\n";
} else {
    echo "</pre><hr><h3>Migration complete.</h3></body></html>";
}
