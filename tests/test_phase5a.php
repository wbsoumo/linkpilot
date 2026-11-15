<?php
// tests/test_phase5a.php
// Autonomous Agent Engine 2.0: Regression Test Suite for Phase 5A.
// Tests: Timezone/DST cron math, atomic claiming, stale lock recovery, missed-run handling,
// conversational slot-filling, single-use content-hash approval tokens, batch digest, and backward compatibility.

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/agent_db_migration.php';
require_once __DIR__ . '/../backend/agent/AgentMigration2.php';
require_once __DIR__ . '/../backend/agent/AgentMigration4.php';
require_once __DIR__ . '/../backend/agent/AgentMigration5.php';
require_once __DIR__ . '/../backend/agent/WorkflowScheduler.php';
require_once __DIR__ . '/../backend/agent/SlotFillingEngine.php';
require_once __DIR__ . '/../backend/agent/BatchApprovalEngine.php';
require_once __DIR__ . '/../backend/agent/WorkflowEngine.php';
require_once __DIR__ . '/../backend/agent/TaskPlanDAG.php';
require_once __DIR__ . '/../backend/agent/ExecutionEngine.php';
require_once __DIR__ . '/../backend/agent_orchestrator.php';

// Setup in-memory SQLite isolated database
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Initialize base CRM tables in SQLite
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    name TEXT,
    email TEXT,
    role TEXT DEFAULT 'admin',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);");
$pdo->exec("INSERT INTO users (id, name, email, role) VALUES (1, 'Soumojit Saha', 'wbsoumo@gmail.com', 'admin');");
$pdo->exec("INSERT INTO users (id, name, email, role) VALUES (2, 'Other Tenant', 'other@tenant.com', 'user');");

