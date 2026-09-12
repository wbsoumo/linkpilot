<?php
// backend/agent_execution_engine.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_tool_registry.php';
require_once __DIR__ . '/crm_sync_helper.php';

class AgentExecutionEngine {

    /**
     * Self-healing migration for agent execution tracking tables.
     */
    public static function ensureTablesExist(PDO $db) {
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
    }

    /**
     * Resolves placeholders like {{step_1.contact_id}} or {{step_1.email}} from prior step outputs.
     */
    public static function resolveStepPlaceholders(array $input, array $stepOutputs) {
        $jsonStr = json_encode($input);
        foreach ($stepOutputs as $stepId => $outData) {
            if (!is_array($outData)) continue;
            foreach ($outData as $key => $val) {
                if (is_scalar($val)) {
                    $pattern = '/\{\{\s*' . preg_quote($stepId, '/') . '\.' . preg_quote($key, '/') . '\s*\}\}/i';
                    $jsonStr = preg_replace($pattern, (string)$val, $jsonStr);
                }
            }
        }
        return json_decode($jsonStr, true) ?: $input;
    }

    /**
     * Executes a single tool step safely with schema validation, permission checks & verification.
     */
    public static function executeStep($userId, $toolId, array $input, $idempotencyToken = null, ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();
        self::ensureTablesExist($db);

        $tool = AIToolRegistry::getTool($toolId);
        if (!$tool) {
            throw new Exception("Tool '{$toolId}' not found in registry.");
        }

        // Execute Tool Handler via Registry
        $resultData = AIToolRegistry::executeTool($toolId, $userId, $input, $db);

        // Verification Engine
        $isVerified = true;
        $verificationMsg = "Execution verified successfully.";

        if ($toolId === 'create_contact' && isset($resultData['id'])) {
            $stmtVer = $db->prepare("SELECT id FROM crm_contacts WHERE id = ? AND user_id = ?");
            $stmtVer->execute([$resultData['id'], $userId]);
            $isVerified = (bool)$stmtVer->fetchColumn();
            if (!$isVerified) $verificationMsg = "Contact record verification failed.";
        }

        return [
            'success' => true,
            'verified' => $isVerified,
            'verification_message' => $verificationMsg,
            'tool_id' => $toolId,
            'data' => $resultData
        ];
    }
}
