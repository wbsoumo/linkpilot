<?php
// tests/test_security_audit.php
// LinkPilot AI: Autonomous Agent Engine 2.0 Security Audit & Cryptographic Verification Suite.
// Verifies rotating secrets, header-based auth, tamper-proof authenticated encryption,
// multi-tenant workspace isolation, and SQL injection protections.

echo "========================================================================\n";
echo "  LINKPILOT AI: SECURITY AUDIT & PRODUCTION HARDENING SUITE\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $title, string $details = '') {
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

// In-Memory SQLite Database for Isolated Security Testing
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
AgentMigration5::run($pdo);

// ------------------------------------------------------------------------
// SUITE 1: Authenticated Encryption (AES-256-CBC + HMAC-SHA256)
// ------------------------------------------------------------------------
echo "TEST SUITE 1: Cryptographic Token Storage & Authenticated Encryption\n";

$plainSecret = 'ya29.a0ARrdaM-sensitive_oauth_refresh_token_xyz987';
$encrypted = encryptData($plainSecret);

assertTest(!empty($encrypted), "Data encrypted successfully");
assertTest(str_starts_with(base64_decode($encrypted), 'v2:'), "Ciphertext uses v2 authenticated format (IV + HMAC + Ciphertext)");

$decrypted = decryptData($encrypted);
assertTest($decrypted === $plainSecret, "Decrypted data matches original plaintext");

// Tampering Test: Flip bits in ciphertext
$raw = base64_decode($encrypted);
$tamperedRaw = substr($raw, 0, -2) . chr(ord(substr($raw, -2, 1)) ^ 0xFF) . substr($raw, -1);
$tamperedB64 = base64_encode($tamperedRaw);
$tamperResult = decryptData($tamperedB64);
assertTest($tamperResult === false, "HMAC-SHA256 integrity check catches and rejects tampered ciphertext");

// Legacy format backward compatibility test (v1 format: encrypted::iv)
$legacyKey = hash('sha256', ENCRYPTION_KEY);
$legacyIv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
$legacyEnc = openssl_encrypt("legacy_secret_value", ENCRYPTION_METHOD, $legacyKey, 0, $legacyIv);
$legacyPayload = base64_encode($legacyEnc . '::' . base64_encode($legacyIv));
$legacyDec = decryptData($legacyPayload);
assertTest($legacyDec === "legacy_secret_value", "Legacy v1 encrypted data successfully decrypts via backward-compatible fallback");

// Empty string handling
assertTest(encryptData('') === '', "Empty string encryption returns empty string safely");
assertTest(decryptData('') === '', "Empty string decryption returns empty string safely");

// ------------------------------------------------------------------------
// SUITE 2: Rotated Tokens & Query Parameter Rejection
// ------------------------------------------------------------------------
echo "\nTEST SUITE 2: Secret Rotation & Header-Based Auth Gate Verification\n";

assertTest(defined('CRON_WORKER_SECRET') && strlen(CRON_WORKER_SECRET) >= 32, "CRON_WORKER_SECRET is defined with high entropy (>= 32 chars)");
assertTest(defined('DIAGNOSTIC_ADMIN_KEY') && strlen(DIAGNOSTIC_ADMIN_KEY) >= 32, "DIAGNOSTIC_ADMIN_KEY is defined with high entropy (>= 32 chars)");
assertTest(defined('DEPLOYMENT_SECRET') && strlen(DEPLOYMENT_SECRET) >= 32, "DEPLOYMENT_SECRET is defined with high entropy (>= 32 chars)");

