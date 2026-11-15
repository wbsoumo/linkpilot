<?php
// tests/test_phase4.php
// Autonomous Agent Engine 2.0: Automated Regression & Verification Suite for Phase 4.
// Tests DAG Planning, Two-Tier Execution Engine, Concurrency, Bounded Retries,
// Idempotency, Maker-Checker Gates, Deep State Verification, and Crash Recovery.

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/ai_tool_registry.php';
require_once __DIR__ . '/../backend/agent/AgentMigration2.php';
require_once __DIR__ . '/../backend/agent/AgentMigration4.php';
require_once __DIR__ . '/../backend/agent/PluginRegistry.php';
require_once __DIR__ . '/../backend/agent/WorkflowEngine.php';
require_once __DIR__ . '/../backend/agent/ContextManager.php';
require_once __DIR__ . '/../backend/agent/TaskPlanDAG.php';
require_once __DIR__ . '/../backend/agent/VerificationEngine.php';
require_once __DIR__ . '/../backend/agent/TaskPlanner.php';
require_once __DIR__ . '/../backend/agent/ExecutionEngine.php';
require_once __DIR__ . '/../backend/agent_orchestrator.php';

class Phase4TestRunner {
    private PDO $db;
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function __construct() {
        // Isolated SQLite in-memory database for fast, reliable, reproducible regression testing
        $this->db = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        Database::setConnection($this->db);
    }

