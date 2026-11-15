<?php
// backend/agent/TaskPlanDAG.php
// Autonomous Agent Engine 2.0: Directed Acyclic Graph (DAG) Task Representation & Dependency Graph.
// Enforces acyclic validation, topological sorting, dependency resolution, variable interpolation,
// and state tracking for multi-step autonomous execution.

class InvalidDAGPlanException extends Exception {}

class TaskPlanDAG {

    private array $nodes = [];
    private array $workflowEntities = [];

    /**
     * Constructs a DAG from an array of node definitions.
     */
    public function __construct(array $nodes = [], array $initialEntities = []) {
        $this->workflowEntities = $initialEntities;
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
        if (!empty($this->nodes)) {
            $this->validateDAG();
        }
    }

    /**
     * Adds a task node to the DAG.
     */
    public function addNode(array $node): void {
        $key = $node['task_key'] ?? ($node['id'] ?? '');
        if (empty($key)) {
            throw new InvalidDAGPlanException("Task node missing 'task_key'.");
        }
        $toolId = $node['tool_id'] ?? '';
        if (empty($toolId)) {
            throw new InvalidDAGPlanException("Task node '{$key}' missing 'tool_id'.");
        }

        $isMutating = isset($node['is_mutating']) ? (bool)$node['is_mutating'] : self::isToolMutating($toolId);
        $requiresApproval = isset($node['requires_approval']) ? (bool)$node['requires_approval'] : self::isToolApprovalRequired($toolId);

        $this->nodes[$key] = [
            'task_key' => $key,
            'tool_id' => $toolId,
            'title' => $node['title'] ?? ucfirst(str_replace('_', ' ', $toolId)),
            'dependencies' => isset($node['dependencies']) && is_array($node['dependencies']) ? array_values(array_unique($node['dependencies'])) : [],
            'arguments' => $node['arguments'] ?? [],
            'is_mutating' => $isMutating,
            'requires_approval' => $requiresApproval,
            'max_retries' => isset($node['max_retries']) ? (int)$node['max_retries'] : ($isMutating ? 0 : 2),
            'retry_count' => (int)($node['retry_count'] ?? 0),
            'state' => $node['state'] ?? 'pending',
            'output' => $node['output'] ?? null,
            'error' => $node['error'] ?? null,
            'verified' => !empty($node['verified']),
            'verification_note' => $node['verification_note'] ?? null,
            'started_at' => $node['started_at'] ?? null,
            'completed_at' => $node['completed_at'] ?? null,
            'execution_time_ms' => (int)($node['execution_time_ms'] ?? 0),
            'approval_token' => $node['approval_token'] ?? null
        ];
    }

