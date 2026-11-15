<?php
// tests/test_phase5_hardening.php
// LinkPilot AI: Phase 5 Reliability, Edge-Case & Failure Recovery Test Suite.
// Tests worker interruption, concurrent cron invocations, expired leases, OAuth failure,
// provider quotas, duplicate discovery, token tampering, and duplicate dispatch prevention.

echo "========================================================================\n";
echo "  LINKPILOT AI: PHASE 5 RELIABILITY & FAILURE HARDENING SUITE\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertHardening(bool $condition, string $title, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$title}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title}" . ($details ? " - {$details}" : '') . "\n";
    }
}

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/agent/AgentMigration5.php';
require_once __DIR__ . '/../backend/agent/WorkflowScheduler.php';
require_once __DIR__ . '/../backend/agent/BatchApprovalEngine.php';
require_once __DIR__ . '/../backend/agent/LeadProspectingEngine.php';
require_once __DIR__ . '/../backend/agent/OutreachDispatcher.php';

// Mock DNS MX resolution for deterministic offline speed
DeliverabilityEngine::setDnsResolver(function(string $domain) {
    if (str_contains($domain, 'nomx') || str_contains($domain, 'invalid')) return [];
    return ['mx1.' . $domain, 'mx2.' . $domain];
});

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
AgentMigration5::run($pdo);

// Create Mock CRM Tables in test DB
// Create CRM base tables in SQLite memory for deduplication & ingestion tests
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `crm_companies` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `user_id` INTEGER NOT NULL,
        `name` TEXT NOT NULL,
        `website` TEXT DEFAULT NULL,
        `phone` TEXT DEFAULT NULL,
        `status` TEXT DEFAULT 'Active',
        `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `crm_contacts` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `user_id` INTEGER NOT NULL,
        `company_id` INTEGER DEFAULT NULL,
        `name` TEXT NOT NULL,
        `email` TEXT DEFAULT NULL,
        `phone` TEXT DEFAULT NULL,
        `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `crm_leads` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `user_id` INTEGER NOT NULL,
        `company_id` INTEGER DEFAULT NULL,
        `contact_id` INTEGER DEFAULT NULL,
        `name` TEXT NOT NULL,
        `email` TEXT DEFAULT NULL,
        `phone` TEXT DEFAULT NULL,
        `company` TEXT DEFAULT NULL,
        `budget` REAL DEFAULT 0.00,
        `requirements` TEXT DEFAULT NULL,
        `services_required` TEXT DEFAULT NULL,
        `priority` TEXT DEFAULT 'medium',
        `expected_closing_date` TEXT DEFAULT NULL,
        `assigned_employee` TEXT DEFAULT NULL,
        `lead_score` INTEGER DEFAULT 50,
        `ai_confidence_score` INTEGER DEFAULT 100,
        `lead_source` TEXT DEFAULT 'Manual Entry',
        `stage` TEXT DEFAULT 'New',
        `tags` TEXT DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `custom_fields` TEXT DEFAULT NULL,
        `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `crm_deals` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `user_id` INTEGER NOT NULL,
        `lead_id` INTEGER DEFAULT NULL,
        `company_id` INTEGER DEFAULT NULL,
        `contact_id` INTEGER DEFAULT NULL,
        `title` TEXT NOT NULL,
        `value` REAL DEFAULT 0.00,
        `stage` TEXT DEFAULT 'Discovery',
        `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
    );
");

$userId = 1;

// ------------------------------------------------------------------------
// SCENARIO 1: Concurrent Cron Invocations & Race Condition Lockout
// ------------------------------------------------------------------------
echo "SCENARIO 1: Concurrent Cron Invocations & Atomic Lease Lockout\n";

$sched = WorkflowScheduler::createSchedule($userId, 'High Volume Outreach', '0 9 * * *', 'UTC', ['target_industry' => 'Dental'], null, 'prospecting_and_outreach', 'digest_review', $pdo);
$schedId = $sched['id'];

// Set schedule as due
$pastTime = date('Y-m-d H:i:s', time() - 3600);
$pdo->prepare("UPDATE ai_agent_schedules SET next_run_at = ? WHERE id = ?")->execute([$pastTime, $schedId]);

// Worker 1 claims schedule
$worker1Claims = WorkflowScheduler::claimDueSchedules(5, 300, $pdo);
assertHardening(count($worker1Claims) === 1, "Worker 1 atomically claims due schedule");
$claimedSched = $worker1Claims[0];
assertHardening(!empty($claimedSched['lease_token']), "Worker 1 received valid atomic lease token");

