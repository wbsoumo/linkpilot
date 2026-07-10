<?php
// backend/api/migrate.php

require_once __DIR__ . '/../config.php';

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
    require_once __DIR__ . '/../jwt_helper.php';
    JWTHelper::requireAdmin();
}

$messages = [];

try {
    // 1. Create email_provider_settings
    $db->exec("CREATE TABLE IF NOT EXISTS `email_provider_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `provider_name` VARCHAR(50) UNIQUE NOT NULL,
        `is_enabled` TINYINT(1) DEFAULT 0,
        `api_key` TEXT DEFAULT NULL,
        `api_secret` TEXT DEFAULT NULL,
        `priority` INT DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    $messages[] = "Table 'email_provider_settings' check completed.";

    // Seed default provider configurations if empty
    $stmtCount = $db->query("SELECT COUNT(*) FROM `email_provider_settings`");
    if ((int)$stmtCount->fetchColumn() === 0) {
        $stmtSeed = $db->prepare("INSERT INTO `email_provider_settings` (provider_name, is_enabled, priority) VALUES (?, 0, ?)");
        $stmtSeed->execute(['hunter', 1]);
        $stmtSeed->execute(['prospeo', 2]);
        $stmtSeed->execute(['apollo', 3]);
        $messages[] = "Provider configurations seeded.";
    }

    // 2. Create payment_provider_settings
    $db->exec("CREATE TABLE IF NOT EXISTS `payment_provider_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `provider_name` VARCHAR(50) UNIQUE NOT NULL,
        `is_enabled` TINYINT(1) DEFAULT 0,
        `test_mode` TINYINT(1) DEFAULT 1,
        `key_id` TEXT DEFAULT NULL,
        `secret_key` TEXT DEFAULT NULL,
        `webhook_secret` TEXT DEFAULT NULL,
        `currency` VARCHAR(10) DEFAULT 'INR',
        `success_url` VARCHAR(255) DEFAULT NULL,
        `failure_url` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    $messages[] = "Table 'payment_provider_settings' check completed.";

    // Seed default payment config
    $stmtCountPay = $db->query("SELECT COUNT(*) FROM `payment_provider_settings` WHERE provider_name = 'razorpay'");
    if ((int)$stmtCountPay->fetchColumn() === 0) {
        $db->exec("INSERT INTO `payment_provider_settings` (provider_name, is_enabled, test_mode, currency, success_url, failure_url) VALUES ('razorpay', 0, 1, 'INR', 'recharge.html?status=success', 'recharge.html?status=failure')");
        $messages[] = "Payment gateway configuration seeded.";
    }

    // 3. Create email_cache
    $db->exec("CREATE TABLE IF NOT EXISTS `email_cache` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) DEFAULT NULL,
        `company_name` VARCHAR(255) DEFAULT NULL,
        `domain` VARCHAR(255) DEFAULT NULL,
        `linkedin_url` VARCHAR(500) UNIQUE NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `confidence_score` INT DEFAULT NULL,
        `provider` VARCHAR(50) DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_cache_lookup` (`linkedin_url`(255)),
        INDEX `idx_cache_names` (`name`, `company_name`)
    ) ENGINE=InnoDB;");
    $messages[] = "Table 'email_cache' check completed.";

    // 4. Create user_email_credits
    $db->exec("CREATE TABLE IF NOT EXISTS `user_email_credits` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNIQUE NOT NULL,
        `total_credits` INT DEFAULT 0,
        `used_credits` INT DEFAULT 0,
        `remaining_credits` INT DEFAULT 0,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_credits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    $messages[] = "Table 'user_email_credits' check completed.";

    // Seed credit records for existing users if missing
    $db->exec("INSERT IGNORE INTO `user_email_credits` (user_id, total_credits, used_credits, remaining_credits) SELECT id, 0, 0, 0 FROM `users`");
    $messages[] = "Credit balances populated for existing profiles.";

    // 5. Create email_credit_transactions
    $db->exec("CREATE TABLE IF NOT EXISTS `email_credit_transactions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `type` VARCHAR(50) NOT NULL,
        `credits` INT NOT NULL,
        `amount` DECIMAL(10, 2) DEFAULT 0.00,
        `payment_id` VARCHAR(100) DEFAULT NULL,
        `provider_used` VARCHAR(50) DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'success',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_credit_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        INDEX `idx_credit_tx_user` (`user_id`)
    ) ENGINE=InnoDB;");
    $messages[] = "Table 'email_credit_transactions' check completed.";

    // 6. Create recharge_orders
    $db->exec("CREATE TABLE IF NOT EXISTS `recharge_orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `order_id` VARCHAR(100) UNIQUE NOT NULL,
        `amount` DECIMAL(10, 2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'INR',
        `credits` INT NOT NULL,
        `status` VARCHAR(50) DEFAULT 'created',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_recharge_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    $messages[] = "Table 'recharge_orders' check completed.";

    // 7. Create payment_transactions
    $db->exec("CREATE TABLE IF NOT EXISTS `payment_transactions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `order_id` VARCHAR(100) NOT NULL,
        `payment_id` VARCHAR(100) UNIQUE DEFAULT NULL,
        `signature` VARCHAR(255) DEFAULT NULL,
        `amount` DECIMAL(10, 2) NOT NULL,
        `status` VARCHAR(50) DEFAULT 'pending',
        `error_code` VARCHAR(100) DEFAULT NULL,
        `error_description` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    $messages[] = "Table 'payment_transactions' check completed.";

    // 8. Create email_search_history
    $db->exec("CREATE TABLE IF NOT EXISTS `email_search_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) DEFAULT NULL,
        `company` VARCHAR(255) DEFAULT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `linkedin_url` VARCHAR(500) DEFAULT NULL,
        `provider` VARCHAR(50) DEFAULT NULL,
        `credits_used` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_history_search_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        INDEX `idx_history_search_user` (`user_id`)
    ) ENGINE=InnoDB;");
    $messages[] = "Table 'email_search_history' check completed.";

    sendJsonResponse('success', 'LinkPilot Database Migrations Run Successfully.', [
        'details' => $messages
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Migration failed: ' . $e->getMessage(), [
        'details' => $messages
    ], 500);
}
