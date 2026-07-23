<?php
// backend/api/auth/migrate_profiles.php

require_once __DIR__ . '/../../config.php';

// Only allow execution from CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run via CLI command line.\n");
}

$db = Database::getConnection();

echo "Starting user_profiles table columns migration...\n";

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
        echo "SUCCESS: $q\n";
    } catch (PDOException $e) {
        // If column already exists (SQLSTATE 42S21 or duplicate column), ignore it
        if ($e->getCode() === '42S21' || strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "INFO: Column already exists.\n";
        } else {
            echo "ERROR: Failed to run query: $q. Message: " . $e->getMessage() . "\n";
        }
    }
}

echo "Migration complete.\n";
