<?php
// backend/cron_agent_worker.php
// Autonomous Agent Engine 2.0: Background Cron Worker.
// Atomically executes due schedules headlessly using Phase 4 ExecutionEngine,
// enforces idempotency, manages leases, and generates Morning Review Digests.

if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if ($token !== 'LP_CRON_WORKER_7719') {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/agent/AgentMigration5.php';
require_once __DIR__ . '/agent/WorkflowScheduler.php';
require_once __DIR__ . '/agent/SlotFillingEngine.php';
require_once __DIR__ . '/agent/BatchApprovalEngine.php';
require_once __DIR__ . '/agent/DeliverabilityEngine.php';
require_once __DIR__ . '/agent/LeadProspectingEngine.php';
require_once __DIR__ . '/agent/ProposalGenerator.php';
require_once __DIR__ . '/agent/OutreachDispatcher.php';
require_once __DIR__ . '/agent/TaskPlanDAG.php';
require_once __DIR__ . '/agent/ExecutionEngine.php';
require_once __DIR__ . '/agent/WorkflowEngine.php';
require_once __DIR__ . '/agent_orchestrator.php';

$db = Database::getConnection();
AgentMigration5::run($db);

$workerStartTime = microtime(true);
$claimedSchedules = WorkflowScheduler::claimDueSchedules(5, 300, $db);
$processed = 0;
$results = [];

foreach ($claimedSchedules as $sched) {
    $schedId = $sched['id'];
    $userId = (int)$sched['user_id'];
    $leaseToken = $sched['lease_token'];
    $execId = $sched['execution_id'];
    $params = $sched['parameters'];
    $policy = $sched['approval_policy'] ?? WorkflowScheduler::POLICY_DIGEST_REVIEW;
    $scheduledFor = $sched['next_run_at'];

    $idempotencyKey = hash('sha256', $schedId . '|' . $scheduledFor);
    $now = date('Y-m-d H:i:s');

    // 1. Record Schedule Run
    $stmtRun = $db->prepare("INSERT INTO ai_agent_schedule_runs (
        id, schedule_id, user_id, idempotency_key, scheduled_for, started_at, status
    ) VALUES (?, ?, ?, ?, ?, ?, 'running')");

    try {
        $stmtRun->execute([$execId, $schedId, $userId, $idempotencyKey, $scheduledFor, $now]);
    } catch (Throwable $eRun) {
        // If already exists due to race, release and skip
        WorkflowScheduler::completeScheduleRun($schedId, $leaseToken, 'Duplicate idempotency execution prevented', $db);
        continue;
    }

    $runError = null;
    $summary = [];

    try {
        // 2. Build or load DAG for scheduled job
        $dagTemplate = $sched['dag_template'];
        if ($dagTemplate && isset($dagTemplate['nodes'])) {
            $dag = TaskPlanDAG::fromArray($dagTemplate);
        } else {
            // Standard prospecting and outreach DAG template
            $dag = new TaskPlanDAG("Lead Prospecting & Outreach ({$sched['title']})");
            $dag->addNode('discover_leads', 'search_leads', 'Discover targeted leads', [], [
                'industry' => $params['target_industry'] ?? 'General',
                'location' => $params['target_location'] ?? 'Global',
                'limit' => $params['lead_count'] ?? 10
            ], false, false);

            $dag->addNode('generate_proposals', 'draft_proposals', 'Generate personalized proposals', ['discover_leads'], [
                'value_proposition' => $params['value_proposition'] ?? 'Business proposal',
                'sender_identity' => $params['sender_identity'] ?? ''
            ], false, false);
        }

        // 3. Find or create an established conversation for digest posting
        $stmtConv = $db->prepare("SELECT id FROM ai_agent_conversations WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmtConv->execute([$userId]);
        $convId = $stmtConv->fetchColumn();
        if (!$convId) {
            $newConv = AgentOrchestrator::createConversation($userId, 'Autonomous Outreach Agent', $db);
            $convId = $newConv['id'];
        }

        // 4. Create workflow record via Phase 3 WorkflowEngine
        $wf = WorkflowEngine::createWorkflow($userId, $convId, 'prospecting_plugin', 'autonomous_prospecting', $sched['title'], $params, null, $db);
        $wfId = $wf['id'];

        $db->prepare("UPDATE ai_agent_schedule_runs SET workflow_id = ? WHERE id = ?")->execute([$wfId, $execId]);

        // 5. Execute DAG headlessly using Phase 4 ExecutionEngine
        $dagResult = ExecutionEngine::executeDAG($dag, $userId, $convId, $wfId, null, $db, ['is_headless' => true]);

        // 6. Execute Lead Discovery, CRM Ingestion & Proposal Generation
        $industry = $params['target_industry'] ?? 'General';
        $location = $params['target_location'] ?? 'Global';
        $leadCount = (int)($params['lead_count'] ?? 10);
        $valueProp = $params['value_proposition'] ?? 'Business collaboration';
        $senderId = $params['sender_identity'] ?? 'outreach@linkpilot.work';

        // Discover and enrich prospects via modular provider adapter
        $discovered = LeadProspectingEngine::discoverProspects($industry, $location, $leadCount, 'mock');
        $ingestedProposals = [];

        foreach ($discovered as $p) {
            // Deduplicate and ingest prospect into CRM (without auto-creating deals)
            $ingestRes = LeadProspectingEngine::deduplicateAndIngestProspect($userId, $p, $schedId, $execId, [], $db);
            if ($ingestRes['status'] === 'ingested') {
                // Draft personalized proposal email
                $proposalDraft = ProposalGenerator::generateProposal(
                    $p,
                    $valueProp,
                    $senderId,
                    'LinkPilot AI Outreach'
                );
                $proposalDraft['body'] = $proposalDraft['body_text'];
                $ingestedProposals[] = $proposalDraft;
            }
        }

        // If all were duplicates or limit empty, provide baseline drafts
        if (empty($ingestedProposals) && !empty($discovered)) {
            foreach (array_slice($discovered, 0, min(3, count($discovered))) as $p) {
                $proposalDraft = ProposalGenerator::generateProposal($p, $valueProp, $senderId, 'LinkPilot AI Outreach');
                $proposalDraft['body'] = $proposalDraft['body_text'];
                $ingestedProposals[] = $proposalDraft;
            }
        }

        if ($policy === WorkflowScheduler::POLICY_DIGEST_REVIEW) {
            // Create cryptographic batch approvals
            $digestBatch = BatchApprovalEngine::createDigestBatch($userId, $execId, $wfId, $ingestedProposals, 72, $db);

            // Post Morning Review Digest message in user chat
            $digestText = "### 🌅 Morning Prospecting Digest: **{$sched['title']}**\n\n" .
                          "I discovered and enriched **" . count($ingestedProposals) . " targeted prospects** in **{$location}** for **{$industry}**.\n\n" .
                          "I have prepared customized, compliance-checked proposal emails. Please review and approve before dispatch:";

            $stmtMsg = $db->prepare("INSERT INTO ai_agent_messages (
                conversation_id, user_id, role, content, tool_calls_json, tool_results_json, status, tokens_used
            ) VALUES (?, ?, 'assistant', ?, ?, ?, 'waiting_approval', 0)");

            $batchJson = json_encode($digestBatch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmtMsg->execute([$convId, $userId, $digestText, json_encode(['batch_approval' => true]), $batchJson]);

            WorkflowEngine::transitionState($wfId, WorkflowEngine::STATE_WAITING_FOR_APPROVAL, 'digest_review', ['batch_count' => count($ingestedProposals)], 'Waiting for morning digest review', $db);

            $summary = [
                'leads_discovered' => count($discovered),
                'proposals_drafted' => count($ingestedProposals),
                'status' => 'waiting_for_approval',
                'digest_created' => true
            ];
            $finalRunStatus = 'waiting_for_approval';
        } else {
            // Auto dispatch (only if explicit user configuration)
            $dispatchedCount = 0;
            // Create batch first
            $batch = BatchApprovalEngine::createDigestBatch($userId, $execId, $wfId, $ingestedProposals, 72, $db);
            foreach ($batch['items'] as $item) {
                try {
                    // Approve item first
                    BatchApprovalEngine::approveItem($userId, $item['token'], $db);
                    OutreachDispatcher::dispatchApprovedItem($userId, $item['token'], OutreachDispatcher::PROVIDER_MOCK, [], $db);
                    $dispatchedCount++;
                } catch (Throwable $e) {}
            }

            $summary = [
                'leads_discovered' => count($discovered),
                'dispatched_count' => $dispatchedCount,
                'status' => 'completed'
            ];
            $finalRunStatus = 'completed';
        }

    } catch (Throwable $eExec) {
        $runError = $eExec->getMessage();
        $finalRunStatus = 'failed';
        $summary = ['error' => $runError];
    }

    // 7. Update Schedule Run Record
    $completedAt = date('Y-m-d H:i:s');
    $updRun = $db->prepare("UPDATE ai_agent_schedule_runs
        SET status = ?, completed_at = ?, summary_json = ?, error_message = ?
        WHERE id = ?");
    $updRun->execute([
        $finalRunStatus,
        $completedAt,
        json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $runError,
        $execId
    ]);

    // 8. Advance Next Run and Release Lease Lock
    WorkflowScheduler::completeScheduleRun($schedId, $leaseToken, $runError, $db);
    $processed++;
    $results[] = [
        'schedule_id' => $schedId,
        'title' => $sched['title'],
        'status' => $finalRunStatus,
        'summary' => $summary
    ];
}

$workerDuration = round((microtime(true) - $workerStartTime) * 1000, 2);

$response = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'duration_ms' => $workerDuration,
    'claimed_schedules' => count($claimedSchedules),
    'processed' => $processed,
    'runs' => $results
];

if (php_sapi_name() === 'cli') {
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($response);
}
