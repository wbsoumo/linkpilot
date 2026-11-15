<?php
// backend/agent/WorkflowEngine.php
// Autonomous Agent Engine 2.0: Multi-Turn Workflow State Machine & Idempotent Execution Controller.
// Enforces strict state transitions, execution locking, entity accumulation, duplicate-action prevention,
// and multi-driver support (MySQL & SQLite).

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/AgentMigration2.php';

class InvalidWorkflowTransitionException extends Exception {}

class WorkflowEngine {

    const STATE_PENDING = 'pending';
    const STATE_RUNNING = 'running';
    const STATE_WAITING_FOR_APPROVAL = 'waiting_for_approval';
    const STATE_COMPLETED = 'completed';
    const STATE_FAILED = 'failed';
    const STATE_CANCELLED = 'cancelled';

    private static $allowedTransitions = [
        self::STATE_PENDING => [self::STATE_RUNNING, self::STATE_WAITING_FOR_APPROVAL, self::STATE_COMPLETED, self::STATE_CANCELLED, self::STATE_FAILED],
        self::STATE_RUNNING => [self::STATE_WAITING_FOR_APPROVAL, self::STATE_COMPLETED, self::STATE_FAILED, self::STATE_CANCELLED],
        self::STATE_WAITING_FOR_APPROVAL => [self::STATE_RUNNING, self::STATE_COMPLETED, self::STATE_CANCELLED, self::STATE_FAILED],
        self::STATE_COMPLETED => [self::STATE_RUNNING], // Resumable / continuation turn (e.g., "send it to him")
        self::STATE_FAILED => [self::STATE_RUNNING, self::STATE_CANCELLED],
        self::STATE_CANCELLED => [] // Terminal
    ];