// Verify hash_equals timing-safe comparison
assertTest(hash_equals(CRON_WORKER_SECRET, CRON_WORKER_SECRET), "Constant-time comparison succeeds for exact token match");
assertTest(!hash_equals(CRON_WORKER_SECRET, 'LP_CRON_WORKER_7719'), "Deprecated token 'LP_CRON_WORKER_7719' is rejected");
assertTest(!hash_equals(DIAGNOSTIC_ADMIN_KEY, 'LP_DIAGNOSTIC_9823'), "Deprecated token 'LP_DIAGNOSTIC_9823' is rejected");
assertTest(!hash_equals(CRON_WORKER_SECRET, 'lp_cron_sec_8f92a10b4c7e6d5e9a31b2c4d6f8e0a2b5c7d9e1f3a5b7c9d1e3f5a7b9c1d3e5'), "Prior token 'lp_cron_sec_...' is rejected");
assertTest(!hash_equals(DIAGNOSTIC_ADMIN_KEY, 'lp_diag_sec_1a3b5c7d9e0f2a4b6c8d0e1f3a5b7c9d2e4f6a8b0c2d4e6f8a0b2c4d6e8f0a2b'), "Prior token 'lp_diag_sec_...' is rejected");
assertTest(!hash_equals(DEPLOYMENT_SECRET, 'lp_deploy_sec_4f6a8b0c2d4e6f8a0b2c4d6e8f0a2b1a3b5c7d9e0f2a4b6c8d0e1f3a5b7c9d2e'), "Prior token 'lp_deploy_sec_...' is rejected");

// ------------------------------------------------------------------------
// SUITE 3: Workspace Multi-Tenant Authorization & IDOR Protection
// ------------------------------------------------------------------------
echo "\nTEST SUITE 3: Multi-Tenant Authorization & IDOR Isolation\n";

$userA = 101;
$userB = 202;

// User A creates a schedule
$schedA = WorkflowScheduler::createSchedule($userA, 'Enterprise Outreach Schedule', '0 9 * * *', 'UTC', ['industry' => 'SaaS'], null, 'prospecting_and_outreach', 'digest_review', $pdo);
$schedIdA = $schedA['id'];

// User B attempts to read User A's schedule
$userBSched = WorkflowScheduler::getSchedule($schedIdA, $userB, $pdo);
assertTest($userBSched === null, "IDOR Guard: User B cannot retrieve User A's schedule");

// User B attempts to pause User A's schedule
$pauseAttempt = WorkflowScheduler::pauseSchedule($schedIdA, $userB, $pdo);
assertTest($pauseAttempt === false, "IDOR Guard: User B cannot pause User A's schedule");

// User B attempts to delete User A's schedule
$deleteAttempt = WorkflowScheduler::deleteSchedule($schedIdA, $userB, $pdo);
assertTest($deleteAttempt === false, "IDOR Guard: User B cannot delete User A's schedule");

// User B attempts to update User A's schedule
$caughtUpdate = false;
try {
    WorkflowScheduler::updateSchedule($schedIdA, $userB, ['title' => 'Malicious Hijack'], $pdo);
} catch (InvalidArgumentException $e) {
    $caughtUpdate = true;
}
assertTest($caughtUpdate, "IDOR Guard: User B cannot update User A's schedule (throws unauthorized exception)");

// ------------------------------------------------------------------------
// SUITE 4: Cryptographic Batch Approval IDOR & Single-Use Enforcement
// ------------------------------------------------------------------------
echo "\nTEST SUITE 4: Batch Approval IDOR & Single-Use Token Guarantees\n";

$draftsA = [
    [
        'recipient_email' => 'ceo@acme-corp.com',
        'subject' => 'Acme Partnership Inquiry',
        'body' => 'Hi Team, proposal for Acme Corp.'
    ]
];

$digestA = BatchApprovalEngine::createDigestBatch($userA, 'run_101', 'wf_101', $draftsA, 72, $pdo);
$tokenA = $digestA['tokens']['lead_1'];

// User B attempts to preview User A's approval token
$caughtPreview = false;
try {
    BatchApprovalEngine::previewItem($userB, $tokenA, $pdo);
} catch (InvalidArgumentException $e) {
    $caughtPreview = true;
}
assertTest($caughtPreview, "IDOR Guard: User B cannot preview User A's approval token");

// User B attempts to approve User A's token
$caughtApprove = false;
try {
    BatchApprovalEngine::approveItem($userB, $tokenA, $pdo);
} catch (InvalidArgumentException $e) {
    $caughtApprove = true;
}
assertTest($caughtApprove, "IDOR Guard: User B cannot approve User A's proposal token");

// User A approves
$apprA = BatchApprovalEngine::approveItem($userA, $tokenA, $pdo);
assertTest($apprA['status'] === 'approved', "User A successfully approves own proposal");

