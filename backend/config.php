<?php
// backend/config.php

ob_start();
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// CORS Headers
function sendCorsHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

sendCorsHeaders();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'helnovexaa_linkdb');
define('DB_USER', 'helnovexaa_linkuser');
define('DB_PASS', 'Soumojit1234@');
define('DB_PORT', '3306');

// JWT Configuration
define('JWT_SECRET', 'linkpilot_super_secret_key_2026_jwt_token_auth');
define('JWT_EXPIRY', 86400 * 7); // 7 days

// SMTP Password Encryption Configuration
define('ENCRYPTION_KEY', 'linkpilot_secure_key_for_smtp_encryption_256bit'); // 32 characters or hashed
define('ENCRYPTION_METHOD', 'aes-256-cbc');

// WhatsApp Message Billing Cost in INR
define('WHATSAPP_MESSAGE_COST', 0.15);

// OpenRouter API Configuration
// Users can define their custom key, fallback to env or empty
define('OPENROUTER_API_KEY', getenv('OPENROUTER_API_KEY') ?: 'sk-or-v1-placeholder-please-replace-with-your-actual-key');
define('OPENROUTER_MODEL', 'google/gemini-2.5-flash:free'); // Premium but cost-efficient model

// GitHub Models API Configuration
define('GITHUB_TOKEN', getenv('GITHUB_TOKEN') ?: getenv('GITHUB_API_KEY') ?: 'github_pat_placeholder-please-replace-with-your-actual-token');
define('GITHUB_MODELS_MODEL', 'gpt-4o-mini');

// Google AI Studio API Configuration
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GOOGLE_AI_STUDIO_MODEL', 'gemini-3.1-flash-lite');

// Groq API Configuration
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
define('GROQ_MODEL', getenv('GROQ_MODEL') ?: 'openai/gpt-oss-120b');

// Google OAuth 2.0 Configuration (for Google Sheets integration)
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: 'https://linkpilot.work/backend/api/google/callback.php');



// Fast2SMS WhatsApp API Configuration for Registration OTP
define('FAST2SMS_API_KEY', getenv('FAST2SMS_API_KEY') ?: 'YOUR_FAST2SMS_API_KEY');
define('FAST2SMS_MESSAGE_ID', '22325');
define('FAST2SMS_PHONE_NUMBER_ID', '1146366028557419');

// Helper function to encrypt sensitive data (SMTP passwords)
function encryptData($data) {
    $key = hash('sha256', ENCRYPTION_KEY);
    $ivSize = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($ivSize);
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, $key, 0, $iv);
    // Combine encrypted data and iv
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

// Helper function to decrypt sensitive data
function decryptData($encryptedData) {
    $key = hash('sha256', ENCRYPTION_KEY);
    $decoded = base64_decode($encryptedData);
    if (!$decoded) return false;
    
    $parts = explode('::', $decoded);
    if (count($parts) !== 2) return false;
    
    list($encrypted, $ivBase64) = $parts;
    $iv = base64_decode($ivBase64);
    $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, 0, $iv);
    return $decrypted;
}

// Database Connection Singleton
class Database {
    private static $instance = null;
    
