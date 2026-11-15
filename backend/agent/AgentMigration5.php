<?php
// backend/agent/AgentMigration5.php
// Autonomous Agent Engine 2.0: Database Migration for Phase 5.
// Creates ai_agent_schedules, ai_agent_schedule_runs, and ai_agent_batch_approvals tables.
// Supports both MySQL (Production) and SQLite (Automated Test Harness) with safe rollback.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/AgentMigration4.php';

class AgentMigration5 {
    private static $migrated = false;

    /**
     * Executes the Phase 5 database migration.
     */
    public static function run(?PDO $db = null): array {
        if (self::$migrated) {
            return ['status' => 'skipped', 'message' => 'Phase 5 already migrated in current process.'];
        }
        if (!$db) {
            try {
                $db = Database::getConnection();
            } catch (Throwable $e) {
                return ['status' => 'deferred', 'message' => 'Database connection unavailable: ' . $e->getMessage()];
            }
        }

        // Ensure Phase 4 tables exist first
        AgentMigration4::run($db);

        $results = [];
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // SQLite schema for test harnesses

            // 1. ai_agent_schedules table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_schedules` (
                `id` TEXT PRIMARY KEY,
                `user_id` INTEGER NOT NULL,
                `title` TEXT NOT NULL,
                `cron_expression` TEXT NOT NULL,
                `timezone` TEXT NOT NULL DEFAULT 'UTC',
                `task_type` TEXT NOT NULL DEFAULT 'prospecting_and_outreach',
                `parameters_json` TEXT NOT NULL,
                `dag_template_json` TEXT DEFAULT NULL,
                `approval_policy` TEXT NOT NULL DEFAULT 'digest_review',
                `status` TEXT NOT NULL DEFAULT 'active',
                `missed_run_policy` TEXT NOT NULL DEFAULT 'skip',
                `next_run_at` TEXT NOT NULL,
                `last_run_at` TEXT DEFAULT NULL,
                `last_execution_id` TEXT DEFAULT NULL,
                `lease_token` TEXT DEFAULT NULL,
                `lease_expires_at` TEXT DEFAULT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
            );");
            $results[] = 'Table ai_agent_schedules verified (SQLite).';

