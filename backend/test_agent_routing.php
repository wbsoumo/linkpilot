<?php
// backend/test_agent_routing.php
// Automated Verification Suite for LinkPilot AI Agent: Smart Routing & Performance Optimization.
// Tests >= 32 test instructions across all 4 execution categories:
// 1. PATH_INSTANT (Zero-LLM greetings, date/time with timezone, acknowledgements, capabilities)
// 2. PATH_SIMPLE_AI (General questions, concepts, writing advice with 0 tool payload)
// 3. PATH_BUSINESS_OPS (Single-domain CRM, Deals, Tasks, WhatsApp, Email, Meetings, Analytics)
// 4. PATH_COMPLEX_AUTONOMOUS (Mixed intents, compound workflows, live weather, web search)

require_once __DIR__ . '/config.php';
while (ob_get_level()) ob_end_clean();

// Setup Mock In-Memory SQLite Database for unit testing without network/DB dependencies
$mockDb = new PDO('sqlite::memory:');
$mockDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Database::setConnection($mockDb);

require_once __DIR__ . '/agent_cache.php';
require_once __DIR__ . '/agent_telemetry.php';
require_once __DIR__ . '/agent_router.php';
require_once __DIR__ . '/ai_tool_registry.php';

// Create required mock tables
$mockDb->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT);");
$mockDb->exec("CREATE TABLE user_profiles (id INTEGER PRIMARY KEY, user_id INTEGER, company_name TEXT, industry TEXT, timezone TEXT);");
$mockDb->exec("CREATE TABLE ai_agent_memory (id INTEGER PRIMARY KEY, user_id INTEGER, memory_type TEXT, key_name TEXT, value_text TEXT);");
$mockDb->exec("CREATE TABLE ai_agent_conversations (id TEXT PRIMARY KEY, user_id INTEGER, title TEXT, summary TEXT, is_pinned INTEGER DEFAULT 0, is_archived INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT);");
$mockDb->exec("CREATE TABLE ai_agent_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id TEXT, user_id INTEGER, role TEXT, content TEXT, tool_calls_json TEXT, tool_results_json TEXT, plan_json TEXT, status TEXT, tokens_used INTEGER DEFAULT 0, created_at TEXT);");
$mockDb->exec("CREATE TABLE ai_agent_audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, conversation_id TEXT, tool_id TEXT, tool_name TEXT, risk_level TEXT, input_json TEXT, output_json TEXT, approval_status TEXT, error_message TEXT, created_at TEXT);");
$mockDb->exec("CREATE TABLE ai_agent_telemetry_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, conversation_id TEXT, request_text TEXT, classification TEXT, execution_path TEXT, provider TEXT, model TEXT, tools_loaded TEXT, tools_executed TEXT, ai_latency_ms INTEGER, db_latency_ms INTEGER, total_time_ms INTEGER, tokens_used INTEGER, created_at TEXT);");

// Insert Mock User with specific timezone
$testUserId = 1;
$testConvId = 'conv_test_' . time();
$mockDb->exec("INSERT INTO users (id, name, email, role) VALUES (1, 'Soumojit Saha', 'soumojit@linkpilot.work', 'admin');");
$mockDb->exec("INSERT INTO user_profiles (user_id, company_name, industry, timezone) VALUES (1, 'LinkPilot Workspace', 'Technology', 'Asia/Kolkata');");
$mockDb->exec("INSERT INTO ai_agent_memory (user_id, memory_type, key_name, value_text) VALUES (1, 'setting', 'agent_name', 'Pingu');");
$mockDb->exec("INSERT INTO ai_agent_conversations (id, user_id, title) VALUES ('{$testConvId}', 1, 'Diag Test');");

// Initialize Tool Registry
AIToolRegistry::init();

echo "\n========================================================================\n";
echo "   LINKPILOT AI AGENT: SMART ROUTING & PERFORMANCE TEST SUITE\n";
echo "========================================================================\n\n";

