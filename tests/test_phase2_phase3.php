<?php
// tests/test_phase2_phase3.php
// Autonomous Agent Engine 2.0: Automated Regression & Verification Suite for Phase 2 & Phase 3.
// Tests Universal Plugin Registry, State Machine, Context Resolution, Idempotency, and Tool Discovery.

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/ai_tool_registry.php';
require_once __DIR__ . '/../backend/agent/AgentMigration2.php';
require_once __DIR__ . '/../backend/agent/PluginRegistry.php';
require_once __DIR__ . '/../backend/agent/WorkflowEngine.php';
require_once __DIR__ . '/../backend/agent/ContextManager.php';
require_once __DIR__ . '/../backend/agent_orchestrator.php';

class AgentEngineTestRunner {
    private PDO $db;
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function __construct() {
        // Use an isolated SQLite test database for fast, self-contained, reproducible execution
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
        echo "  Phase 2 (Plugin Registry) & Phase 3 (Workflow State Machine & Context)\n";
        echo "========================================================================\n\n";

        $this->testDatabaseMigrationAndRollback();
        $this->testUniversalPluginRegistry();
        $this->testPluginDependencyAndActivation();
        $this->testRealConnectionStatusChecks();
        $this->testWorkflowStateMachine();
        $this->testInvalidStateTransitionRejection();
        $this->testWorkflowIdempotency();
        $this->testExecutionLocking();
        $this->testPronounAndEntityResolution();
        $this->testDynamicToolDiscoveryPreservingWorkflow();
        $this->testOrchestratorBackwardCompatibility();

        echo "\n------------------------------------------------------------------------\n";
        echo "  TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "------------------------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "Failures:\n";
            foreach ($this->errors as $err) {
                echo "  {$err}\n";
            }
            return false;
        }

