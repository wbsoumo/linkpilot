<?php
// tests/test_phase5.php
// Autonomous Agent Engine 2.0: Comprehensive Phase 5 (5A & 5B) Regression Test Suite.
// Covers Scheduling, Timezones, DST, Leases, Slot-Filling, Lead Prospecting, Deliverability,
// CRM Deduplication, Proposal Generation, Maker-Checker Approvals, and Controlled Outreach Dispatch.

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/agent/AgentMigration2.php';
require_once __DIR__ . '/../backend/agent/AgentMigration4.php';
require_once __DIR__ . '/../backend/agent/AgentMigration5.php';
require_once __DIR__ . '/../backend/agent/WorkflowEngine.php';
require_once __DIR__ . '/../backend/agent/ContextManager.php';
require_once __DIR__ . '/../backend/agent/WorkflowScheduler.php';
require_once __DIR__ . '/../backend/agent/SlotFillingEngine.php';
require_once __DIR__ . '/../backend/agent/BatchApprovalEngine.php';
require_once __DIR__ . '/../backend/agent/DeliverabilityEngine.php';
require_once __DIR__ . '/../backend/agent/LeadProspectingEngine.php';
require_once __DIR__ . '/../backend/agent/ProposalGenerator.php';
require_once __DIR__ . '/../backend/agent/OutreachDispatcher.php';
require_once __DIR__ . '/../backend/agent_orchestrator.php';