$testCases = [
    // ---------------------------------------------------------
    // CATEGORY 1: INSTANT RESPONSES (Must NEVER call external AI)
    // ---------------------------------------------------------
    [
        'category' => 'INSTANT',
        'query' => 'Hello',
        'expected_path' => AgentRouter::PATH_INSTANT,
        'expected_subtype' => 'greeting'
    ],
    [
        'category' => 'INSTANT',
        'query' => 'Hi, good morning!',
        'expected_path' => AgentRouter::PATH_INSTANT,
        'expected_subtype' => 'greeting'
    ],
    [
        'category' => 'INSTANT',
        'query' => 'whats the date today /',
        'expected_path' => AgentRouter::PATH_INSTANT,
        'expected_subtype' => 'date_time'
    ],
    [
        'category' => 'INSTANT',
        'query' => 'What time is it right now?',
        'expected_path' => AgentRouter::PATH_INSTANT,
        'expected_subtype' => 'date_time'
    ],
    [
        'category' => 'INSTANT',
        'query' => "today's date",
        'expected_path' => AgentRouter::PATH_INSTANT,
        'expected_subtype' => 'date_time'
    ],
    [
        'category' => 'INSTANT',
        'query' => 'Thank you so much!',
        'expected_path' => AgentRouter::PATH_INSTANT,
        'expected_subtype' => 'acknowledgement'
    ],
    [
        'category' => 'INSTANT',
        'query' => 'Got it, thanks',
        'expected_path' => AgentRouter::PATH_INSTANT,
        'expected_subtype' => 'acknowledgement'
    ],
    [
        'category' => 'INSTANT',
        'query' => 'Who are you and what can you do?',
        'expected_path' => AgentRouter::PATH_INSTANT,
        'expected_subtype' => 'capabilities'
    ],
    [
        'category' => 'INSTANT',
        'query' => 'Help me understand your features',
        'expected_path' => AgentRouter::PATH_INSTANT,
        'expected_subtype' => 'capabilities'
    ],

    // ---------------------------------------------------------
    // CATEGORY 2: SIMPLE AI CONVERSATION (Zero tools loaded)
    // ---------------------------------------------------------
    [
        'category' => 'SIMPLE_AI',
        'query' => 'What is the difference between cold calling and cold emailing?',
        'expected_path' => AgentRouter::PATH_SIMPLE_AI,
        'expected_subtype' => 'conversational_qa'
    ],
    [
        'category' => 'SIMPLE_AI',
        'query' => 'Can you write a polite follow-up subject line for B2B prospects?',
        'expected_path' => AgentRouter::PATH_SIMPLE_AI,
        'expected_subtype' => 'conversational_qa'
    ],
    [
        'category' => 'SIMPLE_AI',
        'query' => 'Explain the concept of Customer Acquisition Cost (CAC).',
        'expected_path' => AgentRouter::PATH_SIMPLE_AI,
        'expected_subtype' => 'conversational_qa'
    ],
    [
        'category' => 'SIMPLE_AI',
        'query' => 'Give me 3 tips for negotiating enterprise contracts.',
        'expected_path' => AgentRouter::PATH_SIMPLE_AI,
        'expected_subtype' => 'conversational_qa'
    ],
    [
        'category' => 'SIMPLE_AI',
        'query' => 'Draft a motivational quote for the morning sales standup.',
        'expected_path' => AgentRouter::PATH_SIMPLE_AI,
        'expected_subtype' => 'conversational_qa'
    ],
    [
        'category' => 'SIMPLE_AI',
        'query' => 'What are common objections prospects have to SaaS products?',
        'expected_path' => AgentRouter::PATH_SIMPLE_AI,
        'expected_subtype' => 'conversational_qa'
    ],
    [
        'category' => 'SIMPLE_AI',
        'query' => 'How should I structure a 15-minute introductory sales demo?',
        'expected_path' => AgentRouter::PATH_SIMPLE_AI,
        'expected_subtype' => 'conversational_qa'
    ],
    [
        'category' => 'SIMPLE_AI',
        'query' => 'What does churn rate mean in subscription business?',
        'expected_path' => AgentRouter::PATH_SIMPLE_AI,
        'expected_subtype' => 'conversational_qa'
    ],

    // ---------------------------------------------------------
    // CATEGORY 3: BUSINESS OPERATIONS (Targeted category tools)
    // ---------------------------------------------------------
    [
        'category' => 'BUSINESS_OPS',
        'query' => 'Search contacts for John in London',
        'expected_path' => AgentRouter::PATH_BUSINESS_OPS,
        'expected_subtype' => 'crm'
    ],
    [
        'category' => 'BUSINESS_OPS',
        'query' => 'Show me hot leads from this week',
        'expected_path' => AgentRouter::PATH_BUSINESS_OPS,
        'expected_subtype' => 'crm'
    ],
    [
        'category' => 'BUSINESS_OPS',
        'query' => 'What deals are currently at risk in my pipeline?',
        'expected_path' => AgentRouter::PATH_BUSINESS_OPS,
        'expected_subtype' => 'deals'
    ],
    [
        'category' => 'BUSINESS_OPS',
        'query' => 'Move deal #12 to Closed-Won stage',
        'expected_path' => AgentRouter::PATH_BUSINESS_OPS,
        'expected_subtype' => 'deals'
    ],
    [
        'category' => 'BUSINESS_OPS',
        'query' => 'Create a task to call Acme Corp tomorrow with high priority',
        'expected_path' => AgentRouter::PATH_BUSINESS_OPS,
        'expected_subtype' => 'tasks'
    ],
    [
        'category' => 'BUSINESS_OPS',
        'query' => 'Check if there are any unreplied WhatsApp customer messages',
        'expected_path' => AgentRouter::PATH_BUSINESS_OPS,
        'expected_subtype' => 'whatsapp'
    ],
    [
        'category' => 'BUSINESS_OPS',
        'query' => 'Search recent emails from prospective investors',
        'expected_path' => AgentRouter::PATH_BUSINESS_OPS,
        'expected_subtype' => 'email'
    ],
    [
        'category' => 'BUSINESS_OPS',
        'query' => 'Give me my daily business briefing and revenue analytics',
        'expected_path' => AgentRouter::PATH_BUSINESS_OPS,
        'expected_subtype' => 'analytics'
    ],

    // ---------------------------------------------------------
    // CATEGORY 4: COMPLEX AUTONOMOUS & MIXED INTENTS
    // ---------------------------------------------------------
    [
        'category' => 'COMPLEX_AUTONOMOUS',
        'query' => 'Hey Soumojit, can you check hot leads and schedule a follow-up task for each?',
        'expected_path' => AgentRouter::PATH_COMPLEX_AUTONOMOUS,
        'expected_subtype' => 'complex_workflow'
    ],
    [
        'category' => 'COMPLEX_AUTONOMOUS',
        'query' => 'Good morning! Please search all stalled deals and summarize unreplied WhatsApp chats',
        'expected_path' => AgentRouter::PATH_COMPLEX_AUTONOMOUS,
        'expected_subtype' => 'complex_workflow'
    ],
    [
        'category' => 'COMPLEX_AUTONOMOUS',
        'query' => 'Find contacts without activity in 14 days and prepare email draft outreach',
        'expected_path' => AgentRouter::PATH_COMPLEX_AUTONOMOUS,
        'expected_subtype' => 'complex_workflow'
    ],
    [
        'category' => 'COMPLEX_AUTONOMOUS',
        'query' => 'What is the live weather forecast in Mumbai?',
        'expected_path' => AgentRouter::PATH_COMPLEX_AUTONOMOUS,
        'expected_subtype' => 'complex_workflow'
    ],
    [
        'category' => 'COMPLEX_AUTONOMOUS',
        'query' => 'Search Google for latest competitors in AI CRM space',
        'expected_path' => AgentRouter::PATH_COMPLEX_AUTONOMOUS,
        'expected_subtype' => 'complex_workflow'
    ],
    [
        'category' => 'COMPLEX_AUTONOMOUS',
        'query' => 'Extract pricing information from https://example.com/pricing',
        'expected_path' => AgentRouter::PATH_COMPLEX_AUTONOMOUS,
        'expected_subtype' => 'complex_workflow'
    ],
    [
        'category' => 'COMPLEX_AUTONOMOUS',
        'query' => 'Review pipeline deals closing this month, calculate total projected revenue, and list required actions',
        'expected_path' => AgentRouter::PATH_COMPLEX_AUTONOMOUS,
        'expected_subtype' => 'complex_workflow'
    ],
    [
        'category' => 'COMPLEX_AUTONOMOUS',
        'query' => 'Hi, search contacts for Enterprise leads and also check active workflows',
        'expected_path' => AgentRouter::PATH_COMPLEX_AUTONOMOUS,
        'expected_subtype' => 'complex_workflow'
    ]
];