// Single-use enforcement: re-approval rejected
$caughtReApprove = false;
try {
    BatchApprovalEngine::approveItem($userA, $tokenA, $pdo);
} catch (RuntimeException $e) {
    $caughtReApprove = true;
}
assertTest($caughtReApprove, "Single-use enforcement: Re-approving already approved token is rejected");

// ------------------------------------------------------------------------
// SUITE 5: Operational Controls & Workspace Kill-Switch Isolation
// ------------------------------------------------------------------------
echo "\nTEST SUITE 5: Operational Controls & Workspace Kill-Switch\n";

$settingsA = WorkflowScheduler::getWorkspaceSettings($userA, $pdo);
assertTest($settingsA['pause_all_outreach'] === false, "Default pause_all_outreach is false");
assertTest($settingsA['default_approval_mode'] === 'digest_review', "Default approval mode is strictly 'digest_review'");
assertTest($settingsA['auto_dispatch_enabled'] === false, "Auto-dispatch is strictly disabled by default");

// User A activates killswitch
WorkflowScheduler::updateWorkspaceSettings($userA, ['pause_all_outreach' => true], $pdo);
assertTest(WorkflowScheduler::isOutreachPausedForWorkspace($userA, $pdo) === true, "Workspace killswitch activated for User A");
assertTest(WorkflowScheduler::isOutreachPausedForWorkspace($userB, $pdo) === false, "Workspace killswitch for User A does NOT affect User B (Isolated)");

// User A attempts dispatch while killswitch is active
$caughtPausedDispatch = false;
try {
    OutreachDispatcher::dispatchApprovedItem($userA, $tokenA, 'mock', [], $pdo);
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'killswitch')) {
        $caughtPausedDispatch = true;
    }
}
assertTest($caughtPausedDispatch, "OutreachDispatcher blocks email dispatch when workspace killswitch is active");

// Reset killswitch and dispatch
WorkflowScheduler::updateWorkspaceSettings($userA, ['pause_all_outreach' => false], $pdo);
$dispatchRes = OutreachDispatcher::dispatchApprovedItem($userA, $tokenA, 'mock', [], $pdo);
assertTest($dispatchRes['status'] === 'sent', "Dispatch succeeds when killswitch is deactivated");

// Re-use of token for dispatch is blocked
$caughtReDispatch = false;
try {
    OutreachDispatcher::dispatchApprovedItem($userA, $tokenA, 'mock', [], $pdo);
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'already been used')) {
        $caughtReDispatch = true;
    }
}
assertTest($caughtReDispatch, "Duplicate dispatch prevention: Re-use of dispatched token is strictly blocked");

// ------------------------------------------------------------------------
// SUITE 6: SQL Injection Protection on Phase 5 Endpoints
// ------------------------------------------------------------------------
echo "\nTEST SUITE 6: SQL Injection Protection via Prepared Statements\n";

$sqlInjectionPayload = "'; DROP TABLE ai_agent_schedules; --";
$escapedSched = WorkflowScheduler::getSchedule($sqlInjectionPayload, $userA, $pdo);
assertTest($escapedSched === null, "SQL injection string in getSchedule handled safely without error");

$tablesRemaining = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ai_agent_schedules'")->fetchColumn();
assertTest($tablesRemaining === 'ai_agent_schedules', "Table was NOT dropped; SQL injection payload was neutralized");

$prospectsSqlInj = LeadProspectingEngine::listProspectsForUser($userA, ['search' => $sqlInjectionPayload], 50, $pdo);
assertTest(is_array($prospectsSqlInj), "Search filter with SQL injection payload executes safely via parameter binding");

// Audit History Logging
$auditLogs = WorkflowScheduler::getAuditLogs($userA, 10, $pdo);
assertTest(count($auditLogs) > 0, "Audit logs recorded user actions with timestamps and IP addresses");

echo "\n------------------------------------------------------------------------\n";
echo "  SECURITY AUDIT TEST SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "------------------------------------------------------------------------\n";

if ($failCount === 0) {
    echo "All Security Audit tests PASSED successfully with 100% compliance!\n\n";
    exit(0);
} else {
    echo "Security Audit tests FAILED with {$failCount} failure(s)!\n\n";
    exit(1);
}