    public static function getConnection() {
        if (self::$instance === null) {
            try {
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                try {
                    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=utf8mb4";
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                } catch (PDOException $e) {
                    try {
                        $dsnFallback = "mysql:host=localhost;dbname=" . DB_NAME . ";charset=utf8mb4";
                        self::$instance = new PDO($dsnFallback, DB_USER, DB_PASS, $options);
                    } catch (PDOException $e2) {
                        throw $e;
                    }
                }
                
                // Self-healing database migrations - each run in isolation
                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `users` LIKE 'openrouter_key'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `users` ADD COLUMN `openrouter_key` TEXT DEFAULT NULL");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `users` LIKE 'github_key'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `users` ADD COLUMN `github_key` TEXT DEFAULT NULL");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `users` LIKE 'google_key'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `users` ADD COLUMN `google_key` TEXT DEFAULT NULL");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `users` LIKE 'active_ai_provider'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `users` ADD COLUMN `active_ai_provider` VARCHAR(50) DEFAULT 'github_models'");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `users` LIKE 'active_ai_model'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `users` ADD COLUMN `active_ai_model` VARCHAR(100) DEFAULT NULL");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `smtp_accounts` LIKE 'smtp_type'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `smtp_accounts` ADD COLUMN `smtp_type` VARCHAR(20) DEFAULT 'custom'");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `received_emails` LIKE 'parent_id'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `received_emails` ADD COLUMN `parent_id` INT DEFAULT NULL AFTER `user_id`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `user_profiles` LIKE 'company_size'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `user_profiles` ADD COLUMN `company_size` VARCHAR(100) DEFAULT NULL");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `user_profiles` LIKE 'industry'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `user_profiles` ADD COLUMN `industry` VARCHAR(100) DEFAULT NULL");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `user_profiles` LIKE 'location'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `user_profiles` ADD COLUMN `location` VARCHAR(100) DEFAULT NULL");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `received_emails` LIKE 'ai_status'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `received_emails` ADD COLUMN `ai_status` VARCHAR(20) DEFAULT 'pending' AFTER `is_spam`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `users` LIKE 'phone_number'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `users` ADD COLUMN `phone_number` VARCHAR(50) UNIQUE DEFAULT NULL");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `users` LIKE 'is_verified'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `users` ADD COLUMN `is_verified` TINYINT(1) DEFAULT 0");
                        self::$instance->exec("UPDATE `users` SET `is_verified` = 1");
                    }
                } catch (Exception $e) {}

                try {
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `otp_verifications` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `phone_number` VARCHAR(50) NOT NULL,
                        `otp_hash` VARCHAR(255) NOT NULL,
                        `attempts` INT DEFAULT 0,
                        `ip_address` VARCHAR(45) NOT NULL,
                        `expires_at` TIMESTAMP NOT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_otp_phone` (`phone_number`),
                        INDEX `idx_otp_ip` (`ip_address`),
                        INDEX `idx_otp_created` (`created_at`)
                    ) ENGINE=InnoDB;");
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW TABLES LIKE 'whatsapp_accounts'");
                    if (!$stmt->fetch()) {
                        require_once __DIR__ . '/api/crm/migrate_whatsapp.php';
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `whatsapp_accounts` LIKE 'connection_type'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `whatsapp_accounts` ADD COLUMN `connection_type` VARCHAR(50) DEFAULT 'manual' AFTER `user_id`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `whatsapp_accounts` LIKE 'display_name'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `whatsapp_accounts` ADD COLUMN `display_name` VARCHAR(255) DEFAULT NULL AFTER `display_phone_number`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `whatsapp_accounts` LIKE 'verification_status'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `whatsapp_accounts` ADD COLUMN `verification_status` VARCHAR(50) DEFAULT NULL AFTER `messaging_limit`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `whatsapp_accounts` LIKE 'connected_at'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `whatsapp_accounts` ADD COLUMN `connected_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_verified_at`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `whatsapp_accounts` LIKE 'last_sync'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `whatsapp_accounts` ADD COLUMN `last_sync` TIMESTAMP NULL DEFAULT NULL AFTER `connected_at`");
                    }
                } catch (Exception $e) {}

                try {
                    // Create email_provider_settings
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `email_provider_settings` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `provider_name` VARCHAR(50) UNIQUE NOT NULL,
                        `is_enabled` TINYINT(1) DEFAULT 0,
                        `api_key` TEXT DEFAULT NULL,
                        `api_secret` TEXT DEFAULT NULL,
                        `priority` INT DEFAULT 1,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB;");

                    // Seed default provider configurations if empty
                    $stmtCount = self::$instance->query("SELECT COUNT(*) FROM `email_provider_settings`");
                    if ((int)$stmtCount->fetchColumn() === 0) {
                        $stmtSeed = self::$instance->prepare("INSERT INTO `email_provider_settings` (provider_name, is_enabled, priority) VALUES (?, 0, ?)");
                        $stmtSeed->execute(['hunter', 1]);
                        $stmtSeed->execute(['prospeo', 2]);
                        $stmtSeed->execute(['apollo', 3]);
                    }

                    // Self-healing check for linkedin_scraper provider settings
                    $stmtScrapCount = self::$instance->prepare("SELECT COUNT(*) FROM `email_provider_settings` WHERE provider_name = ?");
                    $stmtScrapCount->execute(['linkedin_scraper']);
                    if ((int)$stmtScrapCount->fetchColumn() === 0) {
                        $stmtScrapSeed = self::$instance->prepare("INSERT INTO `email_provider_settings` (provider_name, is_enabled, api_key, api_secret, priority) VALUES (?, ?, ?, ?, ?)");
                        $stmtScrapSeed->execute(['linkedin_scraper', 0, 'http://localhost:8000', '15', 10]);
                    }

                    // Create payment_provider_settings
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `payment_provider_settings` (
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

                    // Seed default payment config
                    $stmtCountPay = self::$instance->query("SELECT COUNT(*) FROM `payment_provider_settings` WHERE provider_name = 'razorpay'");
                    if ((int)$stmtCountPay->fetchColumn() === 0) {
                        self::$instance->exec("INSERT INTO `payment_provider_settings` (provider_name, is_enabled, test_mode, currency, success_url, failure_url) VALUES ('razorpay', 0, 1, 'INR', 'recharge.html?status=success', 'recharge.html?status=failure')");
                    }

                    // Create email_cache
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `email_cache` (
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

                    // Create scraper_requests_log
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `scraper_requests_log` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NOT NULL,
                        `linkedin_url` VARCHAR(500) NOT NULL,
                        `scraping_duration` DECIMAL(6, 3) DEFAULT NULL,
                        `company_found` VARCHAR(255) DEFAULT NULL,
                        `hunter_lookup_status` VARCHAR(50) DEFAULT NULL,
                        `credits_consumed` INT DEFAULT 0,
                        `errors` TEXT DEFAULT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB;");

                    // Create user_email_credits
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `user_email_credits` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT UNIQUE NOT NULL,
                        `total_credits` INT DEFAULT 0,
                        `used_credits` INT DEFAULT 0,
                        `remaining_credits` INT DEFAULT 0,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        CONSTRAINT `fk_credits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB;");

                    // Upgrade user_email_credits columns for free and purchased credit division
                    try {
                        $stmt = self::$instance->query("SHOW COLUMNS FROM `user_email_credits` LIKE 'free_credits'");
                        if (!$stmt->fetch()) {
                            self::$instance->exec("ALTER TABLE `user_email_credits` ADD COLUMN `free_credits` INT DEFAULT 200");
                            self::$instance->exec("UPDATE `user_email_credits` SET `free_credits` = remaining_credits");
                        }
                    } catch (Exception $e) {}

                    try {
                        $stmt = self::$instance->query("SHOW COLUMNS FROM `user_email_credits` LIKE 'purchased_credits'");
                        if (!$stmt->fetch()) {
                            self::$instance->exec("ALTER TABLE `user_email_credits` ADD COLUMN `purchased_credits` INT DEFAULT 0");
                        }
                    } catch (Exception $e) {}

                    try {
                        $stmt = self::$instance->query("SHOW COLUMNS FROM `user_email_credits` LIKE 'last_monthly_reset_at'");
                        if (!$stmt->fetch()) {
                            self::$instance->exec("ALTER TABLE `user_email_credits` ADD COLUMN `last_monthly_reset_at` TIMESTAMP NULL DEFAULT NULL");
                            self::$instance->exec("UPDATE `user_email_credits` SET `last_monthly_reset_at` = CURRENT_TIMESTAMP");
                        }
                    } catch (Exception $e) {}

                    // Seed credit records for existing users if missing (default to 200 free tier credits)
                    self::$instance->exec("INSERT IGNORE INTO `user_email_credits` (user_id, total_credits, used_credits, remaining_credits, free_credits, purchased_credits, last_monthly_reset_at) SELECT id, 200, 0, 200, 200, 0, CURRENT_TIMESTAMP FROM `users`");

                    // Create email_credit_transactions
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `email_credit_transactions` (
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

                    // Create recharge_orders
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `recharge_orders` (
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

                    // Create payment_transactions
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `payment_transactions` (
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

                    // Create email_search_history
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `email_search_history` (
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

                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `user_profiles` LIKE 'email_open_tracking'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `user_profiles` ADD COLUMN `email_open_tracking` TINYINT(1) DEFAULT 1");
                    }
                } catch (Exception $e) {}

                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS `email_tracking` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `tracking_id` VARCHAR(64) UNIQUE NOT NULL,
                            `user_id` INT NULL,
                            `email_type` VARCHAR(50) NOT NULL,
                            `campaign_log_id` INT NULL,
                            `sent_email_id` INT NULL,
                            `recipient_email` VARCHAR(255) DEFAULT '',
                            `subject` VARCHAR(255) DEFAULT '',
                            `first_opened_at` DATETIME NULL,
                            `last_opened_at` DATETIME NULL,
                            `open_count` INT DEFAULT 0,
                            `ip_address` VARCHAR(45) NULL,
                            `user_agent` TEXT NULL,
                            `device` VARCHAR(100) NULL,
                            `browser` VARCHAR(100) NULL,
                            `os` VARCHAR(100) NULL,
                            `country` VARCHAR(100) DEFAULT 'Unknown',
                            `city` VARCHAR(100) DEFAULT 'Unknown',
                            `is_google_proxy` TINYINT(1) DEFAULT 0,
                            `is_apple_privacy` TINYINT(1) DEFAULT 0,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX (`user_id`),
                            INDEX (`campaign_log_id`),
                            INDEX (`sent_email_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                    self::$instance->exec("ALTER TABLE `email_tracking` MODIFY COLUMN `user_id` INT NULL");
                } catch (Exception $e) {}

                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS `email_click_tracking` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `tracking_id` VARCHAR(64) NOT NULL,
                            `campaign_log_id` INT NULL,
                            `link_url` TEXT NOT NULL,
                            `link_id` VARCHAR(64) NULL,
                            `ip_address` VARCHAR(45) NULL,
                            `user_agent` TEXT NULL,
                            `device` VARCHAR(100) NULL,
                            `browser` VARCHAR(100) NULL,
                            `os` VARCHAR(100) NULL,
                            `country` VARCHAR(100) DEFAULT 'Unknown',
                            `city` VARCHAR(100) DEFAULT 'Unknown',
                            `clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                            INDEX (`tracking_id`),
                            INDEX (`campaign_log_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                } catch (Exception $e) {}

                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS `email_activity_events` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `campaign_log_id` INT NOT NULL,
                            `tracking_id` VARCHAR(64) NULL,
                            `event_type` VARCHAR(50) NOT NULL,
                            `event_label` VARCHAR(255) NULL,
                            `event_data` TEXT NULL,
                            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                            INDEX (`campaign_log_id`),
                            INDEX (`event_type`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                } catch (Exception $e) {}

                try {
                    $colsToEnsure = [
                        'is_bounced' => 'TINYINT(1) DEFAULT 0',
                        'is_unsubscribed' => 'TINYINT(1) DEFAULT 0',
                        'is_replied' => 'TINYINT(1) DEFAULT 0',
                        'replied_at' => 'DATETIME NULL',
                        'is_meeting_booked' => 'TINYINT(1) DEFAULT 0',
                        'meeting_booked_at' => 'DATETIME NULL',
                        'click_count' => 'INT DEFAULT 0',
                        'first_clicked_at' => 'DATETIME NULL',
                        'last_clicked_at' => 'DATETIME NULL'
                    ];
                    foreach ($colsToEnsure as $col => $def) {
                        $cStmt = self::$instance->query("SHOW COLUMNS FROM `email_campaign_logs` LIKE '{$col}'");
                        if (!$cStmt->fetch()) {
                            self::$instance->exec("ALTER TABLE `email_campaign_logs` ADD COLUMN `{$col}` {$def}");
                        }
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW INDEX FROM `smtp_accounts` WHERE Key_name = 'user_id' AND Non_unique = 0");
                    if ($stmt->fetch()) {
                        try {
                            self::$instance->exec("ALTER TABLE `smtp_accounts` ADD INDEX `idx_smtp_user_nonunique` (`user_id`)");
                        } catch (Exception $idxEx) {}
                        self::$instance->exec("ALTER TABLE `smtp_accounts` DROP INDEX `user_id`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `smtp_accounts` LIKE 'is_default'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `smtp_accounts` ADD COLUMN `is_default` TINYINT(1) DEFAULT 0");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `users` LIKE 'active_email_template'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `users` ADD COLUMN `active_email_template` VARCHAR(50) DEFAULT 'minimalist'");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `users` LIKE 'status'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `users` ADD COLUMN `status` VARCHAR(20) DEFAULT 'active'");
                    }
                } catch (Exception $e) {}

                try {
                    self::$instance->exec("
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
                            CONSTRAINT `fk_external_app_user_config` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                            UNIQUE KEY `idx_user_provider` (`user_id`, `provider`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");
                } catch (Exception $e) {}

                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS `user_ai_keys` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `user_id` INT NOT NULL,
                            `provider` VARCHAR(50) NOT NULL,
                            `api_key` TEXT NOT NULL,
                            `status` VARCHAR(50) DEFAULT 'active',
                            `error_message` TEXT DEFAULT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            CONSTRAINT `fk_user_ai_keys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");
                    
                    // Migrate existing keys if any
                    $stmtUsers = self::$instance->query("SELECT id, openrouter_key, github_key, google_key FROM users");
                    $usersWithKeys = $stmtUsers->fetchAll();
                    foreach ($usersWithKeys as $u) {
                        if (!empty($u['openrouter_key'])) {
                            $stmtCheck = self::$instance->prepare("SELECT COUNT(*) FROM user_ai_keys WHERE user_id = ? AND provider = 'openrouter' AND api_key = ?");
                            $stmtCheck->execute([$u['id'], $u['openrouter_key']]);
                            if ((int)$stmtCheck->fetchColumn() === 0) {
                                $stmtIns = self::$instance->prepare("INSERT INTO user_ai_keys (user_id, provider, api_key, status) VALUES (?, 'openrouter', ?, 'active')");
                                $stmtIns->execute([$u['id'], $u['openrouter_key']]);
                            }
                        }
                        if (!empty($u['github_key'])) {
                            $stmtCheck = self::$instance->prepare("SELECT COUNT(*) FROM user_ai_keys WHERE user_id = ? AND provider = 'github_models' AND api_key = ?");
                            $stmtCheck->execute([$u['id'], $u['github_key']]);
                            if ((int)$stmtCheck->fetchColumn() === 0) {
                                $stmtIns = self::$instance->prepare("INSERT INTO user_ai_keys (user_id, provider, api_key, status) VALUES (?, 'github_models', ?, 'active')");
                                $stmtIns->execute([$u['id'], $u['github_key']]);
                            }
                        }
                        if (!empty($u['google_key'])) {
                            $stmtCheck = self::$instance->prepare("SELECT COUNT(*) FROM user_ai_keys WHERE user_id = ? AND provider = 'google_ai_studio' AND api_key = ?");
                            $stmtCheck->execute([$u['id'], $u['google_key']]);
                            if ((int)$stmtCheck->fetchColumn() === 0) {
                                $stmtIns = self::$instance->prepare("INSERT INTO user_ai_keys (user_id, provider, api_key, status) VALUES (?, 'google_ai_studio', ?, 'active')");
                                $stmtIns->execute([$u['id'], $u['google_key']]);
                            }
                        }
                    }
                } catch (Exception $migEx) {}

                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS `user_ai_key_logs` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `key_id` INT NOT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            CONSTRAINT `fk_user_ai_key_logs_key` FOREIGN KEY (`key_id`) REFERENCES `user_ai_keys` (`id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");
                } catch (Exception $e) {}



                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `crm_meetings` LIKE 'google_event_id'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `crm_meetings` ADD COLUMN `google_event_id` VARCHAR(255) DEFAULT NULL AFTER `status`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `crm_meetings` LIKE 'meet_link'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `crm_meetings` ADD COLUMN `meet_link` VARCHAR(500) DEFAULT NULL AFTER `google_event_id`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `crm_tasks` LIKE 'sync_to_calendar'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `crm_tasks` ADD COLUMN `sync_to_calendar` TINYINT(1) DEFAULT 0 AFTER `status`");
                    }
                } catch (Exception $e) {}

                try {
                    $stmt = self::$instance->query("SHOW COLUMNS FROM `crm_tasks` LIKE 'google_event_id'");
                    if (!$stmt->fetch()) {
                        self::$instance->exec("ALTER TABLE `crm_tasks` ADD COLUMN `google_event_id` VARCHAR(255) DEFAULT NULL AFTER `sync_to_calendar`");
                    }
                } catch (Exception $e) {}

                try {
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `crm_booking_profiles` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT UNIQUE NOT NULL,
                        `booking_id` VARCHAR(16) UNIQUE NOT NULL,
                        `timezone` VARCHAR(100) DEFAULT 'Asia/Kolkata',
                        `duration_minutes` INT DEFAULT 30,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        CONSTRAINT `fk_booking_profile_user_selfheal` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                    self::$instance->exec("CREATE TABLE IF NOT EXISTS `crm_booking_availability` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NOT NULL,
                        `day_of_week` INT NOT NULL,
                        `start_time` TIME NOT NULL,
                        `end_time` TIME NOT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        CONSTRAINT `fk_booking_avail_user_selfheal` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                        UNIQUE KEY `idx_user_day_slot` (`user_id`, `day_of_week`, `start_time`, `end_time`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
                } catch (Exception $e) {}
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Database Connection Failed: " . $e->getMessage()
                ]);
                exit;
            }
        }
        return self::$instance;
    }
}
// Helper function to return JSON responses
function sendJsonResponse($status, $message, $data = [], $statusCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    
    $success = ($status === 'success' || $status === true);
    
    $responseArray = [
        "success" => $success,
        "message" => $message
    ];
    
    if ($success) {
        $responseArray["data"] = $data;
    } else {
        $responseArray["errors"] = is_array($data) ? $data : [$data];
    }
    
    // Copy flat keys to root for legacy frontend compatibility
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            if (!isset($responseArray[$key])) {
                $responseArray[$key] = $val;
            }
        }
    }
    
    $responseArray["status"] = $success ? "success" : "error";
    
    $json = json_encode($responseArray);
    if ($json === false) {
        array_walk_recursive($responseArray, function(&$item) {
            if (is_string($item)) {
                $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            }
        });
        $json = json_encode($responseArray);
        if ($json === false) {
            $json = json_encode([
                "success" => false,
                "message" => "JSON encoding failed: " . json_last_error_msg(),
                "errors" => [],
                "status" => "error"
            ]);
        }
    }
    
    if (class_exists('WhatsAppMetaService')) {
        WhatsAppMetaService::logDebug("sendJsonResponse output: HTTP {$statusCode} | JSON: {$json}");
    }
    
    echo $json;
    exit;
}

// Helper function to log user activity
function logActivity($userId, $action) {
    try {
        $db = Database::getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $ip]);
    } catch (Exception $e) {
        // Silently fail logging to avoid breaking primary requests
    }
}

// Helper function to update user statistics
function updateStatistic($userId, $column, $increment = 1) {
    try {
        $db = Database::getConnection();
        
        // Ensure stat record exists
        $stmt = $db->prepare("INSERT INTO user_statistics (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = user_id");
        $stmt->execute([$userId]);
        
        // Increment the column
        $allowedColumns = ['total_requests', 'emails_generated', 'emails_sent', 'whatsapp_generated', 'comments_generated'];
        if (in_array($column, $allowedColumns)) {
            $sql = "UPDATE user_statistics SET `{$column}` = `{$column}` + :increment WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['increment' => $increment, 'user_id' => $userId]);
        }
    } catch (Exception $e) {
        // Silently fail statistic updates to avoid breaking flows
    }
}

// Helper function to lazy-check and reset monthly credits
function checkAndResetMonthlyCredits($userId) {
    $db = Database::getConnection();
    try {
        $stmt = $db->prepare("SELECT * FROM user_email_credits WHERE user_id = ?");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();
        
        if (!$wallet) {
            // Seed credits if missing
            $stmtInsert = $db->prepare("INSERT INTO user_email_credits (user_id, total_credits, used_credits, remaining_credits, free_credits, purchased_credits, last_monthly_reset_at) VALUES (?, 200, 0, 200, 200, 0, CURRENT_TIMESTAMP)");
            $stmtInsert->execute([$userId]);
            return;
        }
        
        $lastReset = $wallet['last_monthly_reset_at'] ?? null;
        $currentMonth = date('Y-m');
        $lastResetMonth = $lastReset ? date('Y-m', strtotime($lastReset)) : null;
        
        if ($currentMonth !== $lastResetMonth) {
            // It is a new month!
            // Refresh free credits to 100 if they are below 100
            $freeCredits = (int)($wallet['free_credits'] ?? 200);
            if ($freeCredits < 100) {
                $freeCredits = 100;
            }
            $purchasedCredits = (int)($wallet['purchased_credits'] ?? 0);
            $newRemaining = $freeCredits + $purchasedCredits;
            
            $stmtUpdate = $db->prepare("UPDATE user_email_credits SET free_credits = ?, remaining_credits = ?, last_monthly_reset_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $stmtUpdate->execute([$freeCredits, $newRemaining, $userId]);
            
            // Log transaction
            $stmtTx = $db->prepare("INSERT INTO email_credit_transactions (user_id, type, credits, amount, status, provider_used) VALUES (?, 'reset', 100, 0.00, 'success', 'system')");
            $stmtTx->execute([$userId]);
        }
    } catch (Exception $e) {
        // Silently capture exceptions to avoid blocking critical paths
    }
}

// Helper function to check contact limits (max 100 contacts for non-admins)
function checkContactLimit($userId) {
    $db = Database::getConnection();
    try {
        $stmtUser = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $role = $stmtUser->fetchColumn();
        if ($role === 'admin') {
            return true; // Admins have unlimited contacts
        }
        
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ?");
        $stmtCount->execute([$userId]);
        $count = (int)$stmtCount->fetchColumn();
        
        return ($count < 100);
    } catch (Exception $e) {
        return true; // Fallback to avoid locking user account on error
    }
}

// Call Generic AI routing function (centralized to Admin API keys)
function callAI($systemPrompt, $userPrompt, $userId = null) {
    $provider = 'github_models';
    $model = GITHUB_MODELS_MODEL;

    $db = Database::getConnection();
    
    // Always fetch active provider and model preferences from the first Admin user
    try {
        $stmtAdmin = $db->query("SELECT active_ai_provider, active_ai_model FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $adminRes = $stmtAdmin->fetch();
        if ($adminRes) {
            if (!empty($adminRes['active_ai_provider'])) {
                $provider = $adminRes['active_ai_provider'];
            }
            if ($provider === 'github_models') {
                $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : GITHUB_MODELS_MODEL;
            } elseif ($provider === 'google_ai_studio') {
                $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : GOOGLE_AI_STUDIO_MODEL;
            } else {
                $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : OPENROUTER_MODEL;
            }
        } else {
            // Fallback to user if no admin role exists
            if ($userId !== null) {
                $stmtUser = $db->prepare("SELECT active_ai_provider, active_ai_model FROM users WHERE id = ?");
                $stmtUser->execute([$userId]);
                $res = $stmtUser->fetch();
                if ($res) {
                    if (!empty($res['active_ai_provider'])) {
                        $provider = $res['active_ai_provider'];
                    }
                    if ($provider === 'github_models') {
                        $model = !empty($res['active_ai_model']) ? $res['active_ai_model'] : GITHUB_MODELS_MODEL;
                    } elseif ($provider === 'google_ai_studio') {
                        $model = !empty($res['active_ai_model']) ? $res['active_ai_model'] : GOOGLE_AI_STUDIO_MODEL;
                    } else {
                        $model = !empty($res['active_ai_model']) ? $res['active_ai_model'] : OPENROUTER_MODEL;
                    }
                }
            }
        }
    } catch (Exception $e) {}

    $apiKeysList = [];
    try {
        if ($userId !== null) {
            $stmtKeys = $db->prepare("
                SELECT k.id, k.api_key, k.status 
                FROM user_ai_keys k 
                WHERE k.user_id = ? AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') 
                ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC
            ");
            $stmtKeys->execute([$userId, $provider]);
            $apiKeysList = $stmtKeys->fetchAll();
        }
        
        if (empty($apiKeysList)) {
            // Retrieve all active keys belonging to users with the 'admin' role
            $stmtKeys = $db->prepare("
                SELECT k.id, k.api_key, k.status 
                FROM user_ai_keys k 
                JOIN users u ON k.user_id = u.id 
                WHERE u.role = 'admin' AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') 
                ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC
            ");
            $stmtKeys->execute([$provider]);
            $apiKeysList = $stmtKeys->fetchAll();
        }
    } catch (Exception $e) {}

    // If empty list, create a mock key entry for the fallback environment key
    if (empty($apiKeysList)) {
        $fallbackKey = '';
        if ($provider === 'github_models') {
            $fallbackKey = getenv('GITHUB_TOKEN') ?: getenv('GITHUB_API_KEY') ?: (defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '');
        } elseif ($provider === 'google_ai_studio') {
            $fallbackKey = getenv('GEMINI_API_KEY') ?: '';
        } else {
            $fallbackKey = getenv('OPENROUTER_API_KEY') ?: OPENROUTER_API_KEY;
        }
        $apiKeysList = [
            ['id' => null, 'api_key' => $fallbackKey, 'status' => 'active']
        ];
    }

    $errors = [];
    foreach ($apiKeysList as $keyEntry) {
        $keyId = $keyEntry['id'];
        $rawKey = $keyEntry['api_key'];
        
        // Decrypt key if it's from the database
        if ($keyId !== null) {
            $decrypted = decryptData($rawKey);
            $apiKey = ($decrypted !== false) ? $decrypted : $rawKey;
        } else {
            $apiKey = $rawKey;
        }
        
        if (empty($apiKey) || strpos($apiKey, 'placeholder') !== false) {
            $errors[] = "Key ID " . ($keyId ?? 'ENV') . ": API Key not configured or empty.";
            continue;
        }

        try {
            if ($provider === 'github_models') {
                $headers = [
                    "Authorization: Bearer " . $apiKey,
                    "Content-Type: application/json",
                    "User-Agent: LinkPilot-AI"
                ];

                $postFields = [
                    "model" => $model,
                    "messages" => [
                        ["role" => "system", "content" => $systemPrompt],
                        ["role" => "user", "content" => $userPrompt]
                    ],
                    "max_tokens" => 1000
                ];

                $ch = curl_init("https://models.inference.ai.azure.com/chat/completions");
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    throw new Exception("GitHub Models connection error: " . $error);
                }

                $data = json_decode($response, true);
                if ($httpCode !== 200) {
                    if ($httpCode === 401) {
                        throw new Exception("401: Unauthorized: Invalid GitHub Personal Access Token.");
                    }
                    if ($httpCode === 429 || (isset($data['error']['message']) && strpos(strtolower($data['error']['message']), 'limit') !== false)) {
                        throw new Exception("429: Rate limit exceeded.");
                    }
                    $msg = $data['message'] ?? ($data['error']['message'] ?? "Unknown GitHub Models Error");
                    throw new Exception("GitHub Models API Error (HTTP {$httpCode}): " . $msg);
                }

                // Succeeded! Reset status to active if db key
                if ($keyId !== null) {
                    $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = ?");
                    $stmtUpdate->execute([$keyId]);
                    $stmtLog = $db->prepare("INSERT INTO user_ai_key_logs (key_id) VALUES (?)");
                    $stmtLog->execute([$keyId]);
                }

                $generatedText = $data['choices'][0]['message']['content'] ?? '';
                $tokensUsed = $data['usage']['total_tokens'] ?? 0;

                return [
                    "text" => trim($generatedText),
                    "tokens" => $tokensUsed
                ];

            } elseif ($provider === 'google_ai_studio') {
                $headers = [
                    "Authorization: Bearer " . $apiKey,
                    "Content-Type: application/json"
                ];

                $postFields = [
                    "model" => $model,
                    "messages" => [
                        ["role" => "system", "content" => $systemPrompt],
                        ["role" => "user", "content" => $userPrompt]
                    ],
                    "max_tokens" => 1000
                ];

                $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/openai/chat/completions");
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    throw new Exception("Google AI Studio connection error: " . $error);
                }

                $data = json_decode($response, true);
                if ($httpCode !== 200) {
                    if ($httpCode === 401) {
                        throw new Exception("401: Unauthorized: Invalid Google Gemini API Key.");
                    }
                    if ($httpCode === 429 || (isset($data['error']['message']) && strpos(strtolower($data['error']['message']), 'limit') !== false)) {
                        throw new Exception("429: Rate limit exceeded.");
                    }
                    $msg = $data['message'] ?? ($data['error']['message'] ?? "Unknown Google AI Studio Error");
                    throw new Exception("Google AI Studio API Error (HTTP {$httpCode}): " . $msg);
                }

                // Succeeded!
                if ($keyId !== null) {
                    $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = ?");
                    $stmtUpdate->execute([$keyId]);
                    $stmtLog = $db->prepare("INSERT INTO user_ai_key_logs (key_id) VALUES (?)");
                    $stmtLog->execute([$keyId]);
                }

                $generatedText = $data['choices'][0]['message']['content'] ?? '';
                $tokensUsed = $data['usage']['total_tokens'] ?? 0;

                return [
                    "text" => trim($generatedText),
                    "tokens" => $tokensUsed
                ];

            } else {
                // OpenRouter API with fallbacks
                $modelsToTry = [
                    $model,
                    'google/gemini-2.5-flash:free',
                    'google/gemini-2.5-flash',
                    'meta-llama/llama-3.3-70b-instruct:free',
                    'google/gemini-2.0-flash-lite:free',
                    'meta-llama/llama-3.1-8b-instruct:free',
                    'meta-llama/llama-3-8b-instruct:free',
                    'google/gemini-2.0-flash-exp'
                ];
                $modelsToTry = array_unique($modelsToTry);
                $lastError = '';

                foreach ($modelsToTry as $currentModel) {
                    try {
                        $headers = [
                            "Authorization: Bearer " . $apiKey,
                            "Content-Type: application/json",
                            "HTTP-Referer: https://linkpilot.work",
                            "X-Title: LinkPilot AI"
                        ];

                        $postFields = [
                            "model" => $currentModel,
                            "messages" => [
                                ["role" => "system", "content" => $systemPrompt],
                                ["role" => "user", "content" => $userPrompt]
                            ],
                            "max_tokens" => 1000
                        ];

                        $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $error = curl_error($ch);
                        curl_close($ch);

                        // Debug logging
                        file_put_contents(__DIR__ . '/api_debug.log', sprintf(
                            "[%s] Model: %s, HTTP Code: %d, Error: %s, Response: %s\n",
                            date('Y-m-d H:i:s'),
                            $currentModel,
                            $httpCode,
                            $error ?: 'None',
                            $response ?: 'Empty'
                        ), FILE_APPEND);

                        if ($error) {
                            throw new Exception("OpenRouter connection error: " . $error);
                        }

                        $data = json_decode($response, true);
                        if ($httpCode !== 200) {
                            if ($httpCode === 401 || (isset($data['error']['code']) && $data['error']['code'] === 401)) {
                                throw new Exception("401: Unauthorized: Invalid OpenRouter API Key.");
                            }
                            if ($httpCode === 429 || (isset($data['error']['message']) && strpos(strtolower($data['error']['message']), 'limit') !== false)) {
                                throw new Exception("429: Rate limit exceeded.");
                            }
                            $msg = $data['error']['message'] ?? "Unknown OpenRouter Error";
                            throw new Exception("OpenRouter API Error (HTTP {$httpCode}) with model {$currentModel}: " . $msg);
                        }

                        // Succeeded!
                        if ($keyId !== null) {
                            $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = ?");
                            $stmtUpdate->execute([$keyId]);
                            $stmtLog = $db->prepare("INSERT INTO user_ai_key_logs (key_id) VALUES (?)");
                            $stmtLog->execute([$keyId]);
                        }

                        $generatedText = $data['choices'][0]['message']['content'] ?? '';
                        $tokensUsed = $data['usage']['total_tokens'] ?? 0;

                        return [
                            "text" => trim($generatedText),
                            "tokens" => $tokensUsed
                        ];
                    } catch (Exception $ex) {
                        $lastError = $ex->getMessage();
                        if (strpos($lastError, '401:') !== false || strpos($lastError, '429:') !== false) {
                            throw $ex;
                        }
                    }
                }

                throw new Exception("Failed to generate outreach content using OpenRouter. Last error: " . $lastError);
            }
        } catch (Exception $ex) {
            $errMessage = $ex->getMessage();
            $errors[] = "Key ID " . ($keyId ?? 'ENV') . ": " . $errMessage;

            // Mark key in DB if applicable
            if ($keyId !== null) {
                $status = 'limit_exceeded';
                if (strpos($errMessage, '401:') !== false || strpos($errMessage, 'Unauthorized') !== false) {
                    $status = 'invalid';
                }
                
                try {
                    $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = ?, error_message = ? WHERE id = ?");
                    $stmtUpdate->execute([$status, $errMessage, $keyId]);
                } catch (Exception $dbEx) {}
            }
            
            // Loop and try next key
            continue;
        }
    }

    // If we reached here, all keys failed
    throw new Exception("All configured API keys for provider '{$provider}' failed. Details:\n" . implode("\n", $errors));
}

// Wrapper for legacy callOpenRouter to keep endpoints compatible
function callOpenRouter($systemPrompt, $userPrompt, $userId = null) {
    return callAI($systemPrompt, $userPrompt, $userId);
}

