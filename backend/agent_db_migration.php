<?php
// backend/agent_db_migration.php

require_once __DIR__ . '/config.php';

class AgentDBMigration {
    private static $migrated = false;

    public static function run(?PDO $db = null) {
        if (self::$migrated) return;
        if (!$db) $db = Database::getConnection();

        // 1. Conversations session table
        $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_conversations` (
            `id` VARCHAR(64) PRIMARY KEY,
            `user_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL DEFAULT 'New Conversation',
            `summary` TEXT DEFAULT NULL,
            `is_pinned` TINYINT(1) DEFAULT 0,
            `is_archived` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_agent_conv_user` (`user_id`, `updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. Conversation messages table
        $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `conversation_id` VARCHAR(64) NOT NULL,
            `user_id` INT NOT NULL,
            `role` ENUM('user', 'assistant', 'system', 'tool') NOT NULL,
            `content` LONGTEXT NOT NULL,
            `tool_calls_json` LONGTEXT DEFAULT NULL,
            `tool_results_json` LONGTEXT DEFAULT NULL,
            `plan_json` LONGTEXT DEFAULT NULL,
            `status` VARCHAR(50) DEFAULT 'completed',
            `tokens_used` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_agent_msg_conv` (`conversation_id`, `created_at`),
            INDEX `idx_agent_msg_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. Agent long-term memory & preferences
        $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_memory` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `memory_type` VARCHAR(50) NOT NULL DEFAULT 'preference',
            `key_name` VARCHAR(100) NOT NULL,
            `value_text` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_user_mem_key` (`user_id`, `key_name`),
            INDEX `idx_agent_mem_user` (`user_id`, `memory_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4. Agent audit logs
        $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_audit_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `conversation_id` VARCHAR(64) DEFAULT NULL,
            `tool_id` VARCHAR(100) NOT NULL,
            `tool_name` VARCHAR(255) DEFAULT NULL,
            `risk_level` VARCHAR(50) DEFAULT 'READ',
            `input_json` LONGTEXT DEFAULT NULL,
            `output_json` LONGTEXT DEFAULT NULL,
            `approval_status` ENUM('auto_approved', 'approved_by_user', 'rejected', 'failed') NOT NULL DEFAULT 'auto_approved',
            `error_message` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_agent_audit_user` (`user_id`, `created_at`),
            INDEX `idx_agent_audit_tool` (`tool_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 5. Agent multi-step executions & steps
        $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_executions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `conversation_id` VARCHAR(100) DEFAULT NULL,
            `goal` TEXT NOT NULL,
            `status` ENUM('planning', 'waiting_approval', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'planning',
            `plan_json` LONGTEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_agent_exec_lookup` (`user_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_tool_executions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `execution_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `step_id` VARCHAR(50) NOT NULL,
            `tool_id` VARCHAR(100) NOT NULL,
            `tool_name` VARCHAR(255) DEFAULT NULL,
            `risk_level` VARCHAR(50) DEFAULT 'READ',
            `input_json` LONGTEXT DEFAULT NULL,
            `output_json` LONGTEXT DEFAULT NULL,
            `status` ENUM('pending', 'waiting_approval', 'running', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
            `idempotency_token` VARCHAR(100) DEFAULT NULL,
            `error_message` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_agent_step_lookup` (`execution_id`, `step_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 6. Compatibility & self-healing migrations across schemas
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `external_app_settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `app_name` VARCHAR(100) NOT NULL,
                `setting_key` VARCHAR(100) NOT NULL,
                `setting_value` TEXT DEFAULT NULL,
                INDEX `idx_eas_lookup` (`user_id`, `app_name`, `setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {}

        try {
            $db->exec("CREATE OR REPLACE VIEW `email_templates` AS 
                SELECT id, user_id, name, subject, category, tag, html_content, created_at, updated_at 
                FROM `custom_email_templates`");
        } catch (Throwable $e) {}

        try {
            $stmt = $db->query("SHOW COLUMNS FROM `whatsapp_templates` LIKE 'body_text'");
            if (!$stmt->fetch()) {
                $db->exec("ALTER TABLE `whatsapp_templates` ADD COLUMN `body_text` TEXT DEFAULT NULL");
            }
        } catch (Throwable $e) {}

        try {
            $stmt = $db->query("SHOW COLUMNS FROM `whatsapp_campaigns` LIKE 'total_recipients'");
            if (!$stmt->fetch()) {
                $db->exec("ALTER TABLE `whatsapp_campaigns` ADD COLUMN `total_recipients` INT DEFAULT 0");
            }
        } catch (Throwable $e) {}

        try {
            $stmt = $db->query("SHOW COLUMNS FROM `automation_workflows` LIKE 'description'");
            if (!$stmt->fetch()) {
                $db->exec("ALTER TABLE `automation_workflows` ADD COLUMN `description` TEXT DEFAULT NULL");
            }
        } catch (Throwable $e) {}

        try {
            $stmt = $db->query("SHOW COLUMNS FROM `automation_workflows` LIKE 'status'");
            if (!$stmt->fetch()) {
                $db->exec("ALTER TABLE `automation_workflows` ADD COLUMN `status` VARCHAR(50) DEFAULT 'active'");
            }
        } catch (Throwable $e) {}

        try {
            $stmt = $db->query("SHOW COLUMNS FROM `crm_contacts` LIKE 'company'");
            if (!$stmt->fetch()) {
                $db->exec("ALTER TABLE `crm_contacts` ADD COLUMN `company` VARCHAR(255) DEFAULT NULL");
                $db->exec("UPDATE `crm_contacts` c JOIN `crm_companies` co ON c.company_id = co.id SET c.company = co.name WHERE c.company IS NULL");
            }
        } catch (Throwable $e) {}

        self::$migrated = true;
    }
}
