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

        self::$migrated = true;
    }
}