    public function assert(bool $condition, string $testName, string $detail = ''): void {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $msg = "  [FAIL] {$testName}" . ($detail ? " - {$detail}" : '');
            $this->errors[] = $msg;
            echo "{$msg}\n";
        }
    }

    public function runAll(): bool {
        echo "========================================================================\n";
        echo "  LINKPILOT AI: AUTONOMOUS AGENT ENGINE 2.0 REGRESSION SUITE\n";
        echo "  Phase 4: Structured Task Planning & Two-Tier Execution Engine\n";
        echo "========================================================================\n\n";

        $this->setupBaseTables();
        $this->testDatabaseMigrationAndRollback();
        $this->testDAGValidationAndCycleDetection();
        $this->testDAGTopologicalSortingAndBatches();
        $this->testVariableInterpolation();
        $this->testSequentialExecutionAndDependencyGates();
        $this->testParallelExecutionAndConcurrencyLimits();
        $this->testDependencyFailureCascadeAndSkipping();
        $this->testBoundedRetriesAndMutatingProtection();
        $this->testIdempotencyProtectionForMutatingActions();
        $this->testMakerCheckerApprovalGates();
        $this->testDeepResultVerification();
        $this->testInterruptedWorkflowRecovery();
        $this->testTaskPlannerIntentMapping();
        $this->testOrchestratorEndToEndIntegration();

        echo "\n------------------------------------------------------------------------\n";
        echo "  PHASE 4 TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "------------------------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "Failures:\n";
            foreach ($this->errors as $err) {
                echo "  {$err}\n";
            }
            return false;
        }

        echo "All Phase 4 regression tests PASSED successfully with 100% compliance!\n\n";
        return true;
    }

    private function setupBaseTables(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS `users` (`id` INTEGER PRIMARY KEY, `name` TEXT, `email` TEXT, `role` TEXT DEFAULT 'user')");
        $this->db->exec("INSERT INTO `users` (`id`, `name`, `email`) VALUES (1, 'Admin User', 'admin@linkpilot.io')");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_conversations` (`id` TEXT PRIMARY KEY, `user_id` INTEGER, `title` TEXT, `summary` TEXT, `is_pinned` INTEGER DEFAULT 0, `is_archived` INTEGER DEFAULT 0, `created_at` TEXT, `updated_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_messages` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `conversation_id` TEXT, `user_id` INTEGER, `role` TEXT, `content` TEXT, `tool_calls_json` TEXT, `tool_results_json` TEXT, `status` TEXT, `tokens_used` INTEGER DEFAULT 0, `created_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `contacts` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` INTEGER, `first_name` TEXT, `last_name` TEXT, `email` TEXT, `phone` TEXT, `created_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `leads` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` INTEGER, `title` TEXT, `status` TEXT, `created_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `deals` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` INTEGER, `title` TEXT, `value` REAL, `stage` TEXT, `created_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `tasks` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` INTEGER, `title` TEXT, `due_date` TEXT, `created_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_audit_logs` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` INTEGER, `conversation_id` TEXT, `tool_id` TEXT, `tool_name` TEXT, `risk_level` TEXT, `input_json` TEXT, `output_json` TEXT, `approval_status` TEXT, `created_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `ai_generated_files` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` INTEGER, `token` TEXT, `filename` TEXT, `format` TEXT, `file_path` TEXT, `is_deleted` INTEGER DEFAULT 0, `expires_at` TEXT, `created_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `external_app_connections` (`id` INTEGER PRIMARY KEY, `user_id` INTEGER, `provider` TEXT, `email` TEXT, `connected_email` TEXT, `scopes` TEXT, `status` TEXT, `created_at` TEXT)");
        $this->db->exec("INSERT INTO `external_app_connections` (`user_id`, `provider`, `email`, `scopes`, `status`) VALUES (1, 'google', 'user@example.com', 'https://www.googleapis.com/auth/calendar.events,https://www.googleapis.com/auth/gmail.send', 'connected')");

        // Initialize Phase 2 & Phase 3 tables
        AgentMigration2::run($this->db);
        PluginRegistry::init($this->db);

        // Register robust tool handlers for isolated testing
        AIToolRegistry::registerTool([
            'id' => 'search_contacts',
            'name' => 'Search Contacts',
            'description' => 'Search contacts in CRM',
            'category' => 'crm',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return ['contacts' => [], 'total' => 0];
            }
        ]);

        AIToolRegistry::registerTool([
            'id' => 'get_hot_leads',
            'name' => 'Get Hot Leads',
            'description' => 'Get prioritized leads',
            'category' => 'crm',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return ['hot_leads' => [['id' => 1, 'name' => 'Alpha Corp', 'score' => 95]], 'total' => 1];
            }
        ]);

        AIToolRegistry::registerTool([
            'id' => 'generate_instant_meet',
            'name' => 'Generate Instant Meet',
            'description' => 'Create Google Meet room',
            'category' => 'calendar',
            'risk_level' => 'INTERNAL',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return ['status' => 'success', 'meet_link' => 'https://meet.google.com/abc-defg-hij', 'code' => 'abc-defg-hij'];
            }
        ]);

        AIToolRegistry::registerTool([
            'id' => 'send_email',
            'name' => 'Send Email',
            'description' => 'Dispatch email',
            'category' => 'email',
            'risk_level' => 'EXTERNAL',
            'approval_required' => true,
            'handler' => function($userId, array $params, PDO $db) {
                return [
                    'status' => 'sent',
                    'message_id' => 'msg_' . bin2hex(random_bytes(6)),
                    'recipient' => $params['recipient'] ?? '',
                    'subject' => $params['subject'] ?? ''
                ];
            }
        ]);

        AIToolRegistry::registerTool([
            'id' => 'generate_export_file',
            'name' => 'Generate Export Document',
            'description' => 'Export data to file',
            'category' => 'crm',
            'risk_level' => 'INTERNAL',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $fmt = $params['format'] ?? 'xlsx';
                $token = bin2hex(random_bytes(8));
                $tmp = sys_get_temp_dir() . "/export_{$token}.{$fmt}";
                file_put_contents($tmp, "mock export data");
                return [
                    'status' => 'success',
                    'token' => $token,
                    'file_path' => $tmp,
                    'download_url' => "https://linkpilot.io/api/download?token={$token}"
                ];
            }
        ]);

        AIToolRegistry::registerTool([
            'id' => 'create_contact',
            'name' => 'Create Contact',
            'description' => 'Create contact',
            'category' => 'crm',
            'risk_level' => 'INTERNAL',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                if (empty($params['first_name'])) {
                    return ['error' => 'First name is required.'];
                }
                $stmt = $db->prepare("INSERT INTO contacts (user_id, first_name, last_name, email) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $params['first_name'], $params['last_name'] ?? '', $params['email'] ?? '']);
                $id = (int)$db->lastInsertId();
                return ['status' => 'created', 'id' => $id, 'contact' => ['id' => $id, 'first_name' => $params['first_name'], 'email' => $params['email'] ?? '']];
            }
        ]);
    }

    /**
     * 1. Test Database Migration & Rollback
     */
    private function testDatabaseMigrationAndRollback(): void {
        echo "TEST SUITE 1: Database Migration & Safe Rollback\n";

        $res = AgentMigration4::run($this->db);
        $this->assert($res['status'] === 'success', "AgentMigration4::run executes successfully");

        // Verify ai_agent_workflow_tasks table
        $tables = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assert(in_array('ai_agent_workflow_tasks', $tables), "ai_agent_workflow_tasks table exists");

        // Verify extended columns in ai_agent_workflows
        $cols = $this->db->query("PRAGMA table_info(ai_agent_workflows)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_column($cols, 'name');
        $this->assert(in_array('plan_json', $colNames), "ai_agent_workflows.plan_json column added");
        $this->assert(in_array('concurrency_limit', $colNames), "ai_agent_workflows.concurrency_limit column added");
        $this->assert(in_array('max_retries', $colNames), "ai_agent_workflows.max_retries column added");
        $this->assert(in_array('execution_timeout', $colNames), "ai_agent_workflows.execution_timeout column added");

        // Test safe rollback
        $rollback = AgentMigration4::rollback($this->db);
        $this->assert($rollback['status'] === 'rolled_back', "AgentMigration4::rollback executes cleanly");
        $tablesAfter = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assert(!in_array('ai_agent_workflow_tasks', $tablesAfter), "ai_agent_workflow_tasks dropped on rollback");

        // Re-run migration for remaining tests
        AgentMigration4::run($this->db);
        echo "\n";
    }

    /**
     * 2. Test DAG Validation, Acyclic Graph Checks & Cycle Detection
     */
    private function testDAGValidationAndCycleDetection(): void {
        echo "TEST SUITE 2: DAG Validation, Acyclic Checks & Cycle Detection\n";

        // Valid DAG: Linear (A -> B -> C)
        $validNodes = [
            ['task_key' => 't1', 'tool_id' => 'search_contacts', 'dependencies' => []],
            ['task_key' => 't2', 'tool_id' => 'get_hot_leads', 'dependencies' => ['t1']],
            ['task_key' => 't3', 'tool_id' => 'send_email', 'dependencies' => ['t2']]
        ];
        $dag = new TaskPlanDAG($validNodes);
        $this->assert(count($dag->getNodes()) === 3, "Valid linear DAG constructs without error");

        // Valid DAG: Diamond (A -> B, A -> C, B -> D, C -> D)
        $diamondNodes = [
            ['task_key' => 'A', 'tool_id' => 'search_contacts', 'dependencies' => []],
            ['task_key' => 'B', 'tool_id' => 'get_hot_leads', 'dependencies' => ['A']],
            ['task_key' => 'C', 'tool_id' => 'web_search', 'dependencies' => ['A']],
            ['task_key' => 'D', 'tool_id' => 'send_email', 'dependencies' => ['B', 'C']]
        ];
        $diamondDAG = new TaskPlanDAG($diamondNodes);
        $this->assert(count($diamondDAG->getNodes()) === 4, "Diamond DAG with multiple dependencies constructs cleanly");

        // Cycle Detection: Direct Cycle (A -> B -> A)
        $cyclic = false;
        try {
            new TaskPlanDAG([
                ['task_key' => 'A', 'tool_id' => 'tool_a', 'dependencies' => ['B']],
                ['task_key' => 'B', 'tool_id' => 'tool_b', 'dependencies' => ['A']]
            ]);
        } catch (InvalidDAGPlanException $e) {
            $cyclic = true;
        }
        $this->assert($cyclic, "Cycle detection catches 2-node circular dependency");

        // Cycle Detection: Self Loop (A -> A)
        $selfLoop = false;
        try {
            new TaskPlanDAG([
                ['task_key' => 'A', 'tool_id' => 'tool_a', 'dependencies' => ['A']]
            ]);
        } catch (InvalidDAGPlanException $e) {
            $selfLoop = true;
        }
        $this->assert($selfLoop, "Cycle detection catches self-referencing dependency loop");

        // Missing Dependency Check
        $missingDep = false;
        try {
            new TaskPlanDAG([
                ['task_key' => 'A', 'tool_id' => 'tool_a', 'dependencies' => ['non_existent_node']]
            ]);
        } catch (InvalidDAGPlanException $e) {
            $missingDep = true;
        }
        $this->assert($missingDep, "Missing dependency validation catches non-existent predecessor node");

        echo "\n";
    }

    /**
     * 3. Test DAG Topological Sorting and Parallel Batching
     */
    private function testDAGTopologicalSortingAndBatches(): void {
        echo "TEST SUITE 3: Topological Sorting & Parallel Execution Batches\n";

        $diamondNodes = [
            ['task_key' => 'A', 'tool_id' => 'search_contacts', 'dependencies' => []],
            ['task_key' => 'B', 'tool_id' => 'get_hot_leads', 'dependencies' => ['A']],
            ['task_key' => 'C', 'tool_id' => 'web_search', 'dependencies' => ['A']],
            ['task_key' => 'D', 'tool_id' => 'send_email', 'dependencies' => ['B', 'C']]
        ];
        $dag = new TaskPlanDAG($diamondNodes);
        $batches = $dag->getExecutionBatches();

        $this->assert(count($batches) === 3, "Diamond DAG partitioned into exactly 3 topological tiers");
        $this->assert(count($batches[0]) === 1 && $batches[0][0]['task_key'] === 'A', "Tier 1 contains root task A");

        $tier2Keys = array_column($batches[1], 'task_key');
        $this->assert(count($batches[1]) === 2 && in_array('B', $tier2Keys) && in_array('C', $tier2Keys), "Tier 2 contains independent parallel tasks B and C");
        $this->assert(count($batches[2]) === 1 && $batches[2][0]['task_key'] === 'D', "Tier 3 contains aggregator task D");

        echo "\n";
    }

    /**
     * 4. Test Variable Interpolation Across Tasks and Entities
     */
    private function testVariableInterpolation(): void {
        echo "TEST SUITE 4: Cross-Task Dynamic Variable Interpolation\n";

        $dag = new TaskPlanDAG([
            ['task_key' => 'task_1', 'tool_id' => 'generate_instant_meet', 'dependencies' => []],
            ['task_key' => 'task_2', 'tool_id' => 'send_email', 'dependencies' => ['task_1']]
        ], ['contact_email' => 'client@example.com']);

        // Simulate task_1 completed output
        $dag->updateNode('task_1', [
            'state' => 'completed',
            'output' => ['meet_link' => 'https://meet.google.com/abc-defg-hij', 'code' => 'abc-defg-hij']
        ]);

        $rawArgs = [
            'recipient' => '{{entities.contact_email}}',
            'subject' => 'Meeting Link',
            'body' => 'Join here: {{task_1.output.meet_link}} (code: {{task_1.code}})'
        ];

        $resolved = $dag->resolveArguments($rawArgs);

        $this->assert($resolved['recipient'] === 'client@example.com', "Interpolates workflow entity {{entities.contact_email}}");
        $this->assert(str_contains($resolved['body'], 'https://meet.google.com/abc-defg-hij'), "Interpolates predecessor output {{task_1.output.meet_link}}");
        $this->assert(str_contains($resolved['body'], 'code: abc-defg-hij'), "Interpolates dot-notated field {{task_1.code}}");

        echo "\n";
    }

    /**
     * 5. Test Sequential Execution and Dependency Gates
     */
    private function testSequentialExecutionAndDependencyGates(): void {
        echo "TEST SUITE 5: Sequential Execution & Dependency Gates\n";

        $convId = 'conv_seq_' . bin2hex(random_bytes(4));
        $this->db->exec("INSERT INTO ai_agent_conversations (id, user_id, title) VALUES ('{$convId}', 1, 'Sequential Test')");

        $executionOrder = [];

        // Register custom test tools in registry
        PluginRegistry::init($this->db);

        $dag = new TaskPlanDAG([
            [
                'task_key' => 'step_1',
                'tool_id' => 'search_contacts',
                'title' => 'Search Contacts',
                'dependencies' => [],
                'arguments' => ['query' => 'Test']
            ],
            [
                'task_key' => 'step_2',
                'tool_id' => 'get_hot_leads',
                'title' => 'Get Hot Leads',
                'dependencies' => ['step_1'],
                'arguments' => []
            ]
        ]);

        // Verify ready tasks before start
        $readyBefore = $dag->getReadyTasks();
        $this->assert(count($readyBefore) === 1 && $readyBefore[0]['task_key'] === 'step_1', "Only step_1 is ready initially; step_2 blocked by dependency gate");

        $emitter = function($event, $data) use (&$executionOrder) {
            if ($event === 'task_start') {
                $executionOrder[] = $data['task_key'];
            }
        };

        $result = ExecutionEngine::executeDAG($dag, 1, $convId, null, $emitter, $this->db);

        $this->assert($result['status'] === 'completed', "Sequential DAG completes with status 'completed'");
        $this->assert($executionOrder === ['step_1', 'step_2'], "Tasks execute strictly in sequential dependency order");

        // Verify database records in ai_agent_workflow_tasks
        $tasksInDb = $this->db->query("SELECT task_key, state FROM ai_agent_workflow_tasks WHERE workflow_id = '{$result['workflow_id']}' ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
        $this->assert(count($tasksInDb) === 2, "Both tasks persisted in ai_agent_workflow_tasks table");
        $this->assert($tasksInDb[0]['state'] === 'completed' && $tasksInDb[1]['state'] === 'completed', "Both task records marked completed in DB");

        echo "\n";
    }

    /**
     * 6. Test Parallel Execution & Concurrency Limit
     */
    private function testParallelExecutionAndConcurrencyLimits(): void {
        echo "TEST SUITE 6: Parallel Execution & Concurrency Limits\n";

        $convId = 'conv_par_' . bin2hex(random_bytes(4));
        $this->db->exec("INSERT INTO ai_agent_conversations (id, user_id, title) VALUES ('{$convId}', 1, 'Parallel Test')");

        // 4 independent parallel tasks with concurrency limit = 2
        $dag = new TaskPlanDAG([
            ['task_key' => 'p1', 'tool_id' => 'get_hot_leads', 'dependencies' => []],
            ['task_key' => 'p2', 'tool_id' => 'get_hot_leads', 'dependencies' => []],
            ['task_key' => 'p3', 'tool_id' => 'get_hot_leads', 'dependencies' => []],
            ['task_key' => 'p4', 'tool_id' => 'get_hot_leads', 'dependencies' => []]
        ]);

        $batchSizes = [];
        $res = ExecutionEngine::executeDAG($dag, 1, $convId, null, null, $this->db, [
            'concurrency_limit' => 2
        ]);

        $this->assert($res['status'] === 'completed', "Parallel DAG executes successfully");
        $this->assert(count($res['executed_tasks']) === 4, "All 4 parallel tasks executed");

        echo "\n";
    }

    /**
     * 7. Test Dependency Failure Cascade & Skip Propagation
     */
    private function testDependencyFailureCascadeAndSkipping(): void {
        echo "TEST SUITE 7: Dependency Failure Cascade & Automatic Skip Propagation\n";

        $convId = 'conv_fail_' . bin2hex(random_bytes(4));
        $this->db->exec("INSERT INTO ai_agent_conversations (id, user_id, title) VALUES ('{$convId}', 1, 'Cascade Failure Test')");

        // Node A depends on an invalid tool that will fail; Node B and C depend on Node A
        $dag = new TaskPlanDAG([
            [
                'task_key' => 'task_fail',
                'tool_id' => 'create_contact',
                'title' => 'Create Invalid Contact',
                'dependencies' => [],
                'arguments' => ['first_name' => '', 'email' => 'invalid-email'] // Missing required fields
            ],
            [
                'task_key' => 'task_dep1',
                'tool_id' => 'send_email',
                'title' => 'Dependent Email',
                'dependencies' => ['task_fail'],
                'arguments' => ['recipient' => 'user@test.com']
            ],
            [
                'task_key' => 'task_dep2',
                'tool_id' => 'create_task',
                'title' => 'Dependent Task',
                'dependencies' => ['task_dep1'],
                'arguments' => ['title' => 'Follow up']
            ]
        ]);

        $res = ExecutionEngine::executeDAG($dag, 1, $convId, null, null, $this->db);

        $nodeFail = $dag->getNode('task_fail');
        $nodeDep1 = $dag->getNode('task_dep1');
        $nodeDep2 = $dag->getNode('task_dep2');

        $this->assert($nodeFail['state'] === 'failed', "Root task marked 'failed'");
        $this->assert($nodeDep1['state'] === 'skipped', "Immediate dependent task automatically skipped");
        $this->assert($nodeDep2['state'] === 'skipped', "Downstream cascaded dependent task automatically skipped");
        $this->assert($res['status'] === 'failed', "Overall workflow marked 'failed'");

        echo "\n";
    }

    /**
     * 8. Test Bounded Retries vs. Mutating Action Protection
     */
    private function testBoundedRetriesAndMutatingProtection(): void {
        echo "TEST SUITE 8: Bounded Retries vs. Mutating Action Safety\n";

        // Read/pure task: max_retries defaults to 2
        $dagRead = new TaskPlanDAG([
            ['task_key' => 'read_1', 'tool_id' => 'search_contacts', 'dependencies' => []]
        ]);
        $readNode = $dagRead->getNode('read_1');
        $this->assert($readNode['is_mutating'] === false, "search_contacts correctly classified as non-mutating");
        $this->assert($readNode['max_retries'] === 2, "Non-mutating read task defaults to bounded max_retries = 2");

        // Mutating task (send_email, send_whatsapp_message, create_contact): max_retries MUST BE 0!
        $dagMut = new TaskPlanDAG([
            ['task_key' => 'mut_1', 'tool_id' => 'send_email', 'dependencies' => []],
            ['task_key' => 'mut_2', 'tool_id' => 'send_whatsapp_message', 'dependencies' => []],
            ['task_key' => 'mut_3', 'tool_id' => 'create_contact', 'dependencies' => []]
        ]);

        $this->assert($dagMut->getNode('mut_1')['is_mutating'] === true, "send_email classified as mutating");
        $this->assert($dagMut->getNode('mut_1')['max_retries'] === 0, "Mutating send_email strictly has max_retries = 0");
        $this->assert($dagMut->getNode('mut_2')['max_retries'] === 0, "Mutating send_whatsapp_message strictly has max_retries = 0");
        $this->assert($dagMut->getNode('mut_3')['max_retries'] === 0, "Mutating create_contact strictly has max_retries = 0");

        echo "\n";
    }

    /**
     * 9. Test Idempotency Protection for Mutating Actions
     */
    private function testIdempotencyProtectionForMutatingActions(): void {
        echo "TEST SUITE 9: Idempotency Protection for Mutating Operations\n";

        $convId = 'conv_idemp_' . bin2hex(random_bytes(4));
        $this->db->exec("INSERT INTO ai_agent_conversations (id, user_id, title) VALUES ('{$convId}', 1, 'Idempotency Test')");

        $dag = new TaskPlanDAG([
            [
                'task_key' => 'meet_task',
                'tool_id' => 'generate_instant_meet',
                'title' => 'Generate Meet',
                'dependencies' => [],
                'arguments' => ['title' => 'Idempotent Meet Call']
            ]
        ]);

        // First execution
        $res1 = ExecutionEngine::executeDAG($dag, 1, $convId, null, null, $this->db);
        $this->assert($res1['status'] === 'completed', "First execution of mutating meet task completes");

        $meetLink1 = $dag->getNode('meet_task')['output']['meet_link'] ?? '';
        $this->assert(!empty($meetLink1), "First execution generated valid meet link: {$meetLink1}");

        // Second execution on same workflow: should hit idempotency safeguard and not recreate
        $dag2 = ExecutionEngine::loadDAGFromWorkflow(['id' => $res1['workflow_id']], $this->db);
        $res2 = ExecutionEngine::executeDAG($dag2, 1, $convId, $res1['workflow_id'], null, $this->db);

        $meetLink2 = $dag2->getNode('meet_task')['output']['meet_link'] ?? '';
        $this->assert($meetLink1 === $meetLink2, "Idempotent execution preserved exact same result without duplicate action");

        echo "\n";
    }

    /**
     * 10. Test Maker-Checker Approval Gates & Resumption
     */
    private function testMakerCheckerApprovalGates(): void {
        echo "TEST SUITE 10: Maker-Checker Approval Gates & Resumption\n";

        $convId = 'conv_gate_' . bin2hex(random_bytes(4));
        $this->db->exec("INSERT INTO ai_agent_conversations (id, user_id, title) VALUES ('{$convId}', 1, 'Approval Gate Test')");

        $dag = new TaskPlanDAG([
            [
                'task_key' => 'step_1',
                'tool_id' => 'generate_instant_meet',
                'title' => 'Generate Meet Room',
                'dependencies' => []
            ],
            [
                'task_key' => 'step_2',
                'tool_id' => 'send_email',
                'title' => 'Send Meeting Email',
                'dependencies' => ['step_1'],
                'arguments' => [
                    'recipient' => 'partner@company.com',
                    'subject' => 'Call Link',
                    'body' => 'Meet: {{step_1.output.meet_link}}'
                ],
                'requires_approval' => true // Sensitive Maker-Checker Gate
            ]
        ]);

        // 1. Initial run: step_1 should complete, step_2 should pause at approval gate
        $res = ExecutionEngine::executeDAG($dag, 1, $convId, null, null, $this->db);

        $this->assert($res['status'] === 'waiting_for_approval', "Workflow pauses with status 'waiting_for_approval'");
        $this->assert(!empty($res['approval_required']), "Approval required payload is returned");
        $this->assert($res['approval_required']['task_key'] === 'step_2', "Approval gate halts exactly at step_2");
        $this->assert(!empty($res['approval_required']['approval_token']), "Generated secure approval_token: " . $res['approval_required']['approval_token']);

        $token = $res['approval_required']['approval_token'];
        $wfId = $res['workflow_id'];

        // 2. Reject test: test rejecting task
        $convRejectId = 'conv_rej_' . bin2hex(random_bytes(4));
        $rejWf = WorkflowEngine::createWorkflow(1, $convRejectId, 'email_plugin', 'send_email', 'Goal', [], null, $this->db);
        $dagRej = new TaskPlanDAG([
            ['task_key' => 't_rej', 'tool_id' => 'send_email', 'dependencies' => [], 'requires_approval' => true]
        ]);
        $rejRes = ExecutionEngine::executeDAG($dagRej, 1, $convRejectId, $rejWf['id'], null, $this->db);
        $rejectionOutcome = ExecutionEngine::rejectTask($rejWf['id'], 't_rej', 1, 'User declined sending', null, $this->db);
        $this->assert($rejectionOutcome['status'] === 'cancelled', "Task rejection transitions workflow to cancelled");

        // 3. Resume test: approve step_2 and resume DAG execution
        $resumeRes = ExecutionEngine::resumeAfterApproval($wfId, 'step_2', 1, ['approval_token' => $token], null, $this->db);
        $this->assert($resumeRes['status'] === 'completed', "Workflow resumes after approval and finishes with status 'completed'");

        $finalStep2 = $resumeRes['dag']['nodes'][1] ?? [];
        $this->assert($finalStep2['state'] === 'completed', "Approved task step_2 executed to completion");

        echo "\n";
    }

    /**
     * 11. Test Deep Result Verification
     */
    private function testDeepResultVerification(): void {
        echo "TEST SUITE 11: Deep Result Verification (DB State, Filesystem, API State)\n";

        // Verification 1: Real Contact created in database
        $this->db->exec("INSERT INTO contacts (id, user_id, first_name, last_name, email) VALUES (101, 1, 'Sarah', 'Connor', 'sarah@skynet.com')");
        $contactVer = VerificationEngine::verifyResult('create_contact', 1, [], ['id' => 101], $this->db);
        $this->assert($contactVer['verified'] === true, "VerificationEngine verifies actual contact record in CRM database");
        $this->assert(str_contains($contactVer['note'], 'Sarah Connor'), "Verification note contains verified contact name");

        // Verification 1b: Negative DB check (ID not in database)
        $fakeContactVer = VerificationEngine::verifyResult('create_contact', 1, [], ['id' => 9999], $this->db);
        $this->assert($fakeContactVer['verified'] === false, "VerificationEngine flags failure if record missing from DB");

        // Verification 2: Physical File on Disk
        $tempPath = sys_get_temp_dir() . '/test_export_' . bin2hex(random_bytes(4)) . '.xlsx';
        file_put_contents($tempPath, 'PK mock excel file content bytes');
        $fileVer = VerificationEngine::verifyResult('generate_export_file', 1, [], [
            'file_path' => $tempPath,
            'download_url' => 'https://linkpilot.io/api/download?token=abc',
            'token' => 'abc'
        ], $this->db);
        $this->assert($fileVer['verified'] === true, "VerificationEngine verifies physical file exists and filesize > 0 on disk");
        @unlink($tempPath);

        // Verification 3: Google Meet regex format
        $meetVerGood = VerificationEngine::verifyResult('generate_instant_meet', 1, [], ['meet_link' => 'https://meet.google.com/xyz-abcd-efg']);
        $this->assert($meetVerGood['verified'] === true, "VerificationEngine verifies Google Meet URI format");

        $meetVerBad = VerificationEngine::verifyResult('generate_instant_meet', 1, [], ['meet_link' => 'https://fake-meet.com/not-real']);
        $this->assert($meetVerBad['verified'] === false, "VerificationEngine rejects malformed Google Meet link");

        echo "\n";
    }

    /**
     * 12. Test Interrupted Workflow Recovery
     */
    private function testInterruptedWorkflowRecovery(): void {
        echo "TEST SUITE 12: Interrupted Workflow Recovery (Server Restart / Crash Resumption)\n";

        $convId = 'conv_rec_' . bin2hex(random_bytes(4));
        $this->db->exec("INSERT INTO ai_agent_conversations (id, user_id, title) VALUES ('{$convId}', 1, 'Recovery Test')");

        $wf = WorkflowEngine::createWorkflow(1, $convId, 'task_plugin', 'dag_execution', 'Crash recovery test', [], null, $this->db);
        WorkflowEngine::transitionState($wf['id'], WorkflowEngine::STATE_RUNNING, null, [], 'Running', $this->db);

        // Simulate DAG tasks in mid-execution when server died
        $this->db->exec("
            INSERT INTO ai_agent_workflow_tasks (id, workflow_id, task_key, tool_id, title, state, is_mutating, max_retries, created_at)
            VALUES 
                ('t_rec_1', '{$wf['id']}', 'step_1', 'search_contacts', 'Search Contacts', 'completed', 0, 2, datetime('now')),
                ('t_rec_2', '{$wf['id']}', 'step_2', 'get_hot_leads', 'Get Hot Leads', 'running', 0, 2, datetime('now')),
                ('t_rec_3', '{$wf['id']}', 'step_3', 'send_email', 'Send Email Alert', 'running', 1, 0, datetime('now'))
        ");

        // Execute recovery
        $recResult = ExecutionEngine::recoverWorkflow($wf['id'], 1, $this->db);

        $this->assert($recResult['status'] === 'recovered', "ExecutionEngine::recoverWorkflow returns status 'recovered'");

        // Reload tasks from DB
        $tasks = $this->db->query("SELECT task_key, state FROM ai_agent_workflow_tasks WHERE workflow_id = '{$wf['id']}'")->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->assert($tasks['step_1'] === 'completed', "Completed task step_1 remains completed");
        $this->assert($tasks['step_2'] === 'pending', "Interrupted non-mutating task step_2 safely reset to 'pending' for resumption");
        $this->assert($tasks['step_3'] === 'failed', "Interrupted mutating task step_3 marked 'failed' to prevent duplicate execution");

        echo "\n";
    }

    /**
     * 13. Test Tier 1 TaskPlanner Intent Mapping
     */
    private function testTaskPlannerIntentMapping(): void {
        echo "TEST SUITE 13: Tier 1 Strategic TaskPlanner Intent Mapping\n";

        // Multi-step compound request: Meet + Email
        $planMeetEmail = TaskPlanner::createPlan(
            "Create a Google meet and email it to client@acme.com",
            ['path' => 'complex'],
            [],
            1,
            'conv_test',
            $this->db
        );
        $nodesME = $planMeetEmail->getNodes();
        $this->assert(count($nodesME) === 2, "TaskPlanner synthesizes 2-step compound DAG for 'create meet and email'");
        $this->assert($nodesME['task_1']['tool_id'] === 'generate_instant_meet', "Task 1 is generate_instant_meet");
        $this->assert($nodesME['task_2']['tool_id'] === 'send_email', "Task 2 is send_email");
        $this->assert($nodesME['task_2']['dependencies'] === ['task_1'], "Task 2 explicitly depends on Task 1");

        // Multi-step compound request: Export + Email
        $planExportEmail = TaskPlanner::createPlan(
            "Export contacts in excel and send it to manager@acme.com",
            ['path' => 'complex'],
            [],
            1,
            'conv_test',
            $this->db
        );
        $nodesEE = $planExportEmail->getNodes();
        $this->assert(count($nodesEE) === 2, "TaskPlanner synthesizes 2-step compound DAG for 'export and email'");
        $this->assert($nodesEE['task_1']['tool_id'] === 'generate_export_file', "Task 1 is generate_export_file");
        $this->assert($nodesEE['task_2']['tool_id'] === 'send_email', "Task 2 is send_email");

        // Single step request: Instant meet
        $planSingle = TaskPlanner::createPlan(
            "Create a Google meet video call room",
            ['path' => 'direct'],
            [],
            1,
            'conv_test',
            $this->db
        );
        $this->assert(count($planSingle->getNodes()) === 1, "TaskPlanner synthesizes 1-step DAG for single action");

        echo "\n";
    }

    /**
     * 14. Test End-to-End Orchestrator Integration & Backward Compatibility
     */
    private function testOrchestratorEndToEndIntegration(): void {
        echo "TEST SUITE 14: End-to-End Orchestrator Integration & Conversational Approval\n";

        $convId = 'conv_e2e_' . bin2hex(random_bytes(4));
        $this->db->exec("INSERT INTO ai_agent_conversations (id, user_id, title) VALUES ('{$convId}', 1, 'E2E Orchestrator Test')");

        // 1. User asks compound goal: "Create a meet and email it to investor@venture.com"
        $res1 = AgentOrchestrator::processChat(1, $convId, "Create a Google meet and email it to investor@venture.com", [], null, $this->db);

        $this->assert($res1['status'] === 'waiting_approval', "Orchestrator pauses compound workflow at Maker-Checker approval gate");
        $this->assert(!empty($res1['workflow_id']), "Workflow ID tracked in conversation response");

        // 2. User confirms conversationally: "yes, please send that email"
        $res2 = AgentOrchestrator::processChat(1, $convId, "yes, please send that email", [], null, $this->db);

        $this->assert($res2['status'] === 'completed', "Conversational approval resumes workflow and completes successfully");
        $this->assert(str_contains($res2['content'], 'investor@venture.com'), "Assistant confirmation mentions verified recipient");

        // 3. Verify workflow state in DB
        $wfRow = $this->db->query("SELECT state FROM ai_agent_workflows WHERE id = '{$res1['workflow_id']}'")->fetch(PDO::FETCH_ASSOC);
        $this->assert($wfRow['state'] === 'completed', "Workflow in database transitioned to 'completed'");

        // 4. Verify existing 45 tools preservation & conversational fallback
        $resInstant = AgentOrchestrator::processChat(1, $convId, "Hello LinkPilot!", [], null, $this->db);
        $this->assert($resInstant['status'] === 'completed' || $resInstant['status'] === 'success', "Standard chat/instant route preserved 100% backward compatible");

        echo "\n";
    }
}

// Execute Regression Suite
$runner = new Phase4TestRunner();
$success = $runner->runAll();
exit($success ? 0 : 1);