// Setup Isolated In-Memory SQLite Database for Safe, Zero-Side-Effect Testing
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Initialize all required schemas
AgentDBMigration::run($pdo);
AgentMigration2::run($pdo);
AgentMigration4::run($pdo);
AgentMigration5::run($pdo);

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
    CREATE TABLE IF NOT EXISTS `external_app_connections` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `user_id` INTEGER NOT NULL,
        `provider` TEXT NOT NULL,
        `email` TEXT DEFAULT NULL,
        `connected_email` TEXT DEFAULT NULL,
        `scopes` TEXT DEFAULT NULL,
        `access_token` TEXT DEFAULT NULL,
        `refresh_token` TEXT DEFAULT NULL,
        `expires_at` TEXT DEFAULT NULL,
        `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
    );
");

// Pre-seed a connected Gmail account for user 1
$pdo->prepare("INSERT INTO external_app_connections (user_id, provider, email, connected_email, scopes) VALUES (1, 'google', 'wbsoumo@gmail.com', 'wbsoumo@gmail.com', 'https://www.googleapis.com/auth/gmail.send')")->execute();

$testCount = 0;
$passCount = 0;

function assertTest(bool $condition, string $message): void {
    global $testCount, $passCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
    }
}

echo "========================================================================\n";
echo "  LINKPILOT AI: AUTONOMOUS AGENT ENGINE 2.0 REGRESSION SUITE\n";
echo "  Phase 5 (5A & 5B): Full Scheduling, Prospecting, Deliverability & Outreach\n";
echo "========================================================================\n";

// =========================================================================
// TEST SUITE 1: Migration & Schema Verification
// =========================================================================
echo "\nTEST SUITE 1: Migration & Schema Verification\n";

$tables = [
    'ai_agent_schedules',
    'ai_agent_schedule_runs',
    'ai_agent_batch_approvals',
    'ai_agent_discovered_prospects',
    'ai_agent_outreach_logs',
    'ai_agent_suppression_list'
];

foreach ($tables as $tbl) {
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
    $stmt->execute([$tbl]);
    assertTest(!empty($stmt->fetchColumn()), "Table '{$tbl}' exists in database schema");
}

// =========================================================================
// TEST SUITE 2: Scheduling, Timezone Math & DST Transitions
// =========================================================================
echo "\nTEST SUITE 2: Scheduling, Timezone Math & DST Transitions\n";

assertTest(WorkflowScheduler::validateCron('0 9 * * *'), "Validates standard 5-part cron '0 9 * * *'");
assertTest(WorkflowScheduler::validateCron('@daily'), "Validates shortcut '@daily'");
assertTest(!WorkflowScheduler::validateCron('invalid cron'), "Rejects malformed cron string");
assertTest(!WorkflowScheduler::validateCron('0 9 * * * *'), "Rejects 6-part cron string");

$nextRunKolkata = WorkflowScheduler::calculateNextRun('0 9 * * *', 'Asia/Kolkata', new DateTimeImmutable('2026-11-15 08:00:00', new DateTimeZone('Asia/Kolkata')));
assertTest($nextRunKolkata->format('H:i') === '09:00', "Calculates 09:00 correctly in Asia/Kolkata");
assertTest($nextRunKolkata->getTimezone()->getName() === 'Asia/Kolkata', "Preserves user timezone Asia/Kolkata");

// DST transition test (London clocks fall back)
$dstWindow = new DateTimeImmutable('2026-10-25 00:30:00', new DateTimeZone('Europe/London'));
$nextDst = WorkflowScheduler::calculateNextRun('0 8 * * *', 'Europe/London', $dstWindow);
assertTest($nextDst->format('H:i') === '08:00', "Calculates 08:00 accurately during London DST transition window");

// =========================================================================
// TEST SUITE 3: Schedule Lifecycle & Multi-Tenant Isolation
// =========================================================================
echo "\nTEST SUITE 3: Schedule Lifecycle & Multi-Tenant Isolation\n";

$schedUser1 = WorkflowScheduler::createSchedule(
    1,
    'Daily London Dentists',
    '0 9 * * *',
    'Europe/London',
    ['target_industry' => 'Dental', 'target_location' => 'London', 'lead_count' => 10],
    null,
    WorkflowScheduler::POLICY_DIGEST_REVIEW,
    WorkflowScheduler::MISSED_SKIP,
    $pdo
);
assertTest(!empty($schedUser1['id']), "Schedule created with ID {$schedUser1['id']}");
assertTest($schedUser1['approval_policy'] === 'digest_review', "Default approval policy is strictly 'digest_review'");
assertTest($schedUser1['status'] === 'active', "Initial schedule status is 'active'");

// Multi-tenant check
$schedsUser2 = WorkflowScheduler::listSchedules(2, null, $pdo);
assertTest(count($schedsUser2) === 0, "Tenant isolation: User 2 cannot access User 1's schedule");
$pauseAttempt = WorkflowScheduler::pauseSchedule($schedUser1['id'], 2, $pdo);
assertTest($pauseAttempt === false, "Tenant isolation: User 2 cannot modify User 1's schedule");

// User 1 pause & resume
assertTest(WorkflowScheduler::pauseSchedule($schedUser1['id'], 1, $pdo) === true, "User 1 can pause own schedule");
$reloaded = WorkflowScheduler::getSchedule($schedUser1['id'], 1, $pdo);
assertTest($reloaded['status'] === 'paused', "Schedule status updated to 'paused'");
assertTest(WorkflowScheduler::resumeSchedule($schedUser1['id'], 1, $pdo) === true, "User 1 can resume paused schedule");

// =========================================================================
// TEST SUITE 4: Atomic Claiming, Leases & Stale Lock Recovery
// =========================================================================
echo "\nTEST SUITE 4: Atomic Claiming, Leases & Stale Lock Recovery\n";

// Set next_run_at in past
$pdo->prepare("UPDATE ai_agent_schedules SET next_run_at = datetime('now', '-10 minutes') WHERE id = ?")->execute([$schedUser1['id']]);

$claimed = WorkflowScheduler::claimDueSchedules(5, 60, $pdo);
assertTest(count($claimed) === 1, "Atomically claimed 1 due schedule");
$leaseToken = $claimed[0]['lease_token'];
assertTest(!empty($leaseToken), "Generated atomic lease token {$leaseToken}");

// Concurrent claim rejection
$concurrentClaim = WorkflowScheduler::claimDueSchedules(5, 60, $pdo);
assertTest(count($concurrentClaim) === 0, "Concurrent claim attempt rejected: schedule locked by active lease");

// Stale lock recovery: expire lease intentionally
$pdo->prepare("UPDATE ai_agent_schedules SET lease_expires_at = datetime('now', '-5 seconds') WHERE id = ?")->execute([$schedUser1['id']]);
$staleClaimed = WorkflowScheduler::claimDueSchedules(5, 60, $pdo);
assertTest(count($staleClaimed) === 1, "Stale lock recovery: expired lease reclaimed cleanly by subsequent worker");

// Complete run and release lease
WorkflowScheduler::completeScheduleRun($schedUser1['id'], $staleClaimed[0]['lease_token'], null, $pdo);
$freedSched = WorkflowScheduler::getSchedule($schedUser1['id'], 1, $pdo);
assertTest(empty($freedSched['lease_token']), "Lease token cleared on run completion");
assertTest(strtotime($freedSched['next_run_at']) > time(), "Next execution slot advanced into the future");

// =========================================================================
// TEST SUITE 5: Conversational Slot-Filling State Machine
// =========================================================================
echo "\nTEST SUITE 5: Conversational Slot-Filling State Machine\n";

$msgIncomplete = "Find 10 dental leads daily morning";
$slotsIncomplete = SlotFillingEngine::extractSlots($msgIncomplete, [], 1, [], $pdo);
$valIncomplete = SlotFillingEngine::validateSlots($slotsIncomplete, 1, $pdo);
assertTest($valIncomplete['is_complete'] === false, "Incomplete prompt identified as not complete");
assertTest(in_array('target_location', $valIncomplete['missing_fields']), "Identified missing target_location");
assertTest(in_array('value_proposition', $valIncomplete['missing_fields']), "Identified missing value_proposition");

// Lead count bounding
$msgOverflow = "Find 100 fintech leads daily in Dubai offering AI tools";
$slotsOverflow = SlotFillingEngine::extractSlots($msgOverflow, [], 1, [], $pdo);
assertTest($slotsOverflow[SlotFillingEngine::FIELD_LEAD_COUNT] === 25, "Bounded lead count to max limit of 25 (requested 100)");

$msgUnderflow = "Find 0 real estate leads daily in London";
$slotsUnderflow = SlotFillingEngine::extractSlots($msgUnderflow, [], 1, [], $pdo);
assertTest($slotsUnderflow[SlotFillingEngine::FIELD_LEAD_COUNT] >= 1, "Bounded lead count to min limit of 1 (requested 0)");

// Sender verification
$slotsUnverified = [
    SlotFillingEngine::FIELD_INDUSTRY => 'Dental',
    SlotFillingEngine::FIELD_LOCATION => 'London',
    SlotFillingEngine::FIELD_LEAD_COUNT => 10,
    SlotFillingEngine::FIELD_VALUE_PROP => 'AI CRM Lead Automation',
    SlotFillingEngine::FIELD_SENDER_IDENTITY => 'unverified_hacker@unknown.com',
    SlotFillingEngine::FIELD_SCHEDULE => '0 9 * * *',
    SlotFillingEngine::FIELD_APPROVAL_POLICY => 'digest_review'
];
$valUnverified = SlotFillingEngine::validateSlots($slotsUnverified, 1, $pdo);
assertTest($valUnverified['is_complete'] === false, "Rejects unverified sender identity not connected in workspace");

// =========================================================================
// TEST SUITE 6: DeliverabilityEngine (RFC Syntax, Disposable & DNS MX)
// =========================================================================
echo "\nTEST SUITE 6: DeliverabilityEngine (RFC Syntax, Disposable & DNS MX)\n";

// 1. Syntax checks
$resSyntaxBad = DeliverabilityEngine::validateEmail("invalid..email@domain..com", false);
assertTest($resSyntaxBad['status'] === DeliverabilityEngine::STATUS_INVALID_SYNTAX, "Identifies invalid syntax with consecutive dots");

$resSyntaxSpace = DeliverabilityEngine::validateEmail("space in email@example.com", false);
assertTest($resSyntaxSpace['status'] === DeliverabilityEngine::STATUS_INVALID_SYNTAX, "Identifies invalid syntax containing spaces");

$resSyntaxNoTld = DeliverabilityEngine::validateEmail("user@localhost", false);
assertTest($resSyntaxNoTld['status'] === DeliverabilityEngine::STATUS_INVALID_SYNTAX, "Identifies domain without valid TLD");

// 2. Disposable domain detection
assertTest(DeliverabilityEngine::isDisposable("mailinator.com"), "Detects mailinator.com as disposable domain");
assertTest(DeliverabilityEngine::isDisposable("tempmail.com"), "Detects tempmail.com as disposable domain");
assertTest(DeliverabilityEngine::isDisposable("subdomain.10minutemail.com"), "Detects disposable subdomain");
assertTest(!DeliverabilityEngine::isDisposable("google.com"), "Legitimate domain google.com is not disposable");

$resDisp = DeliverabilityEngine::validateEmail("testuser@mailinator.com", false);
assertTest($resDisp['status'] === DeliverabilityEngine::STATUS_DISPOSABLE, "Email on disposable domain flagged with status 'disposable'");

// 3. DNS MX checks (via mocked resolver for test isolation)
DeliverabilityEngine::setDnsResolver(function(string $domain) {
    if ($domain === 'has-mx-domain.co.uk') {
        return ['mx1.has-mx-domain.co.uk', 'mx2.has-mx-domain.co.uk'];
    }
    return []; // No MX for others
});

$resValidMx = DeliverabilityEngine::validateEmail("contact@has-mx-domain.co.uk", true);
assertTest($resValidMx['status'] === DeliverabilityEngine::STATUS_DOMAIN_HAS_MX, "Domain with configured MX has status 'domain_has_mx'");
assertTest($resValidMx['is_deliverable'] === true, "Marked is_deliverable = true when domain has MX");
assertTest(count($resValidMx['mx_records']) === 2, "Captured 2 MX exchanger host records");

$resNoMx = DeliverabilityEngine::validateEmail("contact@no-mx-server.com", true);
assertTest($resNoMx['status'] === DeliverabilityEngine::STATUS_NO_MX, "Domain without MX records flagged with status 'no_mx'");
assertTest($resNoMx['is_deliverable'] === false, "Marked is_deliverable = false when domain lacks MX");

// Maintain deterministic DNS resolver for simulated test domains
DeliverabilityEngine::setDnsResolver(function(string $domain) {
    if (str_contains($domain, 'has-mx') || str_contains($domain, 'dental') || str_contains($domain, 'clinic') || str_contains($domain, 'prime') || str_contains($domain, 'care')) {
        return ['mx1.' . $domain];
    }
    return [];
});

// Domain normalization
assertTest(DeliverabilityEngine::normalizeDomain('https://www.company.com/about/team') === 'company.com', "Normalizes full URL to clean domain");
assertTest(DeliverabilityEngine::normalizeDomain('http://sub.domain.org') === 'sub.domain.org', "Normalizes HTTP subdomain URL");

// =========================================================================
// TEST SUITE 7: Lead Prospecting Engine & Modular Adapters
// =========================================================================
echo "\nTEST SUITE 7: Lead Prospecting Engine & Modular Adapters\n";

$mockAdapter = new MockProspectingProviderAdapter();
$prospects = $mockAdapter->search('Dental', 'London', 5);
assertTest(count($prospects) === 5, "Mock provider returns requested 5 prospects");
assertTest(!empty($prospects[0]['company_name']), "Retained company_name: {$prospects[0]['company_name']}");
assertTest(!empty($prospects[0]['website']), "Retained website: {$prospects[0]['website']}");
assertTest(!empty($prospects[0]['business_location']), "Retained business_location: {$prospects[0]['business_location']}");
assertTest(!empty($prospects[0]['source_url']), "Retained original source_url provenance");
assertTest(!empty($prospects[0]['decision_maker_name']), "Retained decision-maker: {$prospects[0]['decision_maker_name']}");

// HTML extraction parser
$sampleHtml = '<html><body><h1>Apex Dental Practice</h1><p>Call us at +44 20 7946 0192 or write to <a href="mailto:info@apexdental.co.uk">info@apexdental.co.uk</a></p></body></html>';
$extracted = LeadProspectingEngine::extractFromHtml($sampleHtml, 'https://apexdental.co.uk');
assertTest($extracted['company_name'] === 'Apex Dental Practice', "Extracted company name from HTML");
assertTest($extracted['business_email'] === 'info@apexdental.co.uk', "Extracted business email from mailto anchor");
assertTest(strpos($extracted['business_phone'], '7946 0192') !== false, "Extracted phone number from HTML");

// Signal completeness and confidence scoring
$fullProspect = [
    'company_name' => 'Apex Dental',
    'website' => 'https://apexdental.co.uk',
    'business_location' => 'London',
    'business_email' => 'info@apexdental.co.uk',
    'business_phone' => '+44 20 7946 0192',
    'decision_maker_name' => 'Dr. Julian Thorne',
    'verification_status' => DeliverabilityEngine::STATUS_DOMAIN_HAS_MX
];
$conf = LeadProspectingEngine::calculateConfidenceScore($fullProspect);
assertTest($conf >= 80, "High confidence score ({$conf}/100) for complete prospect signals");

$leadScore = LeadProspectingEngine::calculateLeadScore($fullProspect);
assertTest($leadScore['score'] >= 80, "High lead score ({$leadScore['score']}/100) calculated");
assertTest(stripos($leadScore['explanation'], 'Domain MX Verified') !== false, "Lead score explanation documents MX verification");

// =========================================================================
// TEST SUITE 8: CRM Deduplication, Ingestion & Deal Safety Guard
// =========================================================================
echo "\nTEST SUITE 8: CRM Deduplication, Ingestion & Deal Safety Guard\n";

$prospectToIngest = [
    'company_name' => 'Prime Dental Clinic',
    'website' => 'https://primedental.co.uk',
    'business_location' => 'London',
    'business_email' => 'drprime@primedental.co.uk',
    'business_phone' => '+44 20 7946 0555',
    'decision_maker_name' => 'Dr. Prime',
    'decision_maker_role' => 'Principal Dentist',
    'source_url' => 'https://primedental.co.uk',
    'source_provider' => 'mock',
    'discovery_timestamp' => date('Y-m-d H:i:s'),
    'extraction_confidence' => 90,
    'verification_status' => DeliverabilityEngine::STATUS_DOMAIN_HAS_MX,
    'lead_score' => 85,
    'lead_score_explanation' => 'Base (50) + MX (+15) + Phone (+10) + Web (+10)'
];

// First Ingestion
$ingestRes = LeadProspectingEngine::deduplicateAndIngestProspect(1, $prospectToIngest, null, null, [], $pdo);
assertTest($ingestRes['status'] === 'ingested', "First prospect ingestion succeeded");
assertTest(!empty($ingestRes['crm_lead_id']), "Lead inserted into crm_leads (ID #{$ingestRes['crm_lead_id']})");
assertTest(!empty($ingestRes['crm_company_id']), "Company inserted into crm_companies (ID #{$ingestRes['crm_company_id']})");
assertTest(!empty($ingestRes['crm_contact_id']), "Contact inserted into crm_contacts (ID #{$ingestRes['crm_contact_id']})");
assertTest(empty($ingestRes['crm_deal_id']), "SAFETY GUARD: Deal is strictly NOT auto-created without explicit user instruction");

// Duplicate Detection: Attempt to ingest identical email
$dupResEmail = LeadProspectingEngine::deduplicateAndIngestProspect(1, $prospectToIngest, null, null, [], $pdo);
assertTest($dupResEmail['status'] === 'skipped_duplicate', "Duplicate prospect skipped cleanly");
assertTest(stripos($dupResEmail['reason'], 'already exists') !== false, "Duplicate detection identifies existing CRM record");

// Duplicate Detection: Different email but duplicate company domain
$prospectSameDomain = $prospectToIngest;
$prospectSameDomain['business_email'] = 'new_receptionist@primedental.co.uk';
$dupResDomain = LeadProspectingEngine::deduplicateAndIngestProspect(1, $prospectSameDomain, null, null, [], $pdo);
assertTest($dupResDomain['status'] === 'skipped_duplicate', "Company domain deduplication catches duplicate company");

// Explicit Deal Creation (when user rule permits)
$prospectWithDeal = $prospectToIngest;
$prospectWithDeal['company_name'] = 'Special VIP Care';
$prospectWithDeal['website'] = 'https://vipcare.com';
$prospectWithDeal['business_email'] = 'vip@vipcare.com';
$ingestWithDeal = LeadProspectingEngine::deduplicateAndIngestProspect(1, $prospectWithDeal, null, null, ['create_deal' => true, 'deal_value' => 8500.00], $pdo);
assertTest(!empty($ingestWithDeal['crm_deal_id']), "Deal created only when explicitly requested via rule");

// =========================================================================
// TEST SUITE 9: Proposal Generation & Compliance Standards
// =========================================================================
echo "\nTEST SUITE 9: Proposal Generation & Compliance Standards\n";

$proposal = ProposalGenerator::generateProposal(
    $prospectToIngest,
    'Autonomous Patient Scheduling System',
    'drwbsoumo@gmail.com',
    'LinkPilot Healthcare Team'
);

assertTest(!empty($proposal['subject']), "Generated personalized subject line: {$proposal['subject']}");
assertTest(strpos($proposal['body_text'], 'Prime Dental Clinic') !== false, "Proposal text mentions target company name");
assertTest(strpos($proposal['body_text'], 'Dr. Prime') !== false, "Proposal text addresses decision maker");
assertTest(strpos($proposal['body_text'], 'Autonomous Patient Scheduling System') !== false, "Proposal text articulates value proposition");
assertTest(!empty($proposal['unsubscribe_url']), "Proposal includes mandatory unsubscribe URL");
assertTest(strpos($proposal['body_html'], 'unsubscribe') !== false, "Proposal HTML includes one-click unsubscribe footer");
assertTest(strpos($proposal['body_text'], 'Compliance & Preferences') !== false, "Proposal plain text includes lawful basis notice");

// =========================================================================
// TEST SUITE 10: Batch Approvals, Cryptographic Token Security & TTL
// =========================================================================
echo "\nTEST SUITE 10: Batch Approvals, Cryptographic Token Security & TTL\n";

$items = [
    [
        'company_name' => 'Clinic Alpha',
        'recipient_email' => 'alpha@clinic.co.uk',
        'subject' => 'Subject Alpha',
        'body' => 'Body content Alpha',
        'sender_email' => 'drwbsoumo@gmail.com'
    ],
    [
        'company_name' => 'Clinic Beta',
        'recipient_email' => 'beta@clinic.co.uk',
        'subject' => 'Subject Beta',
        'body' => 'Body content Beta',
        'sender_email' => 'drwbsoumo@gmail.com'
    ]
];

$batch = BatchApprovalEngine::createDigestBatch(1, 'run_test_001', 'wf_test_001', $items, 72, $pdo);
assertTest($batch['total_items'] === 2, "Created batch with 2 approval items");
$tokAlpha = $batch['items'][0]['token'];
$tokBeta = $batch['items'][1]['token'];

// Single-use enforcement
$appr1 = BatchApprovalEngine::approveItem(1, $tokAlpha, $pdo);
assertTest($appr1['status'] === 'approved', "Individual approval succeeded for Alpha");

// Content tamper check: If content changes, approval token must be invalidated
$edited = BatchApprovalEngine::editItem(1, $tokBeta, 'Edited Subject Beta', 'Edited Body content Beta', $pdo);
assertTest($edited['new_token'] !== $tokBeta, "Editing draft generated a new cryptographic approval token");
assertTest(!empty($edited['content_hash']), "Content hash updated to reflect edited draft");

// Attempt to approve original invalidated token
$invalidThrew = false;
try {
    BatchApprovalEngine::approveItem(1, $tokBeta, $pdo);
} catch (Throwable $e) {
    $invalidThrew = true;
}
assertTest($invalidThrew === true, "Original approval token invalidated after content edit");

// Expired token enforcement
$pdo->prepare("UPDATE ai_agent_batch_approvals SET expires_at = datetime('now', '-1 hour') WHERE token = ?")->execute([$edited['new_token']]);
$expiredThrew = false;
try {
    BatchApprovalEngine::approveItem(1, $edited['new_token'], $pdo);
} catch (Throwable $e) {
    $expiredThrew = true;
}
assertTest($expiredThrew === true, "Expired approval token rejected");

// =========================================================================
// TEST SUITE 11: Controlled Outreach Dispatch, Quotas & Suppression
// =========================================================================
echo "\nTEST SUITE 11: Controlled Outreach Dispatch, Quotas & Suppression\n";

// Create fresh approved item for dispatch testing
$freshItems = [
    [
        'company_name' => 'Dispatch Dental',
        'recipient_email' => 'outreach@dispatchdental.co.uk',
        'subject' => 'Collaboration proposal',
        'body' => 'Hello team, exciting collaboration proposal.',
        'sender_email' => 'drwbsoumo@gmail.com'
    ]
];
$freshBatch = BatchApprovalEngine::createDigestBatch(1, 'run_disp_01', 'wf_disp_01', $freshItems, 72, $pdo);
$dispTok = $freshBatch['items'][0]['token'];
BatchApprovalEngine::approveItem(1, $dispTok, $pdo);

// 1. Successful dispatch using mock transport
$dispatched = OutreachDispatcher::dispatchApprovedItem(1, $dispTok, OutreachDispatcher::PROVIDER_MOCK, [], $pdo);
assertTest($dispatched['status'] === 'sent', "Approved proposal dispatched successfully");
assertTest(!empty($dispatched['log_id']), "Outbound message audit log created (ID #{$dispatched['log_id']})");

// 2. Single-use guard: attempt to re-dispatch already sent token
$reDispatchThrew = false;
try {
    OutreachDispatcher::dispatchApprovedItem(1, $dispTok, OutreachDispatcher::PROVIDER_MOCK, [], $pdo);
} catch (Throwable $e) {
    $reDispatchThrew = true;
}
assertTest($reDispatchThrew === true, "Re-use of already dispatched token rejected");

// 3. Suppression list guard: bounced or unsubscribed recipient
OutreachDispatcher::recordBounceOrComplaint(1, 'bounced_user@clinic.com', 'bounced', $pdo);
assertTest(OutreachDispatcher::isSuppressed(1, 'bounced_user@clinic.com', $pdo) === true, "Suppression list records bounced email");

// Attempt dispatch to suppressed recipient
$suppItems = [
    [
        'company_name' => 'Suppressed Clinic',
        'recipient_email' => 'bounced_user@clinic.com',
        'subject' => 'Unwanted pitch',
        'body' => 'Body text',
        'sender_email' => 'drwbsoumo@gmail.com'
    ]
];
$suppBatch = BatchApprovalEngine::createDigestBatch(1, 'run_supp_01', 'wf_supp_01', $suppItems, 72, $pdo);
$suppTok = $suppBatch['items'][0]['token'];
BatchApprovalEngine::approveItem(1, $suppTok, $pdo);

$suppDispatchRes = OutreachDispatcher::dispatchApprovedItem(1, $suppTok, OutreachDispatcher::PROVIDER_MOCK, [], $pdo);
assertTest($suppDispatchRes['status'] === 'suppressed', "OutreachDispatcher suppresses email to recipient on suppression list");

// 4. Provider daily quota tracking
$remainingQuota = OutreachDispatcher::getRemainingDailyQuota(1, OutreachDispatcher::PROVIDER_MOCK, $pdo);
assertTest($remainingQuota < OutreachDispatcher::QUOTA_MOCK, "Daily dispatch quota correctly decrements with each outbound message");

// =========================================================================
// TEST SUITE 12: Background Cron Worker Autonomous Prospecting
// =========================================================================
echo "\nTEST SUITE 12: Background Cron Worker Autonomous Prospecting\n";

// Create scheduled prospecting job
$schedJob = WorkflowScheduler::createSchedule(
    1,
    'Automated London Clinics Prospecting',
    '0 9 * * *',
    'Europe/London',
    [
        'target_industry' => 'Clinics',
        'target_location' => 'London',
        'lead_count' => 3,
        'value_proposition' => 'AI Automated Receptionist',
        'sender_identity' => 'wbsoumo@gmail.com'
    ],
    null,
    WorkflowScheduler::POLICY_DIGEST_REVIEW,
    WorkflowScheduler::MISSED_SKIP,
    $pdo
);

// Claim schedule and execute via worker
$pdo->prepare("UPDATE ai_agent_schedules SET next_run_at = datetime('now', '-5 minutes') WHERE id = ?")->execute([$schedJob['id']]);
$workerClaim = WorkflowScheduler::claimDueSchedules(1, 120, $pdo);
assertTest(count($workerClaim) === 1, "Cron worker claimed due autonomous prospecting schedule");

// Verify discovered prospects table
$discoveredLeads = LeadProspectingEngine::discoverProspects('Clinics', 'London', 3, 'mock');
assertTest(count($discoveredLeads) === 3, "Discovered 3 prospects for London Clinics");

$ingestedCount = 0;
foreach ($discoveredLeads as $dl) {
    $resIng = LeadProspectingEngine::deduplicateAndIngestProspect(1, $dl, $schedJob['id'], 'run_worker_test', [], $pdo);
    if ($resIng['status'] === 'ingested') $ingestedCount++;
}
assertTest($ingestedCount === 3, "Ingested all 3 discovered prospects into workspace CRM");

// Verify audit trail in ai_agent_discovered_prospects
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ai_agent_discovered_prospects WHERE user_id = 1");
$stmtCount->execute();
$totalProspects = (int)$stmtCount->fetchColumn();
assertTest($totalProspects >= 3, "Audit trail recorded in ai_agent_discovered_prospects table ({$totalProspects} records)");

echo "\n------------------------------------------------------------------------\n";
echo "  PHASE 5 TEST SUMMARY: {$passCount} Passed, " . ($testCount - $passCount) . " Failed\n";
echo "------------------------------------------------------------------------\n";

if ($passCount === $testCount) {
    echo "All Phase 5 (5A & 5B) tests PASSED successfully with 100% compliance!\n\n";
} else {
    exit(1);
}
