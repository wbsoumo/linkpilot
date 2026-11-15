<?php
// backend/agent/AgentMigration4.php
// Autonomous Agent Engine 2.0: Database Migration for Phase 4 Structured Task Planning & Two-Tier Execution Engine.
// Creates ai_agent_workflow_tasks table and extends ai_agent_workflows with DAG plan definitions.
// Supports both MySQL (Production) and SQLite (Automated Test Harness) with safe rollback.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/AgentMigration2.php';

class AgentMigration4 {
    private static $migrated = false;

    /**
     * Executes the Phase 4 database migration.
     */
    public static function run(?PDO $db = null): array {
        if (self::$migrated) {
            return ['status' => 'skipped', 'message' => 'Phase 4 already migrated in current process.'];
        }
        if (!$db) {
            try {
                $db = Database::getConnection();
            } catch (Throwable $e) {
                return ['status' => 'deferred', 'message' => 'Database connection unavailable: ' . $e->getMessage()];
            }
        }

        // Ensure Phase 2/3 base tables exist first
        AgentMigration2::run($db);

        $results = [];
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // SQLite schema for test harnesses

            // 1. ai_agent_workflow_tasks table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_workflow_tasks` (
                `id` TEXT PRIMARY KEY,
                `workflow_id` TEXT NOT NULL,
                `task_key` TEXT NOT NULL,
                `tool_id` TEXT NOT NULL,
                `title` TEXT NOT NULL,
                `dependencies_json` TEXT DEFAULT NULL,
                `arguments_json` TEXT DEFAULT NULL,
                `output_json` TEXT DEFAULT NULL,
                `state` TEXT NOT NULL DEFAULT 'pending',
                `is_mutating` INTEGER NOT NULL DEFAULT 0,
                `requires_approval` INTEGER NOT NULL DEFAULT 0,
                `approval_token` TEXT DEFAULT NULL,
                `approval_status` TEXT DEFAULT NULL,
                `verified_state` INTEGER NOT NULL DEFAULT 0,
                `verification_note` TEXT DEFAULT NULL,
                `retry_count` INTEGER NOT NULL DEFAULT 0,
                `max_retries` INTEGER NOT NULL DEFAULT 0,
                `error_message` TEXT DEFAULT NULL,
                `execution_time_ms` INTEGER DEFAULT 0,
                `started_at` TEXT DEFAULT NULL,
                `completed_at` TEXT DEFAULT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (`workflow_id`, `task_key`)
            );");
            $results[] = 'Table ai_agent_workflow_tasks verified (SQLite).';

