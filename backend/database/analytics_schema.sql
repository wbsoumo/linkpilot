-- LinkPilot Enterprise Analytics Database Schemas

CREATE TABLE IF NOT EXISTS `visitor_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_token` VARCHAR(255) UNIQUE NOT NULL,
    `user_id` INT DEFAULT NULL,
    `workspace_id` INT DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `country` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `region` VARCHAR(100) DEFAULT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `vpn_detected` TINYINT(1) DEFAULT 0,
    `proxy_detected` TINYINT(1) DEFAULT 0,
    `browser` VARCHAR(100) DEFAULT NULL,
    `browser_version` VARCHAR(50) DEFAULT NULL,
    `os` VARCHAR(100) DEFAULT NULL,
    `os_version` VARCHAR(50) DEFAULT NULL,
    `device_type` VARCHAR(50) DEFAULT NULL,
    `device_brand` VARCHAR(50) DEFAULT NULL,
    `device_model` VARCHAR(50) DEFAULT NULL,
    `screen_width` INT DEFAULT NULL,
    `screen_height` INT DEFAULT NULL,
    `viewport_width` INT DEFAULT NULL,
    `viewport_height` INT DEFAULT NULL,
    `pixel_ratio` DECIMAL(4, 2) DEFAULT NULL,
    `touch_supported` TINYINT(1) DEFAULT 0,
    `dark_mode_enabled` TINYINT(1) DEFAULT 0,
    `referrer` VARCHAR(500) DEFAULT NULL,
    `login_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `logout_time` TIMESTAMP DEFAULT NULL,
    `login_method` VARCHAR(50) DEFAULT NULL,
    `remember_me` TINYINT(1) DEFAULT 0,
    `session_status` VARCHAR(50) DEFAULT 'active',
    `logout_reason` VARCHAR(255) DEFAULT NULL,
    `session_expired` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_v_sessions_token` (`session_token`),
    INDEX `idx_v_sessions_user` (`user_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `universal_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `session_token` VARCHAR(255) NOT NULL,
    `event_name` VARCHAR(100) NOT NULL,
    `event_category` VARCHAR(100) NOT NULL,
    `event_action` VARCHAR(100) DEFAULT NULL,
    `event_label` VARCHAR(255) DEFAULT NULL,
    `object_type` VARCHAR(100) DEFAULT NULL,
    `object_id` INT DEFAULT NULL,
    `page_url` VARCHAR(500) NOT NULL,
    `page_title` VARCHAR(255) DEFAULT NULL,
    `referrer` VARCHAR(500) DEFAULT NULL,
    `metadata_json` JSON DEFAULT NULL,
    `device_type` VARCHAR(50) DEFAULT NULL,
    `browser` VARCHAR(100) DEFAULT NULL,
    `os` VARCHAR(100) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_u_events_name` (`event_name`),
    INDEX `idx_u_events_user` (`user_id`),
    INDEX `idx_u_events_token` (`session_token`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `prompt_id` VARCHAR(100) DEFAULT NULL,
    `prompt_length` INT DEFAULT NULL,
    `tokens_used` INT DEFAULT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `response_time_ms` INT DEFAULT NULL,
    `accepted` TINYINT(1) DEFAULT 0,
    `edited` TINYINT(1) DEFAULT 0,
    `copied` TINYINT(1) DEFAULT 0,
    `regenerated` TINYINT(1) DEFAULT 0,
    `feedback` VARCHAR(255) DEFAULT NULL,
    `thumbs_up` TINYINT(1) DEFAULT 0,
    `thumbs_down` TINYINT(1) DEFAULT 0,
    `credits_used` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `billing_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `invoice_id` VARCHAR(100) DEFAULT NULL,
    `plan_id` VARCHAR(100) DEFAULT NULL,
    `plan_name` VARCHAR(100) DEFAULT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `amount` DECIMAL(10, 2) DEFAULT NULL,
    `credits_purchased` INT DEFAULT NULL,
    `credits_used` INT DEFAULT NULL,
    `wallet_balance` INT DEFAULT NULL,
    `coupon` VARCHAR(50) DEFAULT NULL,
    `refund` TINYINT(1) DEFAULT 0,
    `failed_payment` TINYINT(1) DEFAULT 0,
    `renewal_date` TIMESTAMP DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `user_health_scores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNIQUE NOT NULL,
    `health_score` INT DEFAULT 100,
    `activity_score` INT DEFAULT 100,
    `engagement_score` INT DEFAULT 100,
    `feature_score` INT DEFAULT 100,
    `payment_score` INT DEFAULT 100,
    `support_score` INT DEFAULT 100,
    `retention_score` INT DEFAULT 100,
    `churn_probability` DECIMAL(5, 2) DEFAULT 0.00,
    `last_active_days` INT DEFAULT 0,
    `login_frequency` DECIMAL(5, 2) DEFAULT 1.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `platform_errors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `error_type` VARCHAR(100) NOT NULL,
    `error_code` VARCHAR(50) DEFAULT NULL,
    `page_url` VARCHAR(500) NOT NULL,
    `browser` VARCHAR(100) DEFAULT NULL,
    `stack_trace` TEXT DEFAULT NULL,
    `api_endpoint` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `performance_metrics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `page_load_ms` INT DEFAULT NULL,
    `api_latency_ms` INT DEFAULT NULL,
    `db_time_ms` INT DEFAULT NULL,
    `memory_usage_bytes` BIGINT DEFAULT NULL,
    `cpu_usage_pct` DECIMAL(5, 2) DEFAULT NULL,
    `cache_hit` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
