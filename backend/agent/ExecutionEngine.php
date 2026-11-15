<?php
// backend/agent/ExecutionEngine.php
// Autonomous Agent Engine 2.0: Tier 2 Deterministic Execution & Verification Engine.
// Executes DAG tasks according to topological readiness, safe concurrency limits,
// bounded retries, Maker-Checker approval gates, deep state verification, and interrupted workflow recovery.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/AgentMigration4.php';
require_once __DIR__ . '/TaskPlanDAG.php';
require_once __DIR__ . '/PluginRegistry.php';
require_once __DIR__ . '/WorkflowEngine.php';
require_once __DIR__ . '/VerificationEngine.php';

class ExecutionEngine {

    const MAX_WORKFLOW_STEPS = 15;
    const DEFAULT_TIMEOUT_SECONDS = 60;
    const DEFAULT_CONCURRENCY = 3;

    /**
     * Executes a TaskPlanDAG through its topological lifecycle.
     */
    public static function executeDAG(
        TaskPlanDAG $dag,
        int $userId,
        string $convId,
        ?string $workflowId = null,
        ?callable $streamEmitter = null,
        ?PDO $db = null,
        array $options = []
    ): array {
        $startTime = microtime(true);
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) {}
        }
        if ($db) {
            AgentMigration4::run($db);
        }

        // Initialize or bind workflow record
        if (empty($workflowId)) {
            $workflow = WorkflowEngine::createWorkflow(
                $userId,
                $convId,
                'structured_dag_plan',
                'task_execution_dag',
                'Execute autonomous DAG task graph',
                $dag->getEntities(),
                $options['idempotency_key'] ?? null,
                $db
            );
            $workflowId = $workflow['id'];
        }

        // Acquire execution lock
        $locked = WorkflowEngine::acquireLock($workflowId, $userId, 45, $db);
        if (!$locked && empty($options['skip_lock_check'])) {
            return [
                'status' => 'locked',
                'workflow_id' => $workflowId,
                'message' => 'Workflow is currently locked by another active execution process.'
            ];
        }

        // Transition workflow state to running
        try {
            WorkflowEngine::transitionState($workflowId, WorkflowEngine::STATE_RUNNING, 'dag_execution_start', $dag->getEntities(), 'Starting DAG execution', $db);
        } catch (Throwable $e) {}

        $executedSteps = [];
        $approvalPayload = null;
        $totalSteps = 0;
        $concurrencyLimit = (int)($options['concurrency_limit'] ?? self::DEFAULT_CONCURRENCY);
        $timeoutSeconds = (int)($options['execution_timeout'] ?? self::DEFAULT_TIMEOUT_SECONDS);

        // Emit initial DAG plan event
        if ($streamEmitter) {
            $streamEmitter('plan_generated', [
                'workflow_id' => $workflowId,
                'total_tasks' => count($dag->getNodes()),
                'tasks' => array_values($dag->getNodes())
            ]);
        }

        // Persist initial DAG state into DB
        self::persistDAGState($workflowId, $dag, $db);

        // Main DAG execution loop
        while (!$dag->isCompleted()) {
            // Check timeout
            if ((microtime(true) - $startTime) > $timeoutSeconds) {
                $errorMsg = "Workflow execution timed out after {$timeoutSeconds}s.";
                self::failRemainingTasks($dag, $errorMsg, $workflowId, $db);
                break;
            }

            // Check step bounds
            if ($totalSteps >= self::MAX_WORKFLOW_STEPS) {
                $errorMsg = "Maximum workflow execution steps (" . self::MAX_WORKFLOW_STEPS . ") exceeded.";
                self::failRemainingTasks($dag, $errorMsg, $workflowId, $db);
                break;
            }

            $readyTasks = $dag->getReadyTasks();

            // If no tasks are ready but DAG is not completed:
            if (empty($readyTasks)) {
                // Check if any task is paused waiting for human approval
                foreach ($dag->getNodes() as $n) {
                    if ($n['state'] === 'waiting_for_approval') {
                        $approvalPayload = [
                            'workflow_id' => $workflowId,
                            'task_key' => $n['task_key'],
                            'tool_id' => $n['tool_id'],
                            'tool_name' => $n['title'],
                            'arguments' => $n['arguments'],
                            'approval_token' => $n['approval_token']
                        ];
                        break 2; // Break loop and wait for approval
                    }
                }

                // Check if remaining tasks are blocked due to failed upstream dependencies
                $skippedAny = false;
                foreach ($dag->getNodes() as $k => $n) {
                    if ($n['state'] === 'pending') {
                        $failedDep = $dag->hasFailedDependency($k);
                        if ($failedDep) {
                            $dag->updateNode($k, [
                                'state' => 'skipped',
                                'error' => "Skipped because predecessor task '{$failedDep}' failed or was skipped."
                            ]);
                            self::persistTaskState($workflowId, $dag->getNode($k), $db);
                            $skippedAny = true;
                        }
                    }
                }

                if (!$skippedAny) {
                    // Deadlock or unresolvable dependency
                    break;
                }
                continue;
            }

            // Limit batch to concurrency limit
            $batch = array_slice($readyTasks, 0, $concurrencyLimit);

            foreach ($batch as $task) {
                $totalSteps++;
                $taskKey = $task['task_key'];
                $toolId = $task['tool_id'];

                // Check Maker-Checker approval gate
                $isPreApproved = !empty($options['approved_task_key']) && ($options['approved_task_key'] === $taskKey);
                if ($task['requires_approval'] && !$isPreApproved) {
                    $approvalToken = 'appr_' . bin2hex(random_bytes(10));
                    $dag->updateNode($taskKey, [
                        'state' => 'waiting_for_approval',
                        'approval_token' => $approvalToken
                    ]);
                    self::persistTaskState($workflowId, $dag->getNode($taskKey), $db);

                    // Pause workflow
                    try {
                        WorkflowEngine::transitionState($workflowId, WorkflowEngine::STATE_WAITING_FOR_APPROVAL, $taskKey, [], 'Paused at approval gate', $db);
                    } catch (Throwable $e) {}

                    $approvalPayload = [
                        'workflow_id' => $workflowId,
                        'task_key' => $taskKey,
                        'tool_id' => $toolId,
                        'tool_name' => $task['title'],
                        'arguments' => $dag->resolveArguments($task['arguments']),
                        'approval_token' => $approvalToken
                    ];

                    if ($streamEmitter) {
                        $streamEmitter('approval_required', $approvalPayload);
                    }

                    break 2; // Pause execution
                }

                // Variable Interpolation: resolve {{task_1.output.xxx}} or {{entities.xxx}}
                $resolvedArgs = $dag->resolveArguments($task['arguments']);

                // Idempotency Protection for Mutating Operations
                if ($task['is_mutating']) {
                    $idempToken = hash('sha256', "{$workflowId}:{$taskKey}:" . json_encode($resolvedArgs));
                    // Check if already executed in DB
                    if ($db) {
                        try {
                            $stmtCheck = $db->prepare("SELECT state, output_json FROM ai_agent_workflow_tasks WHERE workflow_id = ? AND task_key = ? AND state = 'completed' LIMIT 1");
                            $stmtCheck->execute([$workflowId, $taskKey]);
                            $existingRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                            if ($existingRow) {
                                $existingOutput = json_decode($existingRow['output_json'] ?? '{}', true) ?: [];
                                $dag->updateNode($taskKey, [
                                    'state' => 'completed',
                                    'output' => $existingOutput,
                                    'verified' => true
                                ]);
                                continue;
                            }
                        } catch (Throwable $e) {}
                    }
                }

                // Transition node to running
                $taskStartMs = microtime(true);
                $dag->updateNode($taskKey, [
                    'state' => 'running',
                    'started_at' => date('Y-m-d H:i:s'),
                    'arguments' => $resolvedArgs
                ]);
                self::persistTaskState($workflowId, $dag->getNode($taskKey), $db);

                if ($streamEmitter) {
                    $streamEmitter('task_start', [
                        'workflow_id' => $workflowId,
                        'task_key' => $taskKey,
                        'tool_id' => $toolId,
                        'title' => $task['title'],
                        'arguments' => $resolvedArgs
                    ]);
                }

                // Execute tool via PluginRegistry
                $toolResult = [];
                $executionError = null;
                $maxRetries = (int)$task['max_retries'];
                $retryCount = 0;

                while ($retryCount <= $maxRetries) {
                    try {
                        $toolResult = PluginRegistry::executeTool($toolId, $userId, $resolvedArgs, $db);
                        if (isset($toolResult['error']) && !empty($toolResult['error'])) {
                            $executionError = $toolResult['error'];
                        } else {
                            $executionError = null;
                            break;
                        }
                    } catch (Throwable $e) {
                        $executionError = $e->getMessage();
                        $toolResult = ['error' => $executionError];
                    }

                    // Mutating operations NEVER blindly retry
                    if ($task['is_mutating']) {
                        break;
                    }

                    $retryCount++;
                    if ($retryCount <= $maxRetries) {
                        usleep(150000); // 150ms backoff
                    }
                }

                $taskDurationMs = (int)round((microtime(true) - $taskStartMs) * 1000);

                // Deep State Verification (Requirement 7)
                $verification = VerificationEngine::verifyResult($toolId, $userId, $resolvedArgs, $toolResult, $db);

                $finalState = ($executionError === null && $verification['verified']) ? 'completed' : 'failed';

                // Update node state
                $dag->updateNode($taskKey, [
                    'state' => $finalState,
                    'output' => $toolResult,
                    'error' => $executionError ?: ($verification['verified'] ? null : $verification['note']),
                    'verified' => $verification['verified'],
                    'verification_note' => $verification['note'],
                    'retry_count' => $retryCount,
                    'execution_time_ms' => $taskDurationMs,
                    'completed_at' => date('Y-m-d H:i:s')
                ]);

                // Merge any newly produced entities into DAG and Workflow
                $extractedEntities = self::extractEntitiesFromResult($toolId, $toolResult, $resolvedArgs);
                if (!empty($extractedEntities)) {
                    $dag->setEntities($extractedEntities);
                    if ($db) {
                        WorkflowEngine::updateEntities($workflowId, $extractedEntities, $db);
                    }
                }

                // Persist task state to DB
                self::persistTaskState($workflowId, $dag->getNode($taskKey), $db);

                $executedSteps[] = $dag->getNode($taskKey);

                if ($streamEmitter) {
                    $streamEmitter(($finalState === 'completed') ? 'task_complete' : 'task_failed', [
                        'workflow_id' => $workflowId,
                        'task_key' => $taskKey,
                        'tool_id' => $toolId,
                        'status' => $finalState,
                        'result' => $toolResult,
                        'verified' => $verification['verified'],
                        'verification_note' => $verification['note'],
                        'duration_ms' => $taskDurationMs
                    ]);
                }
            }

            // Persist full DAG snapshot after each batch
            self::persistDAGState($workflowId, $dag, $db);
        }

        // Release execution lock
        WorkflowEngine::releaseLock($workflowId, $userId, $db);

        // Finalize Workflow state machine status
        $finalWorkflowState = WorkflowEngine::STATE_COMPLETED;
        if ($approvalPayload) {
            $finalWorkflowState = WorkflowEngine::STATE_WAITING_FOR_APPROVAL;
        } elseif ($dag->hasFailures()) {
            $finalWorkflowState = WorkflowEngine::STATE_FAILED;
        }

        try {
            WorkflowEngine::transitionState($workflowId, $finalWorkflowState, null, $dag->getEntities(), 'DAG execution completed', $db);
        } catch (Throwable $e) {}

        if ($streamEmitter && empty($approvalPayload)) {
            $streamEmitter('workflow_complete', [
                'workflow_id' => $workflowId,
                'status' => $finalWorkflowState,
                'total_executed' => count($executedSteps),
                'entities' => $dag->getEntities()
            ]);
        }

        return [
            'workflow_id' => $workflowId,
            'status' => $finalWorkflowState,
            'is_completed' => $dag->isCompleted(),
            'executed_tasks' => $executedSteps,
            'dag' => $dag->toArray(),
            'entities' => $dag->getEntities(),
            'approval_required' => $approvalPayload
        ];
    }

    /**
     * Resumes an interrupted workflow or one awaiting Maker-Checker human approval.
     */
    public static function resumeAfterApproval(
        string $workflowId,
        string $taskKey,
        int $userId,
        array $approvalOptions = [],
        ?callable $streamEmitter = null,
        ?PDO $db = null
    ): array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) {}
        }

        $workflow = WorkflowEngine::getWorkflow($workflowId, $userId, $db);
        if (!$workflow) {
            throw new Exception("Workflow '{$workflowId}' not found.");
        }

        $dag = self::loadDAGFromWorkflow($workflow, $db);
        $task = $dag->getNode($taskKey);
        if (!$task) {
            throw new Exception("Task '{$taskKey}' not found in workflow DAG.");
        }

        // Validate approval token if provided
        $token = $approvalOptions['approval_token'] ?? null;
        if ($token && !empty($task['approval_token']) && $token !== $task['approval_token']) {
            throw new Exception("Invalid approval token for task '{$taskKey}'.");
        }

        // Mark task approved
        $dag->updateNode($taskKey, [
            'state' => 'pending', // Set to pending so it runs immediately
            'approval_status' => 'approved',
            'requires_approval' => false
        ]);
        self::persistTaskState($workflowId, $dag->getNode($taskKey), $db);

        // Resume execution
        $approvalOptions['approved_task_key'] = $taskKey;
        $approvalOptions['skip_lock_check'] = true;

        return self::executeDAG($dag, $userId, $workflow['conversation_id'], $workflowId, $streamEmitter, $db, $approvalOptions);
    }

    /**
     * Rejects/cancels a task waiting for approval.
     */
    public static function rejectTask(
        string $workflowId,
        string $taskKey,
        int $userId,
        string $reason = 'Cancelled by user',
        ?callable $streamEmitter = null,
        ?PDO $db = null
    ): array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) {}
        }

        $workflow = WorkflowEngine::getWorkflow($workflowId, $userId, $db);
        if (!$workflow) throw new Exception("Workflow not found.");

        $dag = self::loadDAGFromWorkflow($workflow, $db);
        $dag->updateNode($taskKey, [
            'state' => 'skipped',
            'approval_status' => 'rejected',
            'error' => $reason
        ]);
        self::persistTaskState($workflowId, $dag->getNode($taskKey), $db);

        // Cancel workflow
        WorkflowEngine::transitionState($workflowId, WorkflowEngine::STATE_CANCELLED, $taskKey, [], $reason, $db);
        self::persistDAGState($workflowId, $dag, $db);

        return [
            'workflow_id' => $workflowId,
            'status' => 'cancelled',
            'task_key' => $taskKey,
            'message' => $reason
        ];
    }

    /**
     * Recovers an interrupted workflow after a server restart or crash.
     */
    public static function recoverWorkflow(string $workflowId, int $userId, ?PDO $db = null): array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) {}
        }
        $workflow = WorkflowEngine::getWorkflow($workflowId, $userId, $db);
        if (!$workflow) {
            return ['status' => 'not_found', 'message' => 'Workflow not found.'];
        }

        $dag = self::loadDAGFromWorkflow($workflow, $db);

        // Reset any task left in 'running' to 'pending' if it was not mutating
        $recoveredTasks = 0;
        foreach ($dag->getNodes() as $k => $node) {
            if ($node['state'] === 'running') {
                if (!$node['is_mutating']) {
                    $dag->updateNode($k, ['state' => 'pending']);
                    $recoveredTasks++;
                } else {
                    $dag->updateNode($k, ['state' => 'failed', 'error' => 'Interrupted during mutating execution. Human check required.']);
                }
                self::persistTaskState($workflowId, $dag->getNode($k), $db);
            }
        }

        // Record recovery in DB
        if ($db) {
            try {
                $now = date('Y-m-d H:i:s');
                $stmt = $db->prepare("UPDATE ai_agent_workflows SET recovered_at = ?, lock_token = NULL, lock_expires_at = NULL WHERE id = ?");
                $stmt->execute([$now, $workflowId]);
            } catch (Throwable $e) {}
        }

        self::persistDAGState($workflowId, $dag, $db);

        return [
            'status' => 'recovered',
            'workflow_id' => $workflowId,
            'recovered_tasks' => $recoveredTasks,
            'is_completed' => $dag->isCompleted(),
            'dag' => $dag->toArray()
        ];
    }

    /**
     * Recovers all interrupted active workflows for a user.
     */
    public static function recoverAllInterruptedWorkflows(int $userId, ?PDO $db = null): int {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return 0; }
        }
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare("
                SELECT id FROM ai_agent_workflows 
                WHERE user_id = ? AND state = 'running' 
                  AND (lock_expires_at IS NULL OR lock_expires_at <= ?)
            ");
            $stmt->execute([$userId, $now]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $recovered = 0;
            foreach ($ids as $wfId) {
                self::recoverWorkflow($wfId, $userId, $db);
                $recovered++;
            }
            return $recovered;
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Loads a TaskPlanDAG from workflow records and ai_agent_workflow_tasks table.
     */
    public static function loadDAGFromWorkflow(array $workflow, ?PDO $db = null): TaskPlanDAG {
        $wfId = $workflow['id'];
        $entities = $workflow['entities'] ?? [];

        // Check if discrete tasks exist in ai_agent_workflow_tasks
        if ($db) {
            try {
                $stmt = $db->prepare("SELECT * FROM ai_agent_workflow_tasks WHERE workflow_id = ? ORDER BY created_at ASC");
                $stmt->execute([$wfId]);
                $taskRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($taskRows)) {
                    $nodes = [];
                    foreach ($taskRows as $row) {
                        $nodes[] = [
                            'task_key' => $row['task_key'],
                            'tool_id' => $row['tool_id'],
                            'title' => $row['title'],
                            'dependencies' => json_decode($row['dependencies_json'] ?? '[]', true) ?: [],
                            'arguments' => json_decode($row['arguments_json'] ?? '[]', true) ?: [],
                            'output' => json_decode($row['output_json'] ?? '[]', true) ?: null,
                            'state' => $row['state'],
                            'is_mutating' => (bool)$row['is_mutating'],
                            'requires_approval' => (bool)$row['requires_approval'],
                            'approval_token' => $row['approval_token'],
                            'approval_status' => $row['approval_status'],
                            'verified' => (bool)$row['verified_state'],
                            'verification_note' => $row['verification_note'],
                            'retry_count' => (int)$row['retry_count'],
                            'max_retries' => (int)$row['max_retries'],
                            'error' => $row['error_message'],
                            'execution_time_ms' => (int)$row['execution_time_ms'],
                            'started_at' => $row['started_at'],
                            'completed_at' => $row['completed_at']
                        ];
                    }
                    return new TaskPlanDAG($nodes, $entities);
                }
            } catch (Throwable $e) {}
        }

        // Fallback to plan_json in ai_agent_workflows
        $planJson = $workflow['plan_json'] ?? null;
        if (!empty($planJson)) {
            $decoded = json_decode($planJson, true);
            if (!empty($decoded['nodes'])) {
                return TaskPlanDAG::fromArray($decoded);
            }
        }

        return new TaskPlanDAG([], $entities);
    }

    /**
     * Persists the entire DAG snapshot to ai_agent_workflows.plan_json.
     */
    private static function persistDAGState(string $workflowId, TaskPlanDAG $dag, ?PDO $db = null): void {
        if (!$db) return;
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare("UPDATE ai_agent_workflows SET plan_json = ?, entities_json = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([
                json_encode($dag->toArray()),
                json_encode($dag->getEntities()),
                $now,
                $workflowId
            ]);
        } catch (Throwable $e) {}
    }

    /**
     * Persists a single task state to ai_agent_workflow_tasks.
     */
    private static function persistTaskState(string $workflowId, ?array $task, ?PDO $db = null): void {
        if (!$db || !$task) return;
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            $taskId = 'task_' . bin2hex(random_bytes(10));
            $now = date('Y-m-d H:i:s');

            if ($driver === 'sqlite') {
                $sql = "
                    INSERT OR REPLACE INTO ai_agent_workflow_tasks (
                        id, workflow_id, task_key, tool_id, title, dependencies_json, arguments_json,
                        output_json, state, is_mutating, requires_approval, approval_token,
                        approval_status, verified_state, verification_note, retry_count,
                        max_retries, error_message, execution_time_ms, started_at, completed_at
                    ) VALUES (
                        COALESCE((SELECT id FROM ai_agent_workflow_tasks WHERE workflow_id = ? AND task_key = ?), ?),
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $workflowId, $task['task_key'], $taskId,
                    $workflowId,
                    $task['task_key'],
                    $task['tool_id'],
                    $task['title'],
                    json_encode($task['dependencies'] ?? []),
                    json_encode($task['arguments'] ?? []),
                    json_encode($task['output'] ?? []),
                    $task['state'],
                    $task['is_mutating'] ? 1 : 0,
                    $task['requires_approval'] ? 1 : 0,
                    $task['approval_token'] ?? null,
                    $task['approval_status'] ?? null,
                    $task['verified'] ? 1 : 0,
                    $task['verification_note'] ?? null,
                    (int)($task['retry_count'] ?? 0),
                    (int)($task['max_retries'] ?? 0),
                    $task['error'] ?? null,
                    (int)($task['execution_time_ms'] ?? 0),
                    $task['started_at'] ?? null,
                    $task['completed_at'] ?? null
                ]);
            } else {
                $sql = "
                    INSERT INTO ai_agent_workflow_tasks (
                        id, workflow_id, task_key, tool_id, title, dependencies_json, arguments_json,
                        output_json, state, is_mutating, requires_approval, approval_token,
                        approval_status, verified_state, verification_note, retry_count,
                        max_retries, error_message, execution_time_ms, started_at, completed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        state = VALUES(state),
                        arguments_json = VALUES(arguments_json),
                        output_json = VALUES(output_json),
                        approval_token = VALUES(approval_token),
                        approval_status = VALUES(approval_status),
                        verified_state = VALUES(verified_state),
                        verification_note = VALUES(verification_note),
                        retry_count = VALUES(retry_count),
                        error_message = VALUES(error_message),
                        execution_time_ms = VALUES(execution_time_ms),
                        started_at = VALUES(started_at),
                        completed_at = VALUES(completed_at)
                ";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $taskId,
                    $workflowId,
                    $task['task_key'],
                    $task['tool_id'],
                    $task['title'],
                    json_encode($task['dependencies'] ?? []),
                    json_encode($task['arguments'] ?? []),
                    json_encode($task['output'] ?? []),
                    $task['state'],
                    $task['is_mutating'] ? 1 : 0,
                    $task['requires_approval'] ? 1 : 0,
                    $task['approval_token'] ?? null,
                    $task['approval_status'] ?? null,
                    $task['verified'] ? 1 : 0,
                    $task['verification_note'] ?? null,
                    (int)($task['retry_count'] ?? 0),
                    (int)($task['max_retries'] ?? 0),
                    $task['error'] ?? null,
                    (int)($task['execution_time_ms'] ?? 0),
                    $task['started_at'] ?? null,
                    $task['completed_at'] ?? null
                ]);
            }
        } catch (Throwable $e) {}
    }

    /**
     * Marks remaining unexecuted tasks as skipped when execution halts.
     */
    private static function failRemainingTasks(TaskPlanDAG $dag, string $reason, string $workflowId, ?PDO $db = null): void {
        foreach ($dag->getNodes() as $k => $node) {
            if ($node['state'] === 'pending' || $node['state'] === 'running') {
                $dag->updateNode($k, [
                    'state' => 'skipped',
                    'error' => $reason
                ]);
                self::persistTaskState($workflowId, $dag->getNode($k), $db);
            }
        }
        self::persistDAGState($workflowId, $dag, $db);
    }

    /**
     * Extracts business entities from tool results.
     */
    private static function extractEntitiesFromResult(string $toolId, array $result, array $arguments): array {
        $entities = [];
        if (!empty($result['meet_link'])) {
            $entities['meet_link'] = $result['meet_link'];
        }
        if (!empty($result['download_url'])) {
            $entities['file_url'] = $result['download_url'];
            $entities['filename'] = $arguments['filename'] ?? ($result['filename'] ?? 'file');
        }
        if (!empty($result['contact']['id'])) {
            $entities['contact_id'] = $result['contact']['id'];
            $entities['contact_name'] = trim(($result['contact']['first_name'] ?? '') . ' ' . ($result['contact']['last_name'] ?? ''));
            $entities['contact_email'] = $result['contact']['email'] ?? '';
        } elseif (!empty($result['id']) && $toolId === 'create_contact') {
            $entities['contact_id'] = $result['id'];
        }
        if (!empty($result['lead']['id'])) {
            $entities['lead_id'] = $result['lead']['id'];
        }
        if (!empty($result['deal']['id'])) {
            $entities['deal_id'] = $result['deal']['id'];
        }
        if (!empty($result['task']['id'])) {
            $entities['task_id'] = $result['task']['id'];
        }
        if (!empty($arguments['recipient'])) {
            $entities['recipient_email'] = $arguments['recipient'];
        }
        return $entities;
    }
}