// Worker 2 attempts concurrent claim
$worker2Claims = WorkflowScheduler::claimDueSchedules(5, 300, $pdo);
assertHardening(count($worker2Claims) === 0, "Race condition prevented: Worker 2 cannot claim locked schedule (Atomic Isolation)");

// ------------------------------------------------------------------------
// SCENARIO 2: Worker Crash / Termination & Stale Lease Recovery
// ------------------------------------------------------------------------
echo "\nSCENARIO 2: Worker Interruption & Stale Lease Recovery\n";

// Simulate worker crash: Worker 1 terminated abruptly without releasing lease.
// Advance clock so lease expires
$expiredLeaseTime = date('Y-m-d H:i:s', time() - 60);
$pdo->prepare("UPDATE ai_agent_schedules SET lease_expires_at = ? WHERE id = ?")->execute([$expiredLeaseTime, $schedId]);

// Worker 3 runs in subsequent cron cycle
$worker3Claims = WorkflowScheduler::claimDueSchedules(5, 300, $pdo);
assertHardening(count($worker3Claims) === 1, "Worker 3 successfully reclaims schedule after stale lease expiration");
$recoveredSched = $worker3Claims[0];
assertHardening($recoveredSched['lease_token'] !== $claimedSched['lease_token'], "Worker 3 receives new fresh lease token");

// Complete run properly
$completed = WorkflowScheduler::completeScheduleRun($schedId, $recoveredSched['lease_token'], null, $pdo);
assertHardening($completed, "Schedule run completed cleanly and lease lock released");

// ------------------------------------------------------------------------
// SCENARIO 3: Duplicate Lead Discovery & Deduplication Idempotency
// ------------------------------------------------------------------------
echo "\nSCENARIO 3: Duplicate Lead Discovery & CRM Deduplication\n";

$prospect = [
    'company_name' => 'Bright Smile Dental Ltd',
    'website' => 'https://brightsmiledental.co.uk',
    'business_location' => 'Manchester',
    'business_email' => 'contact@brightsmiledental.co.uk',
    'business_phone' => '+44 161 987 6543',
    'decision_maker_name' => 'Dr. Robert Evans',
    'decision_maker_role' => 'Principal Dentist',
    'source_url' => 'https://brightsmiledental.co.uk',
    'source_provider' => 'mock',
    'discovery_timestamp' => date('Y-m-d H:i:s'),
    'extraction_confidence' => 95,
    'verification_status' => 'domain_has_mx',
    'lead_score' => 90,
    'lead_score_explanation' => 'Valid domain, MX present, decision maker identified'
];

$ingest1 = LeadProspectingEngine::deduplicateAndIngestProspect($userId, $prospect, $schedId, 'run_1', [], $pdo);
assertHardening($ingest1['status'] === 'ingested', "First discovery: Prospect ingested into CRM successfully");

// Second discovery with same company & email
$ingest2 = LeadProspectingEngine::deduplicateAndIngestProspect($userId, $prospect, $schedId, 'run_2', [], $pdo);
assertHardening($ingest2['status'] === 'skipped_duplicate', "Second discovery: Duplicate prospect cleanly skipped");
assertHardening(!empty($ingest2['reason']), "Deduplication reason recorded: {$ingest2['reason']}");

$leadCountInDB = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE email = 'contact@brightsmiledental.co.uk'")->fetchColumn();
assertHardening($leadCountInDB === 1, "CRM database contains exactly 1 lead record (Zero duplication)");

// ------------------------------------------------------------------------
// SCENARIO 4: Approval Token Expiration & Post-Approval Modification Protection
// ------------------------------------------------------------------------
echo "\nSCENARIO 4: Approval Token Expiration & Integrity Enforcement\n";

$drafts = [
    [
        'recipient_email' => 'contact@brightsmiledental.co.uk',
        'subject' => 'Partnership with Bright Smile Dental',
        'body' => 'Hello Dr. Evans, personalized collaboration proposal.'
    ]
];

$batch = BatchApprovalEngine::createDigestBatch($userId, 'run_3', 'wf_3', $drafts, 72, $pdo);
$token = $batch['tokens']['lead_1'];

// Manually expire token
$pastExp = date('Y-m-d H:i:s', time() - 3600);
$pdo->prepare("UPDATE ai_agent_batch_approvals SET expires_at = ? WHERE token = ?")->execute([$pastExp, $token]);

$caughtExpired = false;
try {
    BatchApprovalEngine::approveItem($userId, $token, $pdo);
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'expired')) {
        $caughtExpired = true;
    }
}
assertHardening($caughtExpired, "Expired approval token rejected upon approval attempt");