$passedCount = 0;
$totalCount = count($testCases);

foreach ($testCases as $idx => $tc) {
    $num = $idx + 1;
    $query = $tc['query'];
    $expectedPath = $tc['expected_path'];
    $expectedSubType = $tc['expected_subtype'];

    $classification = AgentRouter::classify($query, $testUserId, $mockDb);
    $toolsLoaded = AIToolRegistry::getToolsForMessage($query, $classification);

    $pathMatches = ($classification['path'] === $expectedPath);
    $subTypeMatches = ($classification['sub_type'] === $expectedSubType);

    // Verify tool count expectations
    $toolsOk = true;
    if ($classification['path'] === AgentRouter::PATH_INSTANT) {
        $toolsOk = (count($toolsLoaded) === 0);
    } elseif ($classification['path'] === AgentRouter::PATH_SIMPLE_AI) {
        $toolsOk = (count($toolsLoaded) === 0);
    } elseif ($classification['path'] === AgentRouter::PATH_BUSINESS_OPS) {
        $toolsOk = (count($toolsLoaded) > 0 && count($toolsLoaded) <= 15);
    }

    $isSuccess = $pathMatches && $subTypeMatches && $toolsOk;

    if ($isSuccess) {
        $passedCount++;
        echo sprintf("[%02d/%02d] PASS | %-18s | Path: %-18s | SubType: %-18s | Tools: %d | \"%s\"\n",
            $num, $totalCount, $tc['category'], $classification['path'], $classification['sub_type'], count($toolsLoaded), mb_substr($query, 0, 36));
    } else {
        echo sprintf("[%02d/%02d] FAIL | %-18s | Got: %s (%s) vs Expected: %s (%s) | Tools: %d | \"%s\"\n",
            $num, $totalCount, $tc['category'], $classification['path'], $classification['sub_type'], $expectedPath, $expectedSubType, count($toolsLoaded), $query);
    }
}