        echo "All Phase 2 & Phase 3 regression tests PASSED successfully!\n\n";
        return true;
    }

    /**
     * 1. Test Database Migration & Rollback
     */
    private function testDatabaseMigrationAndRollback(): void {
        echo "TEST SUITE 1: Database Migration, Schema Verification & Rollback\n";

        // Create base tables for SQLite test harness
        $this->db->exec("CREATE TABLE IF NOT EXISTS `users` (`id` INTEGER PRIMARY KEY, `name` TEXT, `email` TEXT, `role` TEXT DEFAULT 'user')");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_conversations` (`id` TEXT PRIMARY KEY, `user_id` INTEGER, `title` TEXT, `summary` TEXT, `is_pinned` INTEGER DEFAULT 0, `is_archived` INTEGER DEFAULT 0, `created_at` TEXT, `updated_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_messages` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `conversation_id` TEXT, `user_id` INTEGER, `role` TEXT, `content` TEXT, `tool_calls_json` TEXT, `tool_results_json` TEXT, `status` TEXT, `tokens_used` INTEGER DEFAULT 0, `created_at` TEXT)");

        // Run Phase 2/3 Migration
        $res = AgentMigration2::run($this->db);
        $this->assert($res['status'] === 'success', "AgentMigration2::run executes successfully");

        // Verify tables exist
        $tables = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assert(in_array('ai_agent_workflows', $tables), "ai_agent_workflows table created");
        $this->assert(in_array('ai_agent_plugins', $tables), "ai_agent_plugins table created");

        // Verify workflow_id added to ai_agent_messages
        $cols = $this->db->query("PRAGMA table_info(ai_agent_messages)")->fetchAll(PDO::FETCH_ASSOC);
        $hasWfCol = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'workflow_id') $hasWfCol = true;
        }
        $this->assert($hasWfCol, "ai_agent_messages extended with workflow_id column");

        // Test Rollback
        $rbRes = AgentMigration2::rollback($this->db);
        $this->assert($rbRes['status'] === 'rolled_back', "AgentMigration2::rollback executes cleanly");
        $tablesAfter = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assert(!in_array('ai_agent_workflows', $tablesAfter), "ai_agent_workflows dropped on rollback");

        // Re-run migration for subsequent tests
        $reRun = AgentMigration2::run($this->db);
        $this->assert($reRun['status'] === 'success', "AgentMigration2 re-run succeeds idempotently");
    }

    /**
     * 2. Test Universal Plugin Registry
     */
    private function testUniversalPluginRegistry(): void {
        echo "\nTEST SUITE 2: Universal Dynamic Plugin Registry & 45 Preserved Tools\n";

        PluginRegistry::init($this->db);

        $plugins = PluginRegistry::listPlugins(1, $this->db);
        $this->assert(count($plugins) === 10, "Exactly 10 core system plugins registered", "Found: " . count($plugins));

        $expectedPlugins = [
            'crm_contacts_plugin', 'crm_leads_plugin', 'crm_deals_plugin', 'crm_tasks_plugin',
            'google_meet_plugin', 'gmail_plugin', 'whatsapp_plugin', 'file_export_plugin',
            'web_research_plugin', 'analytics_intelligence_plugin'
        ];

        $registeredIds = array_map(fn($p) => $p['id'], $plugins);
        foreach ($expectedPlugins as $expId) {
            $this->assert(in_array($expId, $registeredIds), "Plugin '{$expId}' is registered with manifest");
        }

        // Verify all 45 existing tools preserved
        $tools = PluginRegistry::listTools();
        $this->assert(count($tools) === 45, "All 45 existing LinkPilot tools preserved in registry", "Found: " . count($tools));

        // Check manifest versioning and categories
        $contactPlugin = PluginRegistry::getPlugin('crm_contacts_plugin');
        $this->assert($contactPlugin['version'] === '1.0.0', "crm_contacts_plugin has semantic version 1.0.0");
        $this->assert($contactPlugin['category'] === 'crm', "crm_contacts_plugin category is 'crm'");
        $this->assert(!empty($contactPlugin['required_permissions']), "crm_contacts_plugin specifies permissions");
    }

    /**
     * 3. Test Plugin Dependency & Activation
     */
    private function testPluginDependencyAndActivation(): void {
        echo "\nTEST SUITE 3: Plugin Dependency Resolution & Activation Management\n";

        // crm_leads_plugin depends on crm_contacts_plugin
        $depCheck = PluginRegistry::checkDependencies('crm_leads_plugin');
        $this->assert($depCheck['valid'] === true, "crm_leads_plugin dependencies are satisfied");

        // Test activation toggling
        $this->assert(PluginRegistry::isPluginActive('web_research_plugin') === true, "web_research_plugin initially active");
        PluginRegistry::setPluginActivation('web_research_plugin', false, $this->db);
        $this->assert(PluginRegistry::isPluginActive('web_research_plugin') === false, "web_research_plugin deactivated successfully");

        // Register a test plugin with a missing dependency
        PluginRegistry::registerPlugin([
            'id' => 'custom_test_plugin',
            'name' => 'Custom Test',
            'version' => '1.0.0',
            'dependencies' => ['non_existent_plugin_xyz'],
            'tools' => []
        ]);
        $brokenDep = PluginRegistry::checkDependencies('custom_test_plugin');
        $this->assert($brokenDep['valid'] === false, "checkDependencies catches missing dependency");
        $this->assert(in_array('non_existent_plugin_xyz', $brokenDep['missing']), "Missing dependency reported in manifest");

        // Re-enable web_research_plugin
        PluginRegistry::setPluginActivation('web_research_plugin', true, $this->db);
        $this->assert(PluginRegistry::isPluginActive('web_research_plugin') === true, "web_research_plugin re-activated");
    }

    /**
     * 4. Test Real Connection Status Checks
     */
    private function testRealConnectionStatusChecks(): void {
        echo "\nTEST SUITE 4: Real Integration Connection Status Verification\n";

        // Setup mock integration tables in SQLite
        $this->db->exec("CREATE TABLE IF NOT EXISTS `external_app_connections` (`id` INTEGER PRIMARY KEY, `user_id` INTEGER, `provider` TEXT, `email` TEXT, `connected_email` TEXT, `scopes` TEXT, `status` TEXT, `created_at` TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_accounts` (`id` INTEGER PRIMARY KEY, `user_id` INTEGER, `business_name` TEXT, `phone_number` TEXT, `status` TEXT)");

        // 1. Google Calendar / Meet when not connected
        $connGoogleNotConnected = PluginRegistry::checkConnection('google_meet_plugin', 1, $this->db);
        $this->assert($connGoogleNotConnected['connected'] === false, "Google Meet reports not connected when no DB row exists");
        $this->assert(str_contains($connGoogleNotConnected['message'], 'not connected'), "Descriptive connection error returned");

        // 2. Connect Google account with Calendar scope
        $this->db->exec("INSERT INTO `external_app_connections` (`user_id`, `provider`, `email`, `scopes`, `status`) VALUES (1, 'google', 'user@example.com', 'https://www.googleapis.com/auth/calendar.events,https://www.googleapis.com/auth/gmail.send', 'connected')");
        $connGoogleConnected = PluginRegistry::checkConnection('google_meet_plugin', 1, $this->db);
        $this->assert($connGoogleConnected['connected'] === true, "Google Meet reports connected when valid calendar scope is present");
        $this->assert($connGoogleConnected['account'] === 'user@example.com', "Verified user account returned");

        // 3. Gmail OAuth connection check
        $connGmail = PluginRegistry::checkConnection('gmail_plugin', 1, $this->db);
        $this->assert($connGmail['connected'] === true, "Gmail reports connected when gmail.send scope is present");

        // 4. WhatsApp Cloud API check
        $connWaBefore = PluginRegistry::checkConnection('whatsapp_plugin', 1, $this->db);
        $this->assert($connWaBefore['connected'] === false, "WhatsApp reports not connected initially");

        $this->db->exec("INSERT INTO `whatsapp_accounts` (`user_id`, `business_name`, `phone_number`, `status`) VALUES (1, 'LinkPilot Support', '+15551234567', 'connected')");
        $connWaAfter = PluginRegistry::checkConnection('whatsapp_plugin', 1, $this->db);
        $this->assert($connWaAfter['connected'] === true, "WhatsApp reports connected when active account exists");

        // 5. Tool execution safety guard: executeTool blocks execution when disconnected
        $connGuardRes = PluginRegistry::executeTool('send_whatsapp_message', 99, ['phone' => '+1234567890', 'message' => 'Hello'], $this->db);
        $this->assert($connGuardRes['status'] === 'connection_required', "executeTool returns connection_required when integration is absent");
        $this->assert($connGuardRes['error'] === 'integration_not_connected', "Tool execution blocked safely without crashing");
    }

    /**
     * 5. Test Workflow State Machine
     */
    private function testWorkflowStateMachine(): void {
        echo "\nTEST SUITE 5: Workflow State Machine Lifecycle & Transitions\n";

        $userId = 1;
        $convId = 'conv_test_123';

        // 1. Create workflow
        $wf = WorkflowEngine::createWorkflow($userId, $convId, 'google_meet_plugin', 'instant_meeting', 'Create client room', ['host' => 'user@example.com'], null, $this->db);
        $this->assert(!empty($wf['id']), "Workflow created with unique ID");
        $this->assert($wf['state'] === WorkflowEngine::STATE_PENDING, "Initial state is 'pending'");

        // 2. Transition pending -> running
        $wf = WorkflowEngine::transitionState($wf['id'], WorkflowEngine::STATE_RUNNING, 'generate_instant_meet', [], 'Started execution', $this->db);
        $this->assert($wf['state'] === WorkflowEngine::STATE_RUNNING, "Transition to 'running' successful");
        $this->assert($wf['active_step'] === 'generate_instant_meet', "Active step recorded");

        // 3. Transition running -> waiting_for_approval
        $wf = WorkflowEngine::transitionState($wf['id'], WorkflowEngine::STATE_WAITING_FOR_APPROVAL, 'send_email', ['to' => 'client@acme.com'], 'User confirmation required', $this->db);
        $this->assert($wf['state'] === WorkflowEngine::STATE_WAITING_FOR_APPROVAL, "Transition to 'waiting_for_approval' successful");

        // 4. Transition waiting_for_approval -> running (user confirmed)
        $wf = WorkflowEngine::transitionState($wf['id'], WorkflowEngine::STATE_RUNNING, 'send_email', [], 'User approved via chat', $this->db);
        $this->assert($wf['state'] === WorkflowEngine::STATE_RUNNING, "Resumed to 'running' upon approval");

        // 5. Transition running -> completed
        $wf = WorkflowEngine::transitionState($wf['id'], WorkflowEngine::STATE_COMPLETED, null, ['meet_link' => 'https://meet.google.com/abc-defg-hij'], 'Finished', $this->db);
        $this->assert($wf['state'] === WorkflowEngine::STATE_COMPLETED, "Transition to 'completed' successful");
        $this->assert(!empty($wf['completed_at']), "completed_at timestamp recorded");
        $this->assert($wf['entities']['meet_link'] === 'https://meet.google.com/abc-defg-hij', "Workflow entities accumulated and persisted");

        // 6. Verify transition history audit log
        $this->assert(count($wf['history']) >= 5, "Audit history tracked every state transition", "Steps: " . count($wf['history']));
    }

    /**
     * 6. Test Invalid State Transition Rejection
     */
    private function testInvalidStateTransitionRejection(): void {
        echo "\nTEST SUITE 6: Strict State Machine Rejection of Illegal Transitions\n";

        $userId = 1;
        $convId = 'conv_test_cancel';

        $wf = WorkflowEngine::createWorkflow($userId, $convId, 'crm_tasks_plugin', 'task_creation', 'Create task', [], null, $this->db);
        
        // Transition to cancelled (terminal)
        $wf = WorkflowEngine::transitionState($wf['id'], WorkflowEngine::STATE_CANCELLED, null, [], 'User cancelled', $this->db);
        $this->assert($wf['state'] === WorkflowEngine::STATE_CANCELLED, "Workflow cancelled");

        // Attempt illegal transition from cancelled -> running
        $threwException = false;
        try {
            WorkflowEngine::transitionState($wf['id'], WorkflowEngine::STATE_RUNNING, null, [], 'Illegal transition', $this->db);
        } catch (InvalidWorkflowTransitionException $e) {
            $threwException = true;
        } catch (Throwable $e) {
            $threwException = true;
        }

        $this->assert($threwException, "Illegal transition from 'cancelled' to 'running' rejected with exception");
    }

    /**
     * 7. Test Workflow Idempotency Keys
     */
    private function testWorkflowIdempotency(): void {
        echo "\nTEST SUITE 7: Idempotency Keys & Duplicate Action Prevention\n";

        $userId = 1;
        $convId = 'conv_idemp_1';
        $idempKey = 'tx_export_orders_' . bin2hex(random_bytes(4));

        // Create first workflow
        $wf1 = WorkflowEngine::createWorkflow($userId, $convId, 'file_export_plugin', 'export_excel', 'Export orders', ['rows' => 50], $idempKey, $this->db);
        $this->assert(!empty($wf1['id']), "Initial workflow created with idempotency key");

        // Attempt duplicate creation with exact same idempotency key
        $wf2 = WorkflowEngine::createWorkflow($userId, $convId, 'file_export_plugin', 'export_excel', 'Export orders duplicate', ['rows' => 50], $idempKey, $this->db);
        $this->assert($wf1['id'] === $wf2['id'], "Duplicate request with same idempotency key returns existing workflow instance");
    }

    /**
     * 8. Test Execution Locking
     */
    private function testExecutionLocking(): void {
        echo "\nTEST SUITE 8: Distributed Execution Locking & Concurrency Guard\n";

        $userId = 1;
        $convId = 'conv_lock_test';
        $wf = WorkflowEngine::createWorkflow($userId, $convId, 'crm_tasks_plugin', 'lock_test', 'Lock test', [], null, $this->db);

        // Acquire lock
        $acquired1 = WorkflowEngine::acquireLock($wf['id'], $userId, 10, $this->db);
        $this->assert($acquired1 === true, "First execution lock acquired successfully");
        $this->assert(WorkflowEngine::isLocked($wf['id'], $this->db) === true, "Workflow reports locked");

        // Attempt concurrent lock while active
        $acquired2 = WorkflowEngine::acquireLock($wf['id'], $userId, 10, $this->db);
        $this->assert($acquired2 === false, "Concurrent lock attempt rejected (duplicate action prevented)");

        // Release lock
        WorkflowEngine::releaseLock($wf['id'], $userId, $this->db);
        $this->assert(WorkflowEngine::isLocked($wf['id'], $this->db) === false, "Lock released cleanly");

        // Re-acquire after release
        $acquired3 = WorkflowEngine::acquireLock($wf['id'], $userId, 10, $this->db);
        $this->assert($acquired3 === true, "Lock re-acquired after release");
    }

    /**
     * 9. Test Pronoun and Entity Resolution
     */
    private function testPronounAndEntityResolution(): void {
        echo "\nTEST SUITE 9: Multi-Turn Context & Pronoun/Entity Resolution\n";

        $userId = 1;
        $convId = 'conv_multiturn_1';

        // Simulate turn 1: meeting generated
        $activeEntities = [
            'meet_link' => 'https://meet.google.com/xyz-pqrs-tuv',
            'contact_name' => 'Alice Johnson',
            'contact_id' => 42,
            'contact_email' => 'alice@johnsoncorp.com'
        ];

        // Test 1: User says "send it to bob@example.com"
        $resolvedPronoun = ContextManager::resolvePronouns("send it to bob@example.com", $activeEntities, null);
        $this->assert(isset($resolvedPronoun['anchors']['it']), "Pronoun 'it' detected");
        $this->assert($resolvedPronoun['anchors']['it']['value'] === 'https://meet.google.com/xyz-pqrs-tuv', "Pronoun 'it' anchored to active meet_link");
        $this->assert($resolvedPronoun['anchors']['recipient_email'] === 'bob@example.com', "Destination email extracted");
        $this->assert($resolvedPronoun['suggested_plugin'] === 'gmail_plugin', "Suggested plugin is 'gmail_plugin'");

        // Test 2: User says "send him a message"
        $resolvedPerson = ContextManager::resolvePronouns("send him a whatsapp message", $activeEntities, null);
        $this->assert(isset($resolvedPerson['anchors']['person']), "Pronoun 'him' detected");
        $this->assert($resolvedPerson['anchors']['person']['name'] === 'Alice Johnson', "Pronoun 'him' anchored to target contact");
        $this->assert($resolvedPerson['suggested_plugin'] === 'whatsapp_plugin', "Suggested plugin is 'whatsapp_plugin'");

        // Test 3: Prompt block synthesis
        $promptBlock = ContextManager::buildContextPromptBlock(null, $activeEntities, $resolvedPronoun);
        $this->assert(str_contains($promptBlock, 'https://meet.google.com/xyz-pqrs-tuv'), "Prompt block injects active meeting link");
        $this->assert(str_contains($promptBlock, 'bob@example.com'), "Prompt block injects destination email");
        $this->assert(str_contains($promptBlock, 'DO NOT RE-ASK'), "Prompt block explicitly instructs model not to re-ask user");
    }

    /**
     * 10. Test Dynamic Tool Discovery Preserving Workflow Tools
     */
    private function testDynamicToolDiscoveryPreservingWorkflow(): void {
        echo "\nTEST SUITE 10: Dynamic Tool Discovery Retaining Active Workflow Tools\n";

        // Scenario: Active workflow is google_meet_plugin, but current user prompt is just "send it to alice@example.com"
        // Without workflow retention, keyword classifier only detects "email" and drops Google Meet tools!
        // With PluginRegistry::getToolsForContext, google_meet_plugin tools MUST be preserved!

        $classification = ['categories' => ['email']];
        $retainedPluginIds = ['google_meet_plugin'];

        $tools = PluginRegistry::getToolsForContext('send it to alice@example.com', $classification, $retainedPluginIds);
        $toolNames = array_map(fn($t) => $t['function']['name'], $tools);

        // Verify email tools are included
        $this->assert(in_array('send_email', $toolNames), "Dynamic discovery includes email tools ('send_email')");

        // Verify Google Meet tools are NOT lost
        $this->assert(in_array('generate_instant_meet', $toolNames), "Dynamic discovery retains active workflow tool 'generate_instant_meet'");
        $this->assert(in_array('create_meeting', $toolNames), "Dynamic discovery retains active workflow tool 'create_meeting'");
    }

    /**
     * 11. Test Orchestrator Backward Compatibility
     */
    private function testOrchestratorBackwardCompatibility(): void {
        echo "\nTEST SUITE 11: AgentOrchestrator Backward Compatibility & Tool Calling\n";

        // Verify Orchestrator initializes with Phase 2/3 adapters
        AgentOrchestrator::init($this->db);

        // Verify conversation creation and retrieval contracts
        $conv = AgentOrchestrator::createConversation(1, 'Compatibility Test', $this->db);
        $this->assert(!empty($conv['id']), "Conversation created with expected signature");

        $loaded = AgentOrchestrator::getConversationMessages(1, $conv['id'], $this->db);
        $this->assert(isset($loaded['conversation']) && isset($loaded['messages']), "getConversationMessages returns identical response contract");

        // Verify approval intent recognizer
        $approval1 = AgentOrchestrator::checkConversationalApprovalIntent("send that mail");
        $this->assert($approval1 === 'approved', "checkConversationalApprovalIntent recognises 'send that mail'");

        $rejection1 = AgentOrchestrator::checkConversationalApprovalIntent("don't send it, cancel");
        $this->assert($rejection1 === 'rejected', "checkConversationalApprovalIntent recognises rejection");
    }
}

// Execute Runner
$runner = new AgentEngineTestRunner();
$success = $runner->runAll();

exit($success ? 0 : 1);