            // 2. ai_agent_schedule_runs table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_schedule_runs` (
                `id` TEXT PRIMARY KEY,
                `schedule_id` TEXT NOT NULL,
                `user_id` INTEGER NOT NULL,
                `workflow_id` TEXT DEFAULT NULL,
                `idempotency_key` TEXT NOT NULL,
                `scheduled_for` TEXT NOT NULL,
                `started_at` TEXT NOT NULL,
                `completed_at` TEXT DEFAULT NULL,
                `status` TEXT NOT NULL DEFAULT 'running',
                `summary_json` TEXT DEFAULT NULL,
                `error_message` TEXT DEFAULT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (`schedule_id`, `idempotency_key`)
            );");
            $results[] = 'Table ai_agent_schedule_runs verified (SQLite).';

            // 3. ai_agent_batch_approvals table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_batch_approvals` (
                `id` TEXT PRIMARY KEY,
                `user_id` INTEGER NOT NULL,
                `schedule_run_id` TEXT DEFAULT NULL,
                `workflow_id` TEXT DEFAULT NULL,
                `token` TEXT NOT NULL UNIQUE,
                `item_key` TEXT NOT NULL,
                `recipient_email` TEXT NOT NULL,
                `content_hash` TEXT NOT NULL,
                `status` TEXT NOT NULL DEFAULT 'pending',
                `draft_payload_json` TEXT NOT NULL,
                `expires_at` TEXT NOT NULL,
                `approved_at` TEXT DEFAULT NULL,
                `rejected_at` TEXT DEFAULT NULL,
                `used_at` TEXT DEFAULT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
            );");
            $results[] = 'Table ai_agent_batch_approvals verified (SQLite).';

            // 4. ai_agent_discovered_prospects table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_discovered_prospects` (
                `id` TEXT PRIMARY KEY,
                `user_id` INTEGER NOT NULL,
                `schedule_id` TEXT DEFAULT NULL,
                `schedule_run_id` TEXT DEFAULT NULL,
                `company_name` TEXT NOT NULL,
                `website` TEXT DEFAULT NULL,
                `business_location` TEXT DEFAULT NULL,
                `business_email` TEXT DEFAULT NULL,
                `business_phone` TEXT DEFAULT NULL,
                `decision_maker_name` TEXT DEFAULT NULL,
                `decision_maker_role` TEXT DEFAULT NULL,
                `source_url` TEXT DEFAULT NULL,
                `source_provider` TEXT NOT NULL DEFAULT 'mock',
                `discovery_timestamp` TEXT NOT NULL,
                `extraction_confidence` INTEGER NOT NULL DEFAULT 0,
                `verification_status` TEXT NOT NULL DEFAULT 'unknown',
                `lead_score` INTEGER NOT NULL DEFAULT 50,
                `lead_score_explanation` TEXT DEFAULT NULL,
                `crm_lead_id` INTEGER DEFAULT NULL,
                `crm_company_id` INTEGER DEFAULT NULL,
                `crm_contact_id` INTEGER DEFAULT NULL,
                `status` TEXT NOT NULL DEFAULT 'discovered',
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
            );");
            $results[] = 'Table ai_agent_discovered_prospects verified (SQLite).';

            // 5. ai_agent_outreach_logs table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_outreach_logs` (
                `id` TEXT PRIMARY KEY,
                `user_id` INTEGER NOT NULL,
                `schedule_id` TEXT DEFAULT NULL,
                `schedule_run_id` TEXT DEFAULT NULL,
                `prospect_id` TEXT DEFAULT NULL,
                `batch_token` TEXT DEFAULT NULL,
                `recipient_email` TEXT NOT NULL,
                `subject` TEXT NOT NULL,
                `provider` TEXT NOT NULL DEFAULT 'smtp',
                `status` TEXT NOT NULL DEFAULT 'queued',
                `message_id` TEXT DEFAULT NULL,
                `error_message` TEXT DEFAULT NULL,
                `compliance_flags_json` TEXT DEFAULT NULL,
                `sent_at` TEXT DEFAULT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
            );");
            $results[] = 'Table ai_agent_outreach_logs verified (SQLite).';

            // 6. ai_agent_suppression_list table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_suppression_list` (
                `id` TEXT PRIMARY KEY,
                `user_id` INTEGER NOT NULL,
                `email` TEXT NOT NULL,
                `domain` TEXT DEFAULT NULL,
                `reason` TEXT NOT NULL DEFAULT 'unsubscribed',
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (`user_id`, `email`)
            );");
            $results[] = 'Table ai_agent_suppression_list verified (SQLite).';

        } else {
            // MySQL Production schema

            // 1. ai_agent_schedules table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_schedules` (
                `id` VARCHAR(64) PRIMARY KEY,
                `user_id` INT NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `cron_expression` VARCHAR(100) NOT NULL,
                `timezone` VARCHAR(64) NOT NULL DEFAULT 'UTC',
                `task_type` VARCHAR(100) NOT NULL DEFAULT 'prospecting_and_outreach',
                `parameters_json` LONGTEXT NOT NULL,
                `dag_template_json` LONGTEXT DEFAULT NULL,
                `approval_policy` ENUM('digest_review', 'auto_dispatch') NOT NULL DEFAULT 'digest_review',
                `status` ENUM('active', 'paused', 'cancelled', 'completed') NOT NULL DEFAULT 'active',
                `missed_run_policy` ENUM('run_once', 'skip') NOT NULL DEFAULT 'skip',
                `next_run_at` DATETIME NOT NULL,
                `last_run_at` DATETIME DEFAULT NULL,
                `last_execution_id` VARCHAR(64) DEFAULT NULL,
                `lease_token` VARCHAR(64) DEFAULT NULL,
                `lease_expires_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_sched_due` (`status`, `next_run_at`),
                INDEX `idx_sched_user` (`user_id`, `status`),
                INDEX `idx_sched_lease` (`lease_expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $results[] = 'Table ai_agent_schedules verified (MySQL).';

            // 2. ai_agent_schedule_runs table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_schedule_runs` (
                `id` VARCHAR(64) PRIMARY KEY,
                `schedule_id` VARCHAR(64) NOT NULL,
                `user_id` INT NOT NULL,
                `workflow_id` VARCHAR(64) DEFAULT NULL,
                `idempotency_key` VARCHAR(128) NOT NULL,
                `scheduled_for` DATETIME NOT NULL,
                `started_at` DATETIME NOT NULL,
                `completed_at` DATETIME DEFAULT NULL,
                `status` ENUM('running', 'waiting_for_approval', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'running',
                `summary_json` LONGTEXT DEFAULT NULL,
                `error_message` TEXT DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_sched_idemp` (`schedule_id`, `idempotency_key`),
                INDEX `idx_run_sched` (`schedule_id`, `status`),
                INDEX `idx_run_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $results[] = 'Table ai_agent_schedule_runs verified (MySQL).';

            // 3. ai_agent_batch_approvals table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_batch_approvals` (
                `id` VARCHAR(64) PRIMARY KEY,
                `user_id` INT NOT NULL,
                `schedule_run_id` VARCHAR(64) DEFAULT NULL,
                `workflow_id` VARCHAR(64) DEFAULT NULL,
                `token` VARCHAR(64) NOT NULL UNIQUE,
                `item_key` VARCHAR(64) NOT NULL,
                `recipient_email` VARCHAR(255) NOT NULL,
                `content_hash` VARCHAR(64) NOT NULL,
                `status` ENUM('pending', 'approved', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
                `draft_payload_json` LONGTEXT NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `approved_at` DATETIME DEFAULT NULL,
                `rejected_at` DATETIME DEFAULT NULL,
                `used_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_batch_appr_token` (`token`, `status`),
                INDEX `idx_batch_run` (`schedule_run_id`),
                INDEX `idx_batch_user` (`user_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $results[] = 'Table ai_agent_batch_approvals verified (MySQL).';

            // 4. ai_agent_discovered_prospects table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_discovered_prospects` (
                `id` VARCHAR(64) PRIMARY KEY,
                `user_id` INT NOT NULL,
                `schedule_id` VARCHAR(64) DEFAULT NULL,
                `schedule_run_id` VARCHAR(64) DEFAULT NULL,
                `company_name` VARCHAR(255) NOT NULL,
                `website` VARCHAR(255) DEFAULT NULL,
                `business_location` VARCHAR(255) DEFAULT NULL,
                `business_email` VARCHAR(255) DEFAULT NULL,
                `business_phone` VARCHAR(100) DEFAULT NULL,
                `decision_maker_name` VARCHAR(255) DEFAULT NULL,
                `decision_maker_role` VARCHAR(255) DEFAULT NULL,
                `source_url` TEXT DEFAULT NULL,
                `source_provider` VARCHAR(100) NOT NULL DEFAULT 'mock',
                `discovery_timestamp` DATETIME NOT NULL,
                `extraction_confidence` INT NOT NULL DEFAULT 0,
                `verification_status` VARCHAR(50) NOT NULL DEFAULT 'unknown',
                `lead_score` INT NOT NULL DEFAULT 50,
                `lead_score_explanation` TEXT DEFAULT NULL,
                `crm_lead_id` INT DEFAULT NULL,
                `crm_company_id` INT DEFAULT NULL,
                `crm_contact_id` INT DEFAULT NULL,
                `status` VARCHAR(50) NOT NULL DEFAULT 'discovered',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_prospect_user` (`user_id`, `status`),
                INDEX `idx_prospect_email` (`business_email`),
                INDEX `idx_prospect_run` (`schedule_run_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $results[] = 'Table ai_agent_discovered_prospects verified (MySQL).';

            // 5. ai_agent_outreach_logs table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_outreach_logs` (
                `id` VARCHAR(64) PRIMARY KEY,
                `user_id` INT NOT NULL,
                `schedule_id` VARCHAR(64) DEFAULT NULL,
                `schedule_run_id` VARCHAR(64) DEFAULT NULL,
                `prospect_id` VARCHAR(64) DEFAULT NULL,
                `batch_token` VARCHAR(64) DEFAULT NULL,
                `recipient_email` VARCHAR(255) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `provider` VARCHAR(50) NOT NULL DEFAULT 'smtp',
                `status` ENUM('queued', 'sent', 'failed', 'suppressed', 'bounced') NOT NULL DEFAULT 'queued',
                `message_id` VARCHAR(255) DEFAULT NULL,
                `error_message` TEXT DEFAULT NULL,
                `compliance_flags_json` LONGTEXT DEFAULT NULL,
                `sent_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_outreach_user` (`user_id`, `status`),
                INDEX `idx_outreach_recipient` (`recipient_email`),
                INDEX `idx_outreach_run` (`schedule_run_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $results[] = 'Table ai_agent_outreach_logs verified (MySQL).';

            // 6. ai_agent_suppression_list table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_suppression_list` (
                `id` VARCHAR(64) PRIMARY KEY,
                `user_id` INT NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `domain` VARCHAR(255) DEFAULT NULL,
                `reason` VARCHAR(50) NOT NULL DEFAULT 'unsubscribed',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_suppression_user_email` (`user_id`, `email`),
                INDEX `idx_suppression_domain` (`domain`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $results[] = 'Table ai_agent_suppression_list verified (MySQL).';
        }

        self::$migrated = true;
        return [
            'status' => 'success',
            'details' => $results
        ];
    }

    /**
     * Safe rollback function for testing or disaster recovery.
     */
    public static function rollback(?PDO $db = null): array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) {
                return ['status' => 'skipped', 'message' => 'No DB connection for rollback'];
            }
        }

        $db->exec("DROP TABLE IF EXISTS `ai_agent_suppression_list`");
        $db->exec("DROP TABLE IF EXISTS `ai_agent_outreach_logs`");
        $db->exec("DROP TABLE IF EXISTS `ai_agent_discovered_prospects`");
        $db->exec("DROP TABLE IF EXISTS `ai_agent_batch_approvals`");
        $db->exec("DROP TABLE IF EXISTS `ai_agent_schedule_runs`");
        $db->exec("DROP TABLE IF EXISTS `ai_agent_schedules`");

        self::$migrated = false;
        return ['status' => 'rolled_back', 'message' => 'Dropped Phase 5 tables.'];
    }
}