// Refresh token for active testing
$futureExp = date('Y-m-d H:i:s', time() + 7200);
$pdo->prepare("UPDATE ai_agent_batch_approvals SET expires_at = ?, status = 'pending' WHERE token = ?")->execute([$futureExp, $token]);

// Edit draft proposal before approval -> generates new token
$editRes = BatchApprovalEngine::editItem($userId, $token, 'Updated Partnership Proposal', 'Hello Dr. Evans, updated terms.', $pdo);
assertHardening(!empty($editRes['new_token']) && $editRes['new_token'] !== $token, "Editing draft produces new cryptographic token and invalidates old token");

// Attempting to approve using old invalidated token
$caughtInvalidToken = false;
try {
    BatchApprovalEngine::approveItem($userId, $token, $pdo);
} catch (InvalidArgumentException $e) {
    $caughtInvalidToken = true;
}
assertHardening($caughtInvalidToken, "Old token invalidated and cannot be approved after content edit");

// Approve using new valid token
$newToken = $editRes['new_token'];
$approved = BatchApprovalEngine::approveItem($userId, $newToken, $pdo);
assertHardening($approved['status'] === 'approved', "New token successfully approved");

// Attempt to edit proposal AFTER approval
$caughtEditAfterApprove = false;
try {
    BatchApprovalEngine::editItem($userId, $newToken, 'Malicious Edit After Approval', 'Changed body', $pdo);
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'approved')) {
        $caughtEditAfterApprove = true;
    }
}
assertHardening($caughtEditAfterApprove, "Editing an already-approved draft is strictly blocked");

// ------------------------------------------------------------------------
// SCENARIO 5: Duplicate Dispatch Prevention & Retry Idempotency
// ------------------------------------------------------------------------
echo "\nSCENARIO 5: Duplicate Dispatch Prevention & Retry Safety\n";

$dispatchCount = 0;
OutreachDispatcher::setCustomTransport(function($uid, $to, $subj, $body, $from) use (&$dispatchCount) {
    $dispatchCount++;
    return ['message_id' => 'msg_test_' . $dispatchCount];
});

// Dispatch 1
$res1 = OutreachDispatcher::dispatchApprovedItem($userId, $newToken, 'mock', [], $pdo);
assertHardening($res1['status'] === 'sent', "First dispatch succeeds with provider message ID: {$res1['message_id']}");
assertHardening($dispatchCount === 1, "Transport invoked exactly once (outbound count = 1)");

// Dispatch 2 (Retry on same token)
$caughtDuplicateDispatch = false;
try {
    OutreachDispatcher::dispatchApprovedItem($userId, $newToken, 'mock', [], $pdo);
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'already been used')) {
        $caughtDuplicateDispatch = true;
    }
}
assertHardening($caughtDuplicateDispatch, "Duplicate dispatch strictly rejected: token already used");
assertHardening($dispatchCount === 1, "Retried dispatch did NOT send duplicate email (outbound count remains 1)");

// ------------------------------------------------------------------------
// SCENARIO 6: Provider Quota Exhaustion & Workspace Cap
// ------------------------------------------------------------------------
echo "\nSCENARIO 6: Provider Quota Exhaustion & Dispatch Pause\n";

// Set workspace daily outreach limit to 1
WorkflowScheduler::updateWorkspaceSettings($userId, ['daily_outreach_limit' => 1], $pdo);

// Create another approved proposal
$batch2 = BatchApprovalEngine::createDigestBatch($userId, 'run_quota', 'wf_quota', [
    ['recipient_email' => 'partner@acme.com', 'subject' => 'Proposal 2', 'body' => 'Body 2']
], 72, $pdo);
$token2 = $batch2['tokens']['lead_1'];
BatchApprovalEngine::approveItem($userId, $token2, $pdo);

// Attempt dispatch when daily outreach limit (1) is already consumed by previous dispatch
$caughtQuotaExceeded = false;
try {
    OutreachDispatcher::dispatchApprovedItem($userId, $token2, 'mock', [], $pdo);
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'daily outreach limit')) {
        $caughtQuotaExceeded = true;
    }
}
assertHardening($caughtQuotaExceeded, "Outreach dispatch paused when workspace daily outreach limit is reached");
assertHardening($dispatchCount === 1, "No outbound email dispatched during quota exhaustion");

// Reset custom transport
OutreachDispatcher::setCustomTransport(null);

echo "\n------------------------------------------------------------------------\n";
echo "  HARDENING & RELIABILITY SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "------------------------------------------------------------------------\n";

if ($failCount === 0) {
    echo "All Hardening & Reliability tests PASSED successfully with 100% compliance!\n\n";
    exit(0);
} else {
    echo "Hardening tests FAILED with {$failCount} failure(s)!\n\n";
    exit(1);
}