    /**
     * Validates that the graph is a valid DAG (no cycles, no missing dependencies).
     */
    public function validateDAG(): void {
        $keys = array_keys($this->nodes);

        // 1. Verify all dependencies exist in the graph
        foreach ($this->nodes as $key => $node) {
            foreach ($node['dependencies'] as $dep) {
                if (!in_array($dep, $keys, true)) {
                    throw new InvalidDAGPlanException("Task '{$key}' depends on non-existent task '{$dep}'.");
                }
                if ($dep === $key) {
                    throw new InvalidDAGPlanException("Task '{$key}' cannot depend on itself (self-dependency cycle).");
                }
            }
        }

        // 2. Cycle detection via Kahn's algorithm (Topological sort)
        $inDegree = [];
        $adj = [];
        foreach ($keys as $k) {
            $inDegree[$k] = 0;
            $adj[$k] = [];
        }

        foreach ($this->nodes as $k => $node) {
            $inDegree[$k] = count($node['dependencies']);
            foreach ($node['dependencies'] as $dep) {
                $adj[$dep][] = $k;
            }
        }

        $queue = [];
        foreach ($keys as $k) {
            if ($inDegree[$k] === 0) {
                $queue[] = $k;
            }
        }

        $visitedCount = 0;
        while (!empty($queue)) {
            $curr = array_shift($queue);
            $visitedCount++;

            foreach ($adj[$curr] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if ($visitedCount !== count($keys)) {
            throw new InvalidDAGPlanException("Circular dependency cycle detected in task execution graph.");
        }
    }

    /**
     * Retrieves all task nodes.
     */
    public function getNodes(): array {
        return $this->nodes;
    }

    /**
     * Retrieves a single task node.
     */
    public function getNode(string $taskKey): ?array {
        return $this->nodes[$taskKey] ?? null;
    }

    /**
     * Updates a task node in memory.
     */
    public function updateNode(string $taskKey, array $updates): void {
        if (!isset($this->nodes[$taskKey])) return;
        $this->nodes[$taskKey] = array_merge($this->nodes[$taskKey], $updates);
    }

    /**
     * Returns tasks that are currently 'pending' and whose dependencies are 100% 'completed'.
     */
    public function getReadyTasks(): array {
        $ready = [];
        foreach ($this->nodes as $key => $node) {
            if ($node['state'] !== 'pending') {
                continue;
            }

            // Check if all dependencies are 'completed'
            $depsSatisfied = true;
            foreach ($node['dependencies'] as $depKey) {
                $depNode = $this->nodes[$depKey] ?? null;
                if (!$depNode || $depNode['state'] !== 'completed') {
                    $depsSatisfied = false;
                    break;
                }
            }

            if ($depsSatisfied) {
                $ready[] = $node;
            }
        }
        return $ready;
    }

    /**
     * Returns true if any dependency of a task has failed or was skipped.
     */
    public function hasFailedDependency(string $taskKey): ?string {
        $node = $this->nodes[$taskKey] ?? null;
        if (!$node) return null;

        foreach ($node['dependencies'] as $depKey) {
            $depNode = $this->nodes[$depKey] ?? null;
            if ($depNode && ($depNode['state'] === 'failed' || $depNode['state'] === 'skipped')) {
                return $depKey;
            }
        }
        return null;
    }

    /**
     * Groups tasks into parallel execution batches using topological leveling.
     * Each batch contains tasks that are mutually independent and can safely run in parallel.
     */
    public function getExecutionBatches(): array {
        $this->validateDAG();
        $batches = [];
        $completed = [];
        $remaining = $this->nodes;

        while (!empty($remaining)) {
            $currentBatch = [];
            foreach ($remaining as $key => $node) {
                $depsSatisfied = true;
                foreach ($node['dependencies'] as $dep) {
                    if (!isset($completed[$dep])) {
                        $depsSatisfied = false;
                        break;
                    }
                }
                if ($depsSatisfied) {
                    $currentBatch[] = $node;
                }
            }

            if (empty($currentBatch)) {
                break; // Should not happen after cycle check
            }

            $batches[] = $currentBatch;
            foreach ($currentBatch as $bNode) {
                $completed[$bNode['task_key']] = true;
                unset($remaining[$bNode['task_key']]);
            }
        }

        return $batches;
    }

    /**
     * Interpolates dynamic variables from completed task outputs and active workflow entities.
     * Supports:
     * - `{{task_1.contacts.0.email}}`
     * - `{{task_1.output.meet_link}}`
     * - `{{entities.meet_link}}`
     * - `{{entities.contact_id}}`
     */
    public function resolveArguments(array $arguments): array {
        return $this->interpolateRecursive($arguments);
    }

    private function interpolateRecursive($data) {
        if (is_array($data)) {
            $res = [];
            foreach ($data as $k => $v) {
                $res[$k] = $this->interpolateRecursive($v);
            }
            return $res;
        }

        if (is_string($data) && str_contains($data, '{{')) {
            return preg_replace_callback('/\{\{([a-zA-Z0-9_\.]+)\}\}/', function($matches) use ($data) {
                $path = $matches[1];
                $val = $this->lookupPathValue($path);
                if ($val !== null) {
                    return is_scalar($val) ? (string)$val : json_encode($val);
                }
                return $matches[0]; // Retain placeholder if unresolved
            }, $data);
        }

        return $data;
    }

    /**
     * Looks up a dot-notated variable path across completed tasks or workflow entities.
     */
    private function lookupPathValue(string $path) {
        $parts = explode('.', $path);
        $root = array_shift($parts);

        // Case 1: {{entities.xyz}}
        if ($root === 'entities') {
            return self::extractNestedValue($this->workflowEntities, $parts);
        }

        // Case 2: {{task_key.output.field}} or {{task_key.field}}
        if (isset($this->nodes[$root])) {
            $taskNode = $this->nodes[$root];
            $output = $taskNode['output'] ?? [];
            if (!empty($parts) && $parts[0] === 'output') {
                array_shift($parts);
            }
            return self::extractNestedValue($output, $parts);
        }

        return null;
    }

    private static function extractNestedValue($data, array $parts) {
        if (empty($parts)) return $data;
        $curr = $data;
        foreach ($parts as $p) {
            if (is_array($curr)) {
                if (array_key_exists($p, $curr)) {
                    $curr = $curr[$p];
                } elseif (is_numeric($p) && isset($curr[(int)$p])) {
                    $curr = $curr[(int)$p];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        return $curr;
    }

    /**
     * Sets or merges workflow entities.
     */
    public function setEntities(array $entities): void {
        $this->workflowEntities = array_merge($this->workflowEntities, $entities);
    }

    public function getEntities(): array {
        return $this->workflowEntities;
    }

    /**
     * Checks if all tasks in the DAG are in a terminal state.
     */
    public function isCompleted(): bool {
        foreach ($this->nodes as $node) {
            if (in_array($node['state'], ['pending', 'running', 'waiting_for_approval'], true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if any task in the DAG has failed.
     */
    public function hasFailures(): bool {
        foreach ($this->nodes as $node) {
            if ($node['state'] === 'failed') return true;
        }
        return false;
    }

    /**
     * Serializes DAG into array format for storage.
     */
    public function toArray(): array {
        return [
            'nodes' => array_values($this->nodes),
            'entities' => $this->workflowEntities,
            'node_count' => count($this->nodes),
            'is_completed' => $this->isCompleted()
        ];
    }

    /**
     * Deserializes DAG from stored array.
     */
    public static function fromArray(array $data): self {
        $nodes = $data['nodes'] ?? [];
        $entities = $data['entities'] ?? [];
        return new self($nodes, $entities);
    }

    /**
     * Deterministic classification of tool mutating behavior.
     */
    public static function isToolMutating(string $toolId): bool {
        $mutatingTools = [
            'create_contact', 'update_contact', 'archive_contact', 'add_contact_note', 'add_contact_tag',
            'create_lead',
            'create_deal', 'move_deal_stage',
            'create_task', 'complete_task',
            'create_meeting',
            'send_email',
            'send_whatsapp_message',
            'generate_export_file',
            'toggle_automation'
        ];
        return in_array($toolId, $mutatingTools, true);
    }

    /**
     * Deterministic classification of sensitive tools requiring Maker-Checker confirmation.
     */
    public static function isToolApprovalRequired(string $toolId): bool {
        $sensitiveTools = [
            'send_email',
            'send_whatsapp_message',
            'archive_contact',
            'toggle_automation'
        ];
        return in_array($toolId, $sensitiveTools, true);
    }
}