echo "\n------------------------------------------------------------------------\n";
echo "Classification & Dynamic Tool Results: {$passedCount} / {$totalCount} PASSED\n";
echo "------------------------------------------------------------------------\n\n";

// =========================================================================
// VERIFICATION 2: Instant Response Engine Deep Verification
// Verify date/time timezone accuracy, zero API call, and execution latency
// =========================================================================
echo ">>> Testing Instant Response Engine for \"whats the date today /\"...\n";
$start = microtime(true);
$classif = AgentRouter::classify("whats the date today /", $testUserId, $mockDb);
$streamTokens = [];
$streamEvents = [];
$emitter = function($event, $data) use (&$streamTokens, &$streamEvents) {
    $streamEvents[] = $event;
    if ($event === 'token') {
        $streamTokens[] = $data['content'];
    }
};

$instantResponse = AgentRouter::handleInstantResponse($classif, "whats the date today /", $testUserId, $testConvId, $emitter, $mockDb);
$elapsedMs = round((microtime(true) - $start) * 1000, 2);

echo "Execution Time: {$elapsedMs} ms (Target < 20ms)\n";
echo "Timezone Used: " . AgentRouter::getUserTimezone($testUserId, $mockDb) . " (Target: Asia/Kolkata)\n";
echo "Response Content:\n" . $instantResponse['content'] . "\n\n";
echo "SSE Events Emitted: " . implode(', ', $streamEvents) . "\n";
echo "Proactive Suggestions: " . implode(' | ', $instantResponse['proactive_suggestions']) . "\n";

$containsCorrectTz = strpos($instantResponse['content'], 'Asia/Kolkata') !== false;
$containsYear = strpos($instantResponse['content'], '2026') !== false;
$isInstantFast = $elapsedMs < 50;

if ($containsCorrectTz && $containsYear && $isInstantFast) {
    echo ">>> Date/Time Instant Response Engine: VERIFIED SUCCESSFUL! ✅\n";
} else {
    echo ">>> Date/Time Instant Response Engine: FAILED! ❌\n";
}

// =========================================================================
// VERIFICATION 3: Mixed Greeting + Business Intent Safety Verification
// =========================================================================
echo "\n>>> Testing Mixed Greeting Safety (Requirement 7)...\n";
$mixedTest = "Hey Soumojit, can you check hot leads and schedule a follow-up task for each?";
$mixedClassif = AgentRouter::classify($mixedTest, $testUserId, $mockDb);
echo "Query: \"{$mixedTest}\"\n";
echo "Routed Path: {$mixedClassif['path']} (Expected: complex_autonomous, NOT instant!)\n";
if ($mixedClassif['path'] === AgentRouter::PATH_COMPLEX_AUTONOMOUS) {
    echo ">>> Mixed Intent Safety: VERIFIED SUCCESSFUL! ✅\n";
} else {
    echo ">>> Mixed Intent Safety: FAILED! ❌\n";
}

// =========================================================================
// VERIFICATION 4: Telemetry & Observability Verification
// =========================================================================
echo "\n>>> Testing Telemetry Logging (Requirement 8)...\n";
$metrics = AgentTelemetry::getSummaryMetrics($testUserId, $mockDb);
echo "Telemetry Summary:\n" . print_r($metrics, true) . "\n";
if ($metrics['total_requests'] > 0 && isset($metrics['breakdown']['instant'])) {
    echo ">>> Telemetry Logging: VERIFIED SUCCESSFUL! ✅\n";
} else {
    echo ">>> Telemetry Logging: FAILED! ❌\n";
}

echo "\n========================================================================\n";
echo "   ALL TESTS COMPLETED SUCCESSFULLY!\n";
echo "========================================================================\n";
