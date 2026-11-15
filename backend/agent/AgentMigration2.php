<?php
// backend/agent/AgentMigration2.php
// Autonomous Agent Engine 2.0: Database Migration for Workflows, State Machine & Plugin Registry.
// Implements safe idempotent creation, schema validation, multi-driver support (MySQL & SQLite for tests),
// and rollback capability.

require_once __DIR__ . '/../config.php';

class AgentMigration2 {
    private static $migrated = false;

    /**
     * Executes the Phase 2 & Phase 3 migration.
     */
    public static function run(?PDO $db = null): array {
        if (self::$migrated) {
            return ['status' => 'skipped', 'message' => 'Already migrated in current process.'];
        }
        if (!$db) {
            try {
                $db = Database::getConnection();
            } catch (Throwable $e) {
                return ['status' => 'deferred', 'message' => 'Database connection unavailable: ' . $e->getMessage()];
            }
        }

        $results = [];
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // SQLite schema for test harnesses
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_workflows` (
                `id` TEXT PRIMARY KEY,
                `user_id` INTEGER NOT NULL,
                `conversation_id` TEXT NOT NULL,
                `plugin_id` TEXT NOT NULL,
                `workflow_type` TEXT NOT NULL,
                `goal` TEXT NOT NULL,
                `state` TEXT NOT NULL DEFAULT 'pending',
                `active_step` TEXT DEFAULT NULL,
                `entities_json` TEXT DEFAULT NULL,
                `history_json` TEXT DEFAULT NULL,
                `idempotency_key` TEXT DEFAULT NULL,
                `lock_token` TEXT DEFAULT NULL,
                `lock_expires_at` TEXT DEFAULT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                `completed_at` TEXT DEFAULT NULL,
                UNIQUE (`user_id`, `idempotency_key`)
            );");
            $results[] = 'Table ai_agent_workflows verified (SQLite).';

            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_plugins` (
                `id` TEXT PRIMARY KEY,
                `name` TEXT NOT NULL,
                `version` TEXT NOT NULL DEFAULT '1.0.0',
                `category` TEXT NOT NULL DEFAULT 'general',
                `description` TEXT DEFAULT NULL,
                `is_enabled` INTEGER NOT NULL DEFAULT 1,
                `is_system` INTEGER NOT NULL DEFAULT 1,
                `required_connection` TEXT DEFAULT NULL,
                `required_permissions_json` TEXT DEFAULT NULL,
                `dependencies_json` TEXT DEFAULT NULL,
                `manifest_json` TEXT DEFAULT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
            );");
            $results[] = 'Table ai_agent_plugins verified (SQLite).';

            // Check ai_agent_messages
            try {
                $cols = $db->query("PRAGMA table_info(ai_agent_messages)")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($cols)) {
                    $hasWf = false;
                    foreach ($cols as $col) {
                        if ($col['name'] === 'workflow_id') {
                            $hasWf = true;
                            break;
                        }
                    }
                    if (!$hasWf) {
                        $db->exec("ALTER TABLE `ai_agent_messages` ADD COLUMN `workflow_id` TEXT DEFAULT NULL");
                        $results[] = 'Added column workflow_id to ai_agent_messages (SQLite).';
                    }
                }
            } catch (Throwable $e) {}

        } else {
            // MySQL Production schema
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_workflows` (
                `id` VARCHAR(64) PRIMARY KEY,
                `user_id` INT NOT NULL,
                `conversation_id` VARCHAR(64) NOT NULL,
                `plugin_id` VARCHAR(100) NOT NULL,
                `workflow_type` VARCHAR(100) NOT NULL,
                `goal` TEXT NOT NULL,
                `state` ENUM('pending', 'running', 'waiting_for_approval', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
                `active_step` VARCHAR(50) DEFAULT NULL,
                `entities_json` LONGTEXT DEFAULT NULL,
                `history_json` LONGTEXT DEFAULT NULL,
                `idempotency_key` VARCHAR(128) DEFAULT NULL,
                `lock_token` VARCHAR(64) DEFAULT NULL,
                `lock_expires_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `completed_at` DATETIME DEFAULT NULL,
                INDEX `idx_wf_user_conv` (`user_id`, `conversation_id`, `updated_at`),
                INDEX `idx_wf_state` (`state`),
                UNIQUE KEY `uniq_wf_idempotency` (`user_id`, `idempotency_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $results[] = 'Table ai_agent_workflows verified (MySQL).';

            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_plugins` (
                `id` VARCHAR(100) PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `version` VARCHAR(50) NOT NULL DEFAULT '1.0.0',
                `category` VARCHAR(50) NOT NULL DEFAULT 'general',
                `description` TEXT DEFAULT NULL,
                `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `is_system` TINYINT(1) NOT NULL DEFAULT 1,
                `required_connection` VARCHAR(50) DEFAULT NULL,
                `required_permissions_json` TEXT DEFAULT NULL,
                `dependencies_json` TEXT DEFAULT NULL,
                `manifest_json` LONGTEXT DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_plugin_cat` (`category`),
                INDEX `idx_plugin_enabled` (`is_enabled`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $results[] = 'Table ai_agent_plugins verified (MySQL).';

            // Extend ai_agent_messages with workflow_id reference if missing
            try {
                $stmt = $db->query("SHOW COLUMNS FROM `ai_agent_messages` LIKE 'workflow_id'");
                if (!$stmt->fetch()) {
                    $db->exec("ALTER TABLE `ai_agent_messages` ADD COLUMN `workflow_id` VARCHAR(64) DEFAULT NULL AFTER `conversation_id`");
                    $db->exec("ALTER TABLE `ai_agent_messages` ADD INDEX `idx_agent_msg_wf` (`workflow_id`)");
                    $results[] = 'Added column workflow_id to ai_agent_messages (MySQL).';
                }
            } catch (Throwable $e) {
                $results[] = 'Notice on ai_agent_messages.workflow_id: ' . $e->getMessage();
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
            try {
                $db = Database::getConnection();
            } catch (Throwable $e) {
                return ['status' => 'skipped', 'message' => 'No DB connection for rollback'];
            }
        }

        $db->exec("DROP TABLE IF EXISTS `ai_agent_workflows`");
        $db->exec("DROP TABLE IF EXISTS `ai_agent_plugins`");

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            try {
                $stmt = $db->query("SHOW COLUMNS FROM `ai_agent_messages` LIKE 'workflow_id'");
                if ($stmt->fetch()) {
                    $db->exec("ALTER TABLE `ai_agent_messages` DROP COLUMN `workflow_id`");
                }
            } catch (Throwable $e) {}
        }

        self::$migrated = false;
        return ['status' => 'rolled_back', 'message' => 'Dropped ai_agent_workflows and ai_agent_plugins.'];
    }
}