    /**
     * Creates and records a new workflow session with idempotency guarantee.
     */
    public static function createWorkflow(
        int $userId,
        string $convId,
        string $pluginId,
        string $workflowType,
        string $goal,
        array $initialEntities = [],
        ?string $idempotencyKey = null,
        ?PDO $db = null
    ): array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) {}
        }
        if ($db) {
            AgentMigration2::run($db);
        }

        // Check idempotency if key provided
        if (!empty($idempotencyKey) && $db) {
            $existing = self::getWorkflowByIdempotency($userId, $idempotencyKey, $db);
            if ($existing) {
                return $existing;
            }
        }

        $workflowId = 'wf_' . bin2hex(random_bytes(12));
        $now = date('Y-m-d H:i:s');

        $history = [
            [
                'state' => self::STATE_PENDING,
                'timestamp' => $now,
                'note' => 'Workflow initiated'
            ]
        ];

        if ($db) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO ai_agent_workflows (
                        id, user_id, conversation_id, plugin_id, workflow_type, goal,
                        state, active_step, entities_json, history_json, idempotency_key, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $workflowId,
                    $userId,
                    $convId,
                    $pluginId,
                    $workflowType,
                    $goal,
                    self::STATE_PENDING,
                    json_encode($initialEntities),
                    json_encode($history),
                    $idempotencyKey,
                    $now,
                    $now
                ]);
            } catch (PDOException $e) {
                // If unique idempotency collision happened concurrently
                if (!empty($idempotencyKey) && (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate'))) {
                    $existing = self::getWorkflowByIdempotency($userId, $idempotencyKey, $db);
                    if ($existing) return $existing;
                }
                throw $e;
            }
            return self::getWorkflow($workflowId, $userId, $db);
        }

        return [
            'id' => $workflowId,
            'user_id' => $userId,
            'conversation_id' => $convId,
            'plugin_id' => $pluginId,
            'workflow_type' => $workflowType,
            'goal' => $goal,
            'state' => self::STATE_PENDING,
            'entities' => $initialEntities,
            'history' => $history
        ];
    }

    /**
     * Retrieves a workflow by ID.
     */
    public static function getWorkflow(string $workflowId, int $userId, ?PDO $db = null): ?array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return null; }
        }
        $stmt = $db->prepare("SELECT * FROM ai_agent_workflows WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$workflowId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['entities'] = json_decode($row['entities_json'] ?? '{}', true) ?: [];
        $row['history'] = json_decode($row['history_json'] ?? '[]', true) ?: [];
        return $row;
    }

    /**
     * Checks if a workflow already exists with the given idempotency key.
     */
    public static function getWorkflowByIdempotency(int $userId, string $idempotencyKey, ?PDO $db = null): ?array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return null; }
        }
        $stmt = $db->prepare("SELECT * FROM ai_agent_workflows WHERE user_id = ? AND idempotency_key = ? LIMIT 1");
        $stmt->execute([$userId, $idempotencyKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['entities'] = json_decode($row['entities_json'] ?? '{}', true) ?: [];
        $row['history'] = json_decode($row['history_json'] ?? '[]', true) ?: [];
        return $row;
    }

    /**
     * Fetches the currently active / recent workflow for a conversation.
     * Searches pending, running, waiting_for_approval, and recently completed workflows (within 30 mins).
     */
    public static function getActiveWorkflow(int $userId, string $convId, ?PDO $db = null): ?array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return null; }
        }
        AgentMigration2::run($db);

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = "
                SELECT * FROM ai_agent_workflows 
                WHERE user_id = ? AND conversation_id = ? 
                  AND state IN ('pending', 'running', 'waiting_for_approval', 'completed')
                  AND updated_at >= datetime('now', '-30 minutes')
                ORDER BY updated_at DESC LIMIT 1
            ";
        } else {
            $sql = "
                SELECT * FROM ai_agent_workflows 
                WHERE user_id = ? AND conversation_id = ? 
                  AND state IN ('pending', 'running', 'waiting_for_approval', 'completed')
                  AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                ORDER BY updated_at DESC LIMIT 1
            ";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $convId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['entities'] = json_decode($row['entities_json'] ?? '{}', true) ?: [];
        $row['history'] = json_decode($row['history_json'] ?? '[]', true) ?: [];
        return $row;
    }

    /**
     * Enforces valid state machine transition and updates workflow state.
     * Throws InvalidWorkflowTransitionException on illegal transitions.
     */
    public static function transitionState(
        string $workflowId,
        string $newState,
        ?string $activeStep = null,
        array $newEntities = [],
        string $note = '',
        ?PDO $db = null
    ): array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) {}
        }
        if (!$db) throw new Exception("Database required for state transition.");

        $stmt = $db->prepare("SELECT * FROM ai_agent_workflows WHERE id = ? LIMIT 1");
        $stmt->execute([$workflowId]);
        $wf = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wf) throw new Exception("Workflow '{$workflowId}' not found.");

        $currentState = $wf['state'];
        $allowed = self::$allowedTransitions[$currentState] ?? [];

        if (!in_array($newState, $allowed) && $currentState !== $newState) {
            throw new InvalidWorkflowTransitionException("Invalid workflow state transition: cannot transition from '{$currentState}' to '{$newState}'.");
        }

        $now = date('Y-m-d H:i:s');
        $entities = json_decode($wf['entities_json'] ?? '{}', true) ?: [];
        if (!empty($newEntities)) {
            $entities = array_merge($entities, $newEntities);
        }

        $history = json_decode($wf['history_json'] ?? '[]', true) ?: [];
        $history[] = [
            'from' => $currentState,
            'to' => $newState,
            'step' => $activeStep,
            'timestamp' => $now,
            'note' => $note
        ];

        $completedAt = ($newState === self::STATE_COMPLETED) ? $now : $wf['completed_at'];

        $stmtUpdate = $db->prepare("
            UPDATE ai_agent_workflows 
            SET state = ?, active_step = ?, entities_json = ?, history_json = ?, updated_at = ?, completed_at = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $newState,
            $activeStep,
            json_encode($entities),
            json_encode($history),
            $now,
            $completedAt,
            $workflowId
        ]);

        return self::getWorkflow($workflowId, (int)$wf['user_id'], $db);
    }

    /**
     * Merges newly discovered or generated entities into workflow memory.
     */
    public static function updateEntities(string $workflowId, array $entities, ?PDO $db = null): void {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return; }
        }
        $stmt = $db->prepare("SELECT entities_json FROM ai_agent_workflows WHERE id = ?");
        $stmt->execute([$workflowId]);
        $current = json_decode($stmt->fetchColumn() ?: '{}', true) ?: [];
        $merged = array_merge($current, $entities);

        $now = date('Y-m-d H:i:s');
        $stmtUpd = $db->prepare("UPDATE ai_agent_workflows SET entities_json = ?, updated_at = ? WHERE id = ?");
        $stmtUpd->execute([json_encode($merged), $now, $workflowId]);
    }

    /**
     * Acquires an execution lock on a workflow to prevent concurrent duplicate runs.
     */
    public static function acquireLock(string $workflowId, int $userId, int $ttlSeconds = 30, ?PDO $db = null): bool {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return false; }
        }
        $lockToken = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $stmt = $db->prepare("
            UPDATE ai_agent_workflows 
            SET lock_token = ?, lock_expires_at = ? 
            WHERE id = ? AND user_id = ? AND (lock_expires_at IS NULL OR lock_expires_at <= ?)
        ");
        $stmt->execute([$lockToken, $expires, $workflowId, $userId, $now]);
        return ($stmt->rowCount() > 0);
    }

    /**
     * Checks if a workflow is currently locked.
     */
    public static function isLocked(string $workflowId, ?PDO $db = null): bool {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return false; }
        }
        $stmt = $db->prepare("SELECT lock_expires_at FROM ai_agent_workflows WHERE id = ? LIMIT 1");
        $stmt->execute([$workflowId]);
        $expires = $stmt->fetchColumn();
        if (!$expires) return false;
        return (strtotime($expires) > time());
    }

    /**
     * Releases an execution lock.
     */
    public static function releaseLock(string $workflowId, int $userId, ?PDO $db = null): void {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return; }
        }
        $stmt = $db->prepare("UPDATE ai_agent_workflows SET lock_token = NULL, lock_expires_at = NULL WHERE id = ? AND user_id = ?");
        $stmt->execute([$workflowId, $userId]);
    }

    /**
     * Automatically archives or cancels stale active workflows older than 2 hours.
     */
    public static function archiveStaleWorkflows(?PDO $db = null): int {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return 0; }
        }
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = "
                UPDATE ai_agent_workflows 
                SET state = 'cancelled' 
                WHERE state IN ('pending', 'running', 'waiting_for_approval') 
                  AND updated_at < datetime('now', '-2 hours')
            ";
        } else {
            $sql = "
                UPDATE ai_agent_workflows 
                SET state = 'cancelled' 
                WHERE state IN ('pending', 'running', 'waiting_for_approval') 
                  AND updated_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
