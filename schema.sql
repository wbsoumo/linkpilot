-- LinkPilot AI Database Schema

CREATE DATABASE IF NOT EXISTS `linkpilot_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `linkpilot_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) DEFAULT 'user', -- 'user', 'admin'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB;

-- 2. User Profiles Table
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNIQUE NOT NULL,
    `user_type` VARCHAR(100) NOT NULL, -- 'Job Seeker', 'Freelancer', 'Agency', 'Recruiter', 'Business Owner', 'Sales Professional'
    `job_title` VARCHAR(255) DEFAULT NULL,
    `experience_years` INT DEFAULT 0,
    `skills` TEXT DEFAULT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `portfolio_url` VARCHAR(255) DEFAULT NULL,
    `linkedin_url` VARCHAR(255) DEFAULT NULL,
    `about_me` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. SMTP Accounts Table
CREATE TABLE IF NOT EXISTS `smtp_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNIQUE NOT NULL,
    `host` VARCHAR(255) NOT NULL,
    `port` INT NOT NULL,
    `username` VARCHAR(255) NOT NULL,
    `password` TEXT NOT NULL, -- Encrypted
    `sender_name` VARCHAR(255) NOT NULL,
    `sender_email` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_smtp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. AI Generations Table
CREATE TABLE IF NOT EXISTS `ai_generations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL, -- 'email', 'whatsapp', 'comment'
    `post_content` TEXT NOT NULL,
    `generated_content` TEXT NOT NULL,
    `tokens_used` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ai_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_ai_user_type` (`user_id`, `type`)
) ENGINE=InnoDB;

-- 5. Sent Emails Table
CREATE TABLE IF NOT EXISTS `sent_emails` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `recipient_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `status` VARCHAR(50) DEFAULT 'sent', -- 'sent', 'failed'
    `error_message` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_emails_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_emails_user` (`user_id`)
) ENGINE=InnoDB;

-- 6. WhatsApp Generations Table
CREATE TABLE IF NOT EXISTS `whatsapp_generations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `phone_number` VARCHAR(50) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `status` VARCHAR(50) DEFAULT 'generated', -- 'generated', 'opened'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_whatsapp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_whatsapp_user` (`user_id`)
) ENGINE=InnoDB;

-- 7. Comment Generations Table
CREATE TABLE IF NOT EXISTS `comment_generations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `comment` TEXT NOT NULL,
    `status` VARCHAR(50) DEFAULT 'generated', -- 'generated', 'inserted'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_comment_user` (`user_id`)
) ENGINE=InnoDB;

-- 8. Lead Vault Table
CREATE TABLE IF NOT EXISTS `lead_vault` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `linkedin_url` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone_number` VARCHAR(50) DEFAULT NULL,
    `post_url` VARCHAR(500) DEFAULT NULL,
    `post_content` TEXT DEFAULT NULL,
    `source` VARCHAR(100) DEFAULT 'LinkedIn Extension',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_leads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_leads_search` (`user_id`, `name`, `company_name`)
) ENGINE=InnoDB;

-- 9. User Statistics Table
CREATE TABLE IF NOT EXISTS `user_statistics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNIQUE NOT NULL,
    `total_requests` INT DEFAULT 0,
    `emails_generated` INT DEFAULT 0,
    `emails_sent` INT DEFAULT 0,
    `whatsapp_generated` INT DEFAULT 0,
    `comments_generated` INT DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_stats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Extension Events Table
CREATE TABLE IF NOT EXISTS `extension_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `event_type` VARCHAR(100) NOT NULL, -- 'extension_opened', 'popup_opened', 'comment_inserted', 'whatsapp_opened'
    `details` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_events_user_type` (`user_id`, `event_type`)
) ENGINE=InnoDB;

-- 11. Activity Logs Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_logs_user` (`user_id`)
) ENGINE=InnoDB;

-- Insert default admin user if not exists
-- Default password: 'adminpassword' (hashed using PASSWORD_DEFAULT in PHP, but for seed we can insert a dummy hash and update it or let the register flow work)
-- Wait, let's hash 'admin123' using password_hash and insert: $2y$10$wN9Q.lGq1Qd0l6uB7G7hXeP5k6qY5O3h6dGvOqB2vNlH8bL4bKxeq
-- Actually we can register via API, but let's insert a seed user.
-- Let's hash 'AdminPilot@2026' -> password_hash('AdminPilot@2026', PASSWORD_DEFAULT)
-- We will write a script or let it register normally.