$pdo->exec("CREATE TABLE IF NOT EXISTS external_app_connections (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    provider TEXT NOT NULL,
    email TEXT,
    connected_email TEXT,
    scopes TEXT,
    status TEXT DEFAULT 'active'
);");
$pdo->exec("INSERT INTO external_app_connections (user_id, provider, email, connected_email, scopes, status) VALUES (
    1, 'google', 'wbsoumo@gmail.com', 'wbsoumo@gmail.com', 'https://www.googleapis.com/auth/gmail.send,https://www.googleapis.com/auth/calendar.events', 'active'
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS smtp_configs (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    sender_email TEXT NOT NULL,
    sender_name TEXT,
    is_active INTEGER DEFAULT 1
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS crm_contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    email TEXT,
    phone TEXT,
    company TEXT,
    city TEXT,
    tags TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS ai_agent_memory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    key_name TEXT NOT NULL,
    value_text TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);");
$pdo->exec("INSERT INTO ai_agent_memory (user_id, key_name, value_text) VALUES (1, 'agent_name', 'Pingu');");
$pdo->exec("INSERT INTO ai_agent_memory (user_id, key_name, value_text) VALUES (1, 'timezone', 'Asia/Kolkata');");

// Run Migrations
AgentDBMigration::run($pdo);
AgentMigration2::run($pdo);
AgentMigration4::run($pdo);
AgentMigration5::run($pdo);

$testCount = 0;
$passCount = 0;

function assertTest($condition, $description) {
    global $testCount, $passCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "========================================================================\n";
echo "  LINKPILOT AI: AUTONOMOUS AGENT ENGINE 2.0 REGRESSION SUITE\n";
echo "  Phase 5A: Scheduling, Background Execution & Morning Review Digest\n";
echo "========================================================================\n\n";

// =========================================================================
// TEST SUITE 1: Migration & Schema Verification
// =========================================================================
echo "TEST SUITE 1: Migration & Schema Verification\n";
assertTest(class_exists('AgentMigration5'), "AgentMigration5 class is loaded");
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
assertTest(in_array('ai_agent_schedules', $tables), "ai_agent_schedules table exists in database");
assertTest(in_array('ai_agent_schedule_runs', $tables), "ai_agent_schedule_runs table exists in database");
assertTest(in_array('ai_agent_batch_approvals', $tables), "ai_agent_batch_approvals table exists in database");

// =========================================================================
// TEST SUITE 2: Cron Validation, Timezone Math & DST Handling
// =========================================================================
echo "\nTEST SUITE 2: Cron Validation, Timezone Math & DST Handling\n";
assertTest(WorkflowScheduler::validateCron('0 9 * * *'), "Validates standard 5-part cron '0 9 * * *'");
assertTest(WorkflowScheduler::validateCron('*/15 * * * *'), "Validates step cron '*/15 * * * *'");
assertTest(WorkflowScheduler::validateCron('0 9 * * 1-5'), "Validates weekday cron '0 9 * * 1-5'");
assertTest(WorkflowScheduler::validateCron('@daily'), "Validates shortcut '@daily'");
assertTest(WorkflowScheduler::validateCron('@hourly'), "Validates shortcut '@hourly'");
assertTest(!WorkflowScheduler::validateCron('invalid_cron_string'), "Rejects malformed cron string");
assertTest(!WorkflowScheduler::validateCron('* * * *'), "Rejects incomplete 4-part cron");
assertTest(!WorkflowScheduler::validateCron('60 * * * *'), "Rejects out-of-bounds minute 60");

// Timezone test: 09:00 in Asia/Kolkata vs UTC
$baseTime = new DateTimeImmutable('2026-11-16 00:00:00', new DateTimeZone('UTC'));
$nextRunKolkata = WorkflowScheduler::calculateNextRun('0 9 * * *', 'Asia/Kolkata', $baseTime);
assertTest($nextRunKolkata->format('H:i') === '09:00', "Calculates 09:00 correctly in Asia/Kolkata timezone");
assertTest($nextRunKolkata->getTimezone()->getName() === 'Asia/Kolkata', "Preserves user timezone Asia/Kolkata");

// Daylight-saving transition test: London (BST -> GMT transition)
$nextRunLondon = WorkflowScheduler::calculateNextRun('0 8 * * *', 'Europe/London', $baseTime);
assertTest($nextRunLondon->format('H:i') === '08:00', "Calculates local 08:00 in Europe/London DST transition window");

// =========================================================================
// TEST SUITE 3: Schedule Lifecycle & Multi-Tenant Isolation
// =========================================================================
echo "\nTEST SUITE 3: Schedule Lifecycle & Multi-Tenant Isolation\n";
$sched = WorkflowScheduler::createSchedule(
    1,
    'Dental Leads London Daily',
    '0 9 * * *',
    'Europe/London',
    [
        'target_industry' => 'Dental Clinics',
        'target_location' => 'London',
        'lead_count' => 10,
        'value_proposition' => 'LinkPilot AI CRM demo'
    ],
    null,
    WorkflowScheduler::POLICY_DIGEST_REVIEW,
    WorkflowScheduler::MISSED_SKIP,
    $pdo
);

assertTest(!empty($sched['id']), "Schedule created with unique ID {$sched['id']}");
assertTest($sched['status'] === 'active', "Initial schedule status is 'active'");
assertTest($sched['approval_policy'] === 'digest_review', "Default approval policy is strictly 'digest_review'");
assertTest($sched['missed_run_policy'] === 'skip', "Missed run policy is 'skip'");

// Per-workspace isolation test: User 2 cannot access or mutate User 1's schedule
$user2View = WorkflowScheduler::getSchedule($sched['id'], 2, $pdo);
assertTest($user2View === null, "Tenant isolation: User 2 cannot view User 1's schedule");

$user2Pause = WorkflowScheduler::pauseSchedule($sched['id'], 2, $pdo);
assertTest($user2Pause === false, "Tenant isolation: User 2 cannot pause User 1's schedule");

// User 1 lifecycle
$paused = WorkflowScheduler::pauseSchedule($sched['id'], 1, $pdo);
assertTest($paused === true, "User 1 can pause active schedule");
$schedPaused = WorkflowScheduler::getSchedule($sched['id'], 1, $pdo);
assertTest($schedPaused['status'] === 'paused', "Schedule status is updated to 'paused'");

$resumed = WorkflowScheduler::resumeSchedule($sched['id'], 1, $pdo);
assertTest($resumed === true, "User 1 can resume paused schedule");
$schedResumed = WorkflowScheduler::getSchedule($sched['id'], 1, $pdo);
assertTest($schedResumed['status'] === 'active', "Schedule status resumed to 'active'");

// =========================================================================
// TEST SUITE 4: Atomic Claiming, Leases & Stale Lock Recovery
// =========================================================================
echo "\nTEST SUITE 4: Atomic Claiming, Leases & Stale Lock Recovery\n";

// Set next_run_at to past so it is due
$pastTime = date('Y-m-d H:i:s', time() - 300);
$pdo->prepare("UPDATE ai_agent_schedules SET next_run_at = ?, lease_token = NULL, lease_expires_at = NULL WHERE id = ?")
    ->execute([$pastTime, $sched['id']]);

$claimed = WorkflowScheduler::claimDueSchedules(5, 300, $pdo);
assertTest(count($claimed) === 1, "Atomically claimed 1 due schedule");
assertTest($claimed[0]['id'] === $sched['id'], "Claimed schedule matches target schedule ID");
assertTest(!empty($claimed[0]['lease_token']), "Generated atomic lease token {$claimed[0]['lease_token']}");

// Concurrent attempt test: Second worker attempt while lease is active MUST be rejected
$concurrentClaims = WorkflowScheduler::claimDueSchedules(5, 300, $pdo);
assertTest(count($concurrentClaims) === 0, "Concurrent claim attempt rejected: schedule is locked by lease");

// Stale lock recovery test: Simulate worker crash where lease expired in the past
$expiredLeaseTime = date('Y-m-d H:i:s', time() - 60);
$pdo->prepare("UPDATE ai_agent_schedules SET lease_expires_at = ? WHERE id = ?")
    ->execute([$expiredLeaseTime, $sched['id']]);

$recoveredClaims = WorkflowScheduler::claimDueSchedules(5, 300, $pdo);
assertTest(count($recoveredClaims) === 1, "Stale lock recovery: expired lease reclaimed cleanly by subsequent worker");
$recoveredLeaseToken = $recoveredClaims[0]['lease_token'];

// Complete schedule run
$completed = WorkflowScheduler::completeScheduleRun($sched['id'], $recoveredLeaseToken, null, $pdo);
assertTest($completed === true, "Schedule run completed and lease lock released");

$schedAfterRun = WorkflowScheduler::getSchedule($sched['id'], 1, $pdo);
assertTest(empty($schedAfterRun['lease_token']), "Lease token cleared on completion");
assertTest($schedAfterRun['next_run_at'] > date('Y-m-d H:i:s'), "Next execution slot advanced into the future");

// =========================================================================
// TEST SUITE 5: Conversational Slot-Filling State Machine
// =========================================================================
echo "\nTEST SUITE 5: Conversational Slot-Filling State Machine\n";

// 1. Incomplete initial prompt (missing location, value prop)
$msgIncomplete = "Find 10 dental leads daily morning and mail them";
$slotsIncomplete = SlotFillingEngine::extractSlots($msgIncomplete, [], 1, [], $pdo);
assertTest($slotsIncomplete[SlotFillingEngine::FIELD_INDUSTRY] === 'Dental', "Extracted industry: Dental");
assertTest($slotsIncomplete[SlotFillingEngine::FIELD_LEAD_COUNT] === 10, "Extracted lead count: 10");
assertTest($slotsIncomplete[SlotFillingEngine::FIELD_SCHEDULE] === '0 9 * * *', "Extracted schedule: 09:00 AM daily");
assertTest($slotsIncomplete[SlotFillingEngine::FIELD_APPROVAL_POLICY] === 'digest_review', "Default approval policy is strictly 'digest_review'");

$valIncomplete = SlotFillingEngine::validateSlots($slotsIncomplete, 1, $pdo);
assertTest($valIncomplete['is_complete'] === false, "Incomplete slots correctly flagged as not complete");
assertTest(in_array(SlotFillingEngine::FIELD_LOCATION, $valIncomplete['missing_fields']), "Detected missing location");
assertTest(in_array(SlotFillingEngine::FIELD_VALUE_PROP, $valIncomplete['missing_fields']), "Detected missing value proposition");

$counterQ = SlotFillingEngine::generateCounterQuestion($valIncomplete['missing_fields'], $slotsIncomplete, 1, $pdo);
assertTest(stripos($counterQ, 'geographic') !== false || stripos($counterQ, 'city') !== false, "Counter-question asks for target city/location");
assertTest(stripos($counterQ, 'service') !== false || stripos($counterQ, 'value proposition') !== false, "Counter-question asks for value proposition");

// 2. Bounds enforcement on lead count (1 to 25)
$msgOverflow = "Find 100 fintech leads daily in Dubai offering AI tools";
$slotsOverflow = SlotFillingEngine::extractSlots($msgOverflow, [], 1, [], $pdo);
assertTest($slotsOverflow[SlotFillingEngine::FIELD_LEAD_COUNT] === 25, "Bounded lead count to max limit of 25 (requested 100)");

$msgUnderflow = "Find 0 real estate leads daily in London";
$slotsUnderflow = SlotFillingEngine::extractSlots($msgUnderflow, [], 1, [], $pdo);
assertTest($slotsUnderflow[SlotFillingEngine::FIELD_LEAD_COUNT] >= 1, "Bounded lead count to min limit of 1 (requested 0)");

// 3. Complete Slots
$completeSlots = [
    SlotFillingEngine::FIELD_INDUSTRY => 'Real Estate',
    SlotFillingEngine::FIELD_LOCATION => 'Dubai',
    SlotFillingEngine::FIELD_LEAD_COUNT => 10,
    SlotFillingEngine::FIELD_VALUE_PROP => 'AI CRM Lead Automation',
    SlotFillingEngine::FIELD_SENDER_IDENTITY => 'wbsoumo@gmail.com', // Verified in external_app_connections
    SlotFillingEngine::FIELD_SCHEDULE => '0 9 * * *',
    SlotFillingEngine::FIELD_APPROVAL_POLICY => 'digest_review'
];
$valComplete = SlotFillingEngine::validateSlots($completeSlots, 1, $pdo);
assertTest($valComplete['is_complete'] === true, "Complete slots validated successfully");
assertTest(empty($valComplete['missing_fields']), "No missing fields for complete slots");

// 4. Unverified sender identity rejection
$unverifiedSlots = $completeSlots;
$unverifiedSlots[SlotFillingEngine::FIELD_SENDER_IDENTITY] = 'unverified_hacker@unknown.com';
$valUnverified = SlotFillingEngine::validateSlots($unverifiedSlots, 1, $pdo);
assertTest($valUnverified['is_complete'] === false, "Rejects unverified sender identity not connected in workspace");
assertTest(count($valUnverified['errors']) > 0, "Error emitted for unverified sender");

// =========================================================================
// TEST SUITE 6: Cryptographic Batch Approval Tokens & Morning Digest
// =========================================================================
echo "\nTEST SUITE 6: Cryptographic Batch Approval Tokens & Morning Digest\n";

$drafts = [
    [
        'company_name' => 'Smile Dental Clinic',
        'recipient_email' => 'dr.smith@smiledental.co.uk',
        'subject' => 'AI Patient Scheduling Proposal',
        'body' => 'Dear Dr. Smith, We noticed your practice in London and have a proposal for automated booking.'
    ],
    [
        'company_name' => 'Harley Street Dental',
        'recipient_email' => 'contact@harleystreet.com',
        'subject' => 'AI Consultation Proposal',
        'body' => 'Dear Team, We offer LinkPilot CRM for medical practices.'
    ]
];

$digestBatch = BatchApprovalEngine::createDigestBatch(1, 'run_test_001', 'wf_test_001', $drafts, 72, $pdo);
assertTest($digestBatch['total_items'] === 2, "Created batch approval for 2 proposal items");
assertTest(count($digestBatch['items']) === 2, "Generated 2 distinct item approval tokens");
$item1 = $digestBatch['items'][0];
$item2 = $digestBatch['items'][1];

// 1. Preview Item
$preview = BatchApprovalEngine::previewItem(1, $item1['token'], $pdo);
assertTest($preview['status'] === 'pending', "Item preview status is 'pending'");
assertTest($preview['recipient_email'] === 'dr.smith@smiledental.co.uk', "Preview recipient matches draft");
assertTest($preview['draft']['company_name'] === 'Smile Dental Clinic', "Preview draft payload loaded");

// 2. Tenant isolation on approvals: User 2 cannot access User 1's token
$unauthorizedTokenAccess = false;
try {
    BatchApprovalEngine::previewItem(2, $item1['token'], $pdo);
    $unauthorizedTokenAccess = true;
} catch (Throwable $e) {}
assertTest($unauthorizedTokenAccess === false, "Tenant isolation: User 2 cannot access User 1's approval token");

// 3. Edit Item: Recomputes content hash and issues new token
$edited = BatchApprovalEngine::editItem(1, $item1['token'], 'Updated Subject Line', 'Updated email body content', $pdo);
assertTest($edited['new_token'] !== $item1['token'], "Editing draft generates new cryptographic approval token");
assertTest($edited['content_hash'] !== $preview['content_hash'], "Content hash updated to reflect edited subject & body");

// 4. Old token invalidation
$oldTokenFailed = false;
try {
    BatchApprovalEngine::approveItem(1, $item1['token'], $pdo);
} catch (Throwable $e) {
    $oldTokenFailed = true;
}
assertTest($oldTokenFailed === true, "Previous approval token invalidated after content edit");

// 5. Individual Approval with New Token
$approvedItem = BatchApprovalEngine::approveItem(1, $edited['new_token'], $pdo);
assertTest($approvedItem['status'] === 'approved', "Individual approval successful for item 1");

// 6. Single-use guard: Re-use of approved token MUST be rejected
$reusedTokenFailed = false;
try {
    BatchApprovalEngine::approveItem(1, $edited['new_token'], $pdo);
} catch (Throwable $e) {
    $reusedTokenFailed = true;
}
assertTest($reusedTokenFailed === true, "Single-use enforcement: Re-use of already approved token rejected");

// 7. Approve All for remaining items in batch
$approveAllRes = BatchApprovalEngine::approveAll(1, 'run_test_001', $pdo);
assertTest($approveAllRes['approved_count'] === 1, "ApproveAll approved remaining pending item (item 2)");

// 8. Rejections
$draftRej = [
    [
        'company_name' => 'Rejected Corp',
        'recipient_email' => 'reject@corp.com',
        'subject' => 'Bad pitch',
        'body' => 'Bad body'
    ]
];
$batchRej = BatchApprovalEngine::createDigestBatch(1, 'run_rej_001', 'wf_rej_001', $draftRej, 72, $pdo);
$rejTok = $batchRej['items'][0]['token'];
$rejRes = BatchApprovalEngine::rejectItem(1, $rejTok, 'Irrelevant niche', $pdo);
assertTest($rejRes['status'] === 'rejected', "Individual item rejected successfully");

// Rejection blocks subsequent approval
$approvedAfterRej = false;
try {
    BatchApprovalEngine::approveItem(1, $rejTok, $pdo);
} catch (Throwable $e) {
    $approvedAfterRej = true;
}
assertTest($approvedAfterRej === true, "Rejected token cannot be subsequently approved");

// =========================================================================
// TEST SUITE 7: End-to-End Conversational Slot-Filling & Orchestrator Flow
// =========================================================================
echo "\nTEST SUITE 7: End-to-End Conversational Slot-Filling & Orchestrator Flow\n";

$conv = AgentOrchestrator::createConversation(1, 'Outreach Setup Test', $pdo);
$convId = $conv['id'];

// Turn 1: Incomplete prompt -> Agent must ask focused counter-question (not activate schedule!)
$reply1 = AgentOrchestrator::processChat(1, $convId, "Find 10 leads daily morning and mail them with my mail ID as a proposal mail", [], null, $pdo);
assertTest($reply1['status'] === 'waiting_for_user', "Turn 1 paused with status 'waiting_for_user'");
assertTest(stripos($reply1['content'], 'industry') !== false || stripos($reply1['content'], 'city') !== false, "Turn 1 counter-question asks for industry/location");
assertTest(in_array('target_industry', $reply1['missing_fields']), "Identified missing target_industry");

// Verify no premature schedule was created
$schedulesBeforeComplete = WorkflowScheduler::listSchedules(1, null, $pdo);
assertTest(count($schedulesBeforeComplete) === 1, "No premature schedule activated during incomplete turn");

// Turn 2: User provides missing industry & location -> Agent validates and activates schedule!
$reply2 = AgentOrchestrator::processChat(1, $convId, "Target Dental Clinics in London with AI CRM automation proposal", [], null, $pdo);
assertTest($reply2['status'] === 'completed', "Turn 2 successfully completed schedule activation");
assertTest(stripos($reply2['content'], 'Schedule Configured & Activated') !== false, "Turn 2 confirmed schedule activation");
assertTest(!empty($reply2['schedule']['id']), "Turn 2 created persistent schedule");

$schedulesAfterComplete = WorkflowScheduler::listSchedules(1, null, $pdo);
assertTest(count($schedulesAfterComplete) === 2, "Persistent schedule saved in database");

echo "\n------------------------------------------------------------------------\n";
echo "  PHASE 5A TEST SUMMARY: {$passCount} Passed, " . ($testCount - $passCount) . " Failed\n";
echo "------------------------------------------------------------------------\n";

if ($passCount === $testCount) {
    echo "All Phase 5A regression tests PASSED successfully with 100% compliance!\n\n";
} else {
    exit(1);
}