            // 2. Extend ai_agent_workflows with plan_json & limits if not present
            try {
                $cols = $db->query("PRAGMA table_info(ai_agent_workflows)")->fetchAll(PDO::FETCH_ASSOC);
                $existingCols = array_column($cols, 'name');

                if (!in_array('plan_json', $existingCols)) {
                    $db->exec("ALTER TABLE `ai_agent_workflows` ADD COLUMN `plan_json` TEXT DEFAULT NULL");
                    $results[] = 'Added column plan_json to ai_agent_workflows (SQLite).';
                }
                if (!in_array('concurrency_limit', $existingCols)) {
                    $db->exec("ALTER TABLE `ai_agent_workflows` ADD COLUMN `concurrency_limit` INTEGER DEFAULT 3");
                    $results[] = 'Added column concurrency_limit to ai_agent_workflows (SQLite).';
                }
                if (!in_array('max_retries', $existingCols)) {
                    $db->exec("ALTER TABLE `ai_agent_workflows` ADD COLUMN `max_retries` INTEGER DEFAULT 2");
                    $results[] = 'Added column max_retries to ai_agent_workflows (SQLite).';
                }
                if (!in_array('execution_timeout', $existingCols)) {
                    $db->exec("ALTER TABLE `ai_agent_workflows` ADD COLUMN `execution_timeout` INTEGER DEFAULT 60");
                    $results[] = 'Added column execution_timeout to ai_agent_workflows (SQLite).';
                }
                if (!in_array('error_reason', $existingCols)) {
                    $db->exec("ALTER TABLE `ai_agent_workflows` ADD COLUMN `error_reason` TEXT DEFAULT NULL");
                    $results[] = 'Added column error_reason to ai_agent_workflows (SQLite).';
                }
                if (!in_array('recovered_at', $existingCols)) {
                    $db->exec("ALTER TABLE `ai_agent_workflows` ADD COLUMN `recovered_at` TEXT DEFAULT NULL");
                    $results[] = 'Added column recovered_at to ai_agent_workflows (SQLite).';
                }
            } catch (Throwable $e) {
                $results[] = 'Notice on ai_agent_workflows columns (SQLite): ' . $e->getMessage();
            }

        } else {
            // MySQL Production schema

            // 1. ai_agent_workflow_tasks table
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_workflow_tasks` (
                `id` VARCHAR(64) PRIMARY KEY,
                `workflow_id` VARCHAR(64) NOT NULL,
                `task_key` VARCHAR(64) NOT NULL,
                `tool_id` VARCHAR(100) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `dependencies_json` TEXT DEFAULT NULL,
                `arguments_json` LONGTEXT DEFAULT NULL,
                `output_json` LONGTEXT DEFAULT NULL,
                `state` ENUM('pending', 'running', 'waiting_for_approval', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
                `is_mutating` TINYINT(1) NOT NULL DEFAULT 0,
                `requires_approval` TINYINT(1) NOT NULL DEFAULT 0,
                `approval_token` VARCHAR(64) DEFAULT NULL,
                `approval_status` VARCHAR(30) DEFAULT NULL,
                `verified_state` TINYINT(1) NOT NULL DEFAULT 0,
                `verification_note` TEXT DEFAULT NULL,
                `retry_count` INT NOT NULL DEFAULT 0,
                `max_retries` INT NOT NULL DEFAULT 0,
                `error_message` TEXT DEFAULT NULL,
                `execution_time_ms` INT DEFAULT 0,
                `started_at` DATETIME DEFAULT NULL,
                `completed_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_wf_task_wf` (`workflow_id`, `state`),
                INDEX `idx_wf_task_key` (`workflow_id`, `task_key`),
                UNIQUE KEY `uniq_wf_task_node` (`workflow_id`, `task_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $results[] = 'Table ai_agent_workflow_tasks verified (MySQL).';

            // 2. Extend ai_agent_workflows with plan_json & limits if missing
            $columnsToAdd = [
                'plan_json' => "LONGTEXT DEFAULT NULL AFTER `goal`",
                'concurrency_limit' => "INT DEFAULT 3 AFTER `active_step`",
                'max_retries' => "INT DEFAULT 2 AFTER `concurrency_limit`",
                'execution_timeout' => "INT DEFAULT 60 AFTER `max_retries`",
                'error_reason' => "TEXT DEFAULT NULL AFTER `history_json`",
                'recovered_at' => "DATETIME DEFAULT NULL AFTER `completed_at`"
            ];

            foreach ($columnsToAdd as $col => $definition) {
                try {
                    $stmt = $db->query("SHOW COLUMNS FROM `ai_agent_workflows` LIKE '{$col}'");
                    if (!$stmt->fetch()) {
                        $db->exec("ALTER TABLE `ai_agent_workflows` ADD COLUMN `{$col}` {$definition}");
                        $results[] = "Added column {$col} to ai_agent_workflows (MySQL).";
                    }
                } catch (Throwable $e) {
                    $results[] = "Notice on column {$col}: " . $e->getMessage();
                }
            }
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

        $db->exec("DROP TABLE IF EXISTS `ai_agent_workflow_tasks`");

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $colsToDrop = ['plan_json', 'concurrency_limit', 'max_retries', 'execution_timeout', 'error_reason', 'recovered_at'];
            foreach ($colsToDrop as $col) {
                try {
                    $stmt = $db->query("SHOW COLUMNS FROM `ai_agent_workflows` LIKE '{$col}'");
                    if ($stmt->fetch()) {
                        $db->exec("ALTER TABLE `ai_agent_workflows` DROP COLUMN `{$col}`");
                    }
                } catch (Throwable $e) {}
            }
        }

        self::$migrated = false;
        return ['status' => 'rolled_back', 'message' => 'Dropped ai_agent_workflow_tasks and plan columns.'];
    }
}
