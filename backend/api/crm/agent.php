<?php
// backend/api/crm/agent.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../agent_orchestrator.php';
require_once __DIR__ . '/../../ai_tool_registry.php';

// Validate User Authentication
$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];
$db = Database::getConnection();

// Self-healing migration
try {
    AgentOrchestrator::init($db);
} catch (Throwable $eInit) {
    error_log("AgentOrchestrator::init notice: " . $eInit->getMessage());
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: [];

$action = $input['action'] ?? ($_GET['action'] ?? 'chat');
$isStream = !empty($_GET['stream']) || !empty($input['stream']);

try {
    // 1. List conversations
    if ($action === 'list_conversations') {
        $search = trim($_GET['search'] ?? ($input['search'] ?? ''));
        $conversations = AgentOrchestrator::listConversations($userId, $search, $db);
        sendJsonResponse('success', 'Conversations retrieved.', [
            'conversations' => $conversations
        ]);
    }

    // 2. Create new conversation
    elseif ($action === 'create_conversation') {
        $title = trim($input['title'] ?? 'New Conversation');
        $newConv = AgentOrchestrator::createConversation($userId, $title, $db);
        sendJsonResponse('success', 'New conversation started.', [
            'conversation' => $newConv
        ]);
    }

    // 3. Get conversation messages
    elseif ($action === 'get_conversation') {
        $convId = trim($_GET['id'] ?? ($input['id'] ?? ''));
        if (empty($convId)) {
            sendJsonResponse('error', 'Conversation ID is required.', [], 400);
        }
        $data = AgentOrchestrator::getConversationMessages($userId, $convId, $db);
        sendJsonResponse('success', 'Conversation loaded.', $data);
    }

    // 4. Rename conversation
    elseif ($action === 'rename_conversation') {
        $convId = trim($input['id'] ?? '');
        $title = trim($input['title'] ?? '');
        if (empty($convId) || empty($title)) {
            sendJsonResponse('error', 'Conversation ID and title are required.', [], 400);
        }
        $res = AgentOrchestrator::renameConversation($userId, $convId, $title, $db);
        sendJsonResponse('success', 'Conversation renamed.', $res);
    }

    // 5. Delete conversation
    elseif ($action === 'delete_conversation') {
        $convId = trim($input['id'] ?? '');
        if (empty($convId)) {
            sendJsonResponse('error', 'Conversation ID is required.', [], 400);
        }
        $res = AgentOrchestrator::deleteConversation($userId, $convId, $db);
        sendJsonResponse('success', 'Conversation deleted.', $res);
    }

    // 6. Get capabilities & tool registry summary
    elseif ($action === 'get_capabilities') {
        $tools = AIToolRegistry::listTools();
        $categories = [];
        foreach ($tools as $t) {
            $cat = $t['category'] ?? 'general';
            if (!isset($categories[$cat])) $categories[$cat] = [];
            $categories[$cat][] = $t;
        }

        $plugins = PluginRegistry::listPlugins($userId, $db);

        sendJsonResponse('success', 'Agent capabilities loaded.', [
            'total_tools' => count($tools),
            'categories' => $categories,
            'plugins' => $plugins
        ]);
    }

    // 6a. List Universal Plugins with Connection Status
    elseif ($action === 'list_plugins') {
        $plugins = PluginRegistry::listPlugins($userId, $db);
        sendJsonResponse('success', 'Universal plugins loaded.', [
            'plugins' => $plugins,
            'total_plugins' => count($plugins)
        ]);
    }

    // 6a2. Toggle Plugin Activation Status
    elseif ($action === 'toggle_plugin') {
        $pluginId = trim($input['plugin_id'] ?? ($_GET['plugin_id'] ?? ''));
        $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : (isset($_GET['enabled']) ? (bool)$_GET['enabled'] : true);
        if (empty($pluginId)) {
            sendJsonResponse('error', 'Plugin ID is required.', [], 400);
        }
        $updated = PluginRegistry::setPluginActivation($pluginId, $enabled, $db);
        sendJsonResponse('success', "Plugin '{$pluginId}' activation updated.", [
            'plugin_id' => $pluginId,
            'is_enabled' => $enabled,
            'updated' => $updated
        ]);
    }

    // 6a3. Get Active Multi-Turn Workflow
    elseif ($action === 'get_active_workflow') {
        $convId = trim($input['conversation_id'] ?? ($_GET['conversation_id'] ?? ''));
        if (empty($convId)) {
            sendJsonResponse('error', 'Conversation ID is required.', [], 400);
        }
        $activeWf = WorkflowEngine::getActiveWorkflow($userId, $convId, $db);
        sendJsonResponse('success', 'Active workflow state retrieved.', [
            'workflow' => $activeWf
        ]);
    }

    // 6a4. Get Workflow Task DAG Graph & Status (Phase 4)
    elseif ($action === 'get_workflow_dag') {
        $wfId = trim($input['workflow_id'] ?? ($_GET['workflow_id'] ?? ''));
        if (empty($wfId)) {
            sendJsonResponse('error', 'Workflow ID is required.', [], 400);
        }
        $wf = WorkflowEngine::getWorkflow($wfId, $userId, $db);
        if (!$wf) {
            sendJsonResponse('error', 'Workflow not found.', [], 404);
        }
        $dag = ExecutionEngine::loadDAGFromWorkflow($wf, $db);
        sendJsonResponse('success', 'Workflow DAG retrieved.', [
            'workflow_id' => $wfId,
            'status' => $wf['state'],
            'dag' => $dag->toArray(),
            'nodes' => array_values($dag->getNodes())
        ]);
    }

    // 6a5. Explicit Maker-Checker Approval for Workflow Task (Phase 4)
    elseif ($action === 'approve_workflow_task') {
        $wfId = trim($input['workflow_id'] ?? '');
        $taskKey = trim($input['task_key'] ?? '');
        $token = trim($input['approval_token'] ?? '');
        if (empty($wfId) || empty($taskKey)) {
            sendJsonResponse('error', 'Workflow ID and Task Key are required.', [], 400);
        }
        try {
            $res = ExecutionEngine::resumeAfterApproval($wfId, $taskKey, $userId, ['approval_token' => $token], null, $db);
            sendJsonResponse('success', 'Workflow task approved and executed.', $res);
        } catch (Throwable $eAppr) {
            sendJsonResponse('error', 'Failed to approve workflow task: ' . $eAppr->getMessage(), [], 400);
        }
    }

    // 6a6. Reject Workflow Task (Phase 4)
    elseif ($action === 'reject_workflow_task') {
        $wfId = trim($input['workflow_id'] ?? '');
        $taskKey = trim($input['task_key'] ?? '');
        $reason = trim($input['reason'] ?? 'Rejected by user');
        if (empty($wfId) || empty($taskKey)) {
            sendJsonResponse('error', 'Workflow ID and Task Key are required.', [], 400);
        }
        try {
            $res = ExecutionEngine::rejectTask($wfId, $taskKey, $userId, $reason, null, $db);
            sendJsonResponse('success', 'Workflow task rejected.', $res);
        } catch (Throwable $eRej) {
            sendJsonResponse('error', 'Failed to reject workflow task: ' . $eRej->getMessage(), [], 400);
        }
    }

    // 6a7. Recover Interrupted Workflows (Phase 4)
    elseif ($action === 'recover_workflows') {
        $recoveredCount = ExecutionEngine::recoverAllInterruptedWorkflows($userId, $db);
        sendJsonResponse('success', "Recovered {$recoveredCount} interrupted workflows.", [
            'recovered_count' => $recoveredCount
        ]);
    }

    // 6b1. List Schedules (Phase 5)
    elseif ($action === 'list_schedules') {
        $statusFilter = trim($input['status'] ?? ($_GET['status'] ?? ''));
        $schedules = WorkflowScheduler::listSchedules($userId, $statusFilter ?: null, $db);
        sendJsonResponse('success', 'Schedules retrieved successfully.', [
            'schedules' => $schedules,
            'total_count' => count($schedules)
        ]);
    }

    // 6b2. Pause Schedule (Phase 5)
    elseif ($action === 'pause_schedule') {
        $scheduleId = trim($input['schedule_id'] ?? ($_GET['schedule_id'] ?? ''));
        if (empty($scheduleId)) sendJsonResponse('error', 'Schedule ID is required.', [], 400);
        $res = WorkflowScheduler::pauseSchedule($scheduleId, $userId, $db);
        sendJsonResponse($res ? 'success' : 'error', $res ? 'Schedule paused.' : 'Schedule not found or not active.', ['schedule_id' => $scheduleId]);
    }

    // 6b3. Resume Schedule (Phase 5)
    elseif ($action === 'resume_schedule') {
        $scheduleId = trim($input['schedule_id'] ?? ($_GET['schedule_id'] ?? ''));
        if (empty($scheduleId)) sendJsonResponse('error', 'Schedule ID is required.', [], 400);
        $res = WorkflowScheduler::resumeSchedule($scheduleId, $userId, $db);
        sendJsonResponse($res ? 'success' : 'error', $res ? 'Schedule resumed.' : 'Schedule not found or not paused.', ['schedule_id' => $scheduleId]);
    }

    // 6b4. Cancel Schedule (Phase 5)
    elseif ($action === 'cancel_schedule') {
        $scheduleId = trim($input['schedule_id'] ?? ($_GET['schedule_id'] ?? ''));
        if (empty($scheduleId)) sendJsonResponse('error', 'Schedule ID is required.', [], 400);
        $res = WorkflowScheduler::cancelSchedule($scheduleId, $userId, $db);
        sendJsonResponse($res ? 'success' : 'error', $res ? 'Schedule cancelled.' : 'Schedule not found.', ['schedule_id' => $scheduleId]);
    }

    // 6b5. Delete Schedule (Phase 5)
    elseif ($action === 'delete_schedule') {
        $scheduleId = trim($input['schedule_id'] ?? ($_GET['schedule_id'] ?? ''));
        if (empty($scheduleId)) sendJsonResponse('error', 'Schedule ID is required.', [], 400);
        $res = WorkflowScheduler::deleteSchedule($scheduleId, $userId, $db);
        sendJsonResponse($res ? 'success' : 'error', $res ? 'Schedule deleted.' : 'Schedule not found.', ['schedule_id' => $scheduleId]);
    }

    // 6b6. Preview Batch Approval Item (Phase 5)
    elseif ($action === 'preview_batch_item') {
        $token = trim($input['token'] ?? ($_GET['token'] ?? ''));
        if (empty($token)) sendJsonResponse('error', 'Approval token is required.', [], 400);
        try {
            $item = BatchApprovalEngine::previewItem($userId, $token, $db);
            sendJsonResponse('success', 'Batch item preview loaded.', $item);
        } catch (Throwable $ePreview) {
            sendJsonResponse('error', $ePreview->getMessage(), [], 400);
        }
    }

    // 6b7. Edit Batch Approval Item (Phase 5)
    elseif ($action === 'edit_batch_item') {
        $token = trim($input['token'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $body = trim($input['body'] ?? '');
        if (empty($token) || empty($subject) || empty($body)) {
            sendJsonResponse('error', 'Token, subject, and body are required.', [], 400);
        }
        try {
            $res = BatchApprovalEngine::editItem($userId, $token, $subject, $body, $db);
            sendJsonResponse('success', 'Proposal draft edited successfully.', $res);
        } catch (Throwable $eEdit) {
            sendJsonResponse('error', $eEdit->getMessage(), [], 400);
        }
    }

    // 6b8. Approve Batch Approval Item (Phase 5)
    elseif ($action === 'approve_batch_item') {
        $token = trim($input['token'] ?? '');
        if (empty($token)) sendJsonResponse('error', 'Approval token is required.', [], 400);
        try {
            $res = BatchApprovalEngine::approveItem($userId, $token, $db);
            sendJsonResponse('success', 'Proposal approved for dispatch.', $res);
        } catch (Throwable $eAppr) {
            sendJsonResponse('error', $eAppr->getMessage(), [], 400);
        }
    }

    // 6b9. Approve Selected Batch Items (Phase 5)
    elseif ($action === 'approve_selected_items') {
        $tokens = (array)($input['tokens'] ?? []);
        if (empty($tokens)) sendJsonResponse('error', 'Tokens array is required.', [], 400);
        try {
            $res = BatchApprovalEngine::approveSelected($userId, $tokens, $db);
            sendJsonResponse('success', 'Selected proposals approved.', $res);
        } catch (Throwable $eSel) {
            sendJsonResponse('error', $eSel->getMessage(), [], 400);
        }
    }

    // 6b10. Approve All Batch Items (Phase 5)
    elseif ($action === 'approve_all_items') {
        $runId = trim($input['schedule_run_id'] ?? '');
        if (empty($runId)) sendJsonResponse('error', 'Schedule Run ID is required.', [], 400);
        try {
            $res = BatchApprovalEngine::approveAll($userId, $runId, $db);
            sendJsonResponse('success', 'All batch proposals approved.', $res);
        } catch (Throwable $eAll) {
            sendJsonResponse('error', $eAll->getMessage(), [], 400);
        }
    }

    // 6b11. Reject Batch Item (Phase 5)
    elseif ($action === 'reject_batch_item') {
        $token = trim($input['token'] ?? '');
        $reason = trim($input['reason'] ?? 'Rejected by user');
        if (empty($token)) sendJsonResponse('error', 'Token is required.', [], 400);
        try {
            $res = BatchApprovalEngine::rejectItem($userId, $token, $reason, $db);
            sendJsonResponse('success', 'Item rejected.', $res);
        } catch (Throwable $eRej) {
            sendJsonResponse('error', $eRej->getMessage(), [], 400);
        }
    }

    // 6b12. Discard Batch (Phase 5)
    elseif ($action === 'discard_batch') {
        $runId = trim($input['schedule_run_id'] ?? '');
        $reason = trim($input['reason'] ?? 'Discarded by user');
        if (empty($runId)) sendJsonResponse('error', 'Schedule Run ID is required.', [], 400);
        try {
            $res = BatchApprovalEngine::discardBatch($userId, $runId, $reason, $db);
            sendJsonResponse('success', 'Batch discarded.', $res);
        } catch (Throwable $eDisc) {
            sendJsonResponse('error', $eDisc->getMessage(), [], 400);
        }
    }

    // 6b13. List Discovered Prospects (Phase 5B)
    elseif ($action === 'list_discovered_prospects') {
        $limit = max(1, min(100, (int)($input['limit'] ?? ($_GET['limit'] ?? 50))));
        $runId = trim($input['schedule_run_id'] ?? ($_GET['schedule_run_id'] ?? ''));

        $query = "SELECT * FROM ai_agent_discovered_prospects WHERE user_id = :user_id";
        $params = [':user_id' => $userId];
        if (!empty($runId)) {
            $query .= " AND schedule_run_id = :run_id";
            $params[':run_id'] = $runId;
        }
        $query .= " ORDER BY created_at DESC LIMIT :lim";

        $stmt = $db->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $prospects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJsonResponse('success', 'Discovered prospects retrieved.', [
            'prospects' => $prospects,
            'count' => count($prospects)
        ]);
    }

    // 6b14. List Outreach Logs (Phase 5B)
    elseif ($action === 'list_outreach_logs') {
        $limit = max(1, min(100, (int)($input['limit'] ?? ($_GET['limit'] ?? 50))));
        $status = trim($input['status'] ?? ($_GET['status'] ?? ''));

        $query = "SELECT * FROM ai_agent_outreach_logs WHERE user_id = :user_id";
        $params = [':user_id' => $userId];
        if (!empty($status)) {
            $query .= " AND status = :st";
            $params[':st'] = $status;
        }
        $query .= " ORDER BY created_at DESC LIMIT :lim";

        $stmt = $db->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJsonResponse('success', 'Outreach logs retrieved.', [
            'logs' => $logs,
            'count' => count($logs)
        ]);
    }

    // 6b15. Dispatch Approved Item (Phase 5B)
    elseif ($action === 'dispatch_approved_item') {
        $token = trim($input['token'] ?? '');
        $provider = trim($input['provider'] ?? 'smtp');
        if (empty($token)) sendJsonResponse('error', 'Approval token is required.', [], 400);

        try {
            $res = OutreachDispatcher::dispatchApprovedItem($userId, $token, $provider, [], $db);
            sendJsonResponse('success', 'Email dispatched successfully.', $res);
        } catch (Throwable $eDisp) {
            sendJsonResponse('error', $eDisp->getMessage(), [], 400);
        }
    }

    // 6b16. Manage Suppression List (Phase 5B)
    elseif ($action === 'manage_suppression') {
        $subAction = trim($input['sub_action'] ?? ($_GET['sub_action'] ?? 'list'));
        if ($subAction === 'add') {
            $email = strtolower(trim($input['email'] ?? ''));
            $reason = trim($input['reason'] ?? 'manual');
            if (empty($email)) sendJsonResponse('error', 'Email is required.', [], 400);
            OutreachDispatcher::recordBounceOrComplaint($userId, $email, $reason, $db);
            sendJsonResponse('success', "Email '{$email}' added to suppression list.");
        } else {
            $stmt = $db->prepare("SELECT * FROM ai_agent_suppression_list WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
            $stmt->execute([$userId]);
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Suppression list retrieved.', ['suppression_list' => $list]);
        }
    }

    // 6b. Get Agent Profile & Settings
    elseif ($action === 'get_agent_settings') {
        $agentName = 'Muse';
        try {
            $stmt = $db->prepare("SELECT value_text FROM ai_agent_memory WHERE user_id = ? AND key_name = 'agent_name' LIMIT 1");
            $stmt->execute([$userId]);
            $nameVal = $stmt->fetchColumn();
            if (!empty($nameVal)) $agentName = trim($nameVal);
        } catch (Throwable $e) {}

        // Real dynamic schedules + System presets
        $scheduledTasks = [];
        if (class_exists('WorkflowScheduler')) {
            try {
                $realSchedules = WorkflowScheduler::listSchedules($userId, null, $db);
                foreach ($realSchedules as $rs) {
                    $scheduledTasks[] = [
                        'id' => $rs['id'],
                        'title' => $rs['title'],
                        'schedule' => $rs['cron_expression'],
                        'next_run' => $rs['next_run_at'],
                        'category' => 'prospecting',
                        'icon' => 'calendar',
                        'badge' => 'AI',
                        'status' => $rs['status'],
                        'description' => "Autonomous scheduled prospecting & outreach. Target: " . ($rs['parameters']['target_industry'] ?? 'General') . " in " . ($rs['parameters']['target_location'] ?? 'Global')
                    ];
                }
            } catch (Throwable $eSched) {}
        }

        $systemPresets = [
            [
                'id' => 'st_1',
                'title' => 'Daily hot-leads WhatsApp pack',
                'schedule' => 'Every day at 17:45',
                'next_run' => '14 Nov, 17:45',
                'category' => 'whatsapp',
                'icon' => 'calendar',
                'badge' => 'J',
                'status' => 'active',
                'description' => 'Automatically scans CRM contacts for high-buying-intent leads with unreplied WhatsApp conversations in the past 7 days and prepares follow-ups.'
            ],
            [
                'id' => 'st_2',
                'title' => 'Heartbeat',
                'schedule' => 'Every 30 minutes',
                'next_run' => 'In 18 minutes',
                'category' => 'system',
                'icon' => 'heart',
                'badge' => '',
                'status' => 'active',
                'description' => 'System background heartbeat monitoring active webhooks, WhatsApp sync health, and incoming business lead alerts.'
            ],
            [
                'id' => 'st_3',
                'title' => 'International lead research (45/day)',
                'schedule' => 'Every day at 08:45',
                'next_run' => '14 Nov, 8:45',
                'category' => 'enrichment',
                'icon' => 'calendar',
                'badge' => 'J',
                'status' => 'active',
                'description' => 'Autonomous enrichment of prospective international contacts with verified company emails, phone codes, and company sizes.'
            ],
            [
                'id' => 'st_4',
                'title' => 'Executive Morning Briefing',
                'schedule' => 'Weekdays at 09:00 AM',
                'next_run' => 'Tomorrow, 9:00 AM',
                'category' => 'briefing',
                'icon' => 'sparkles',
                'badge' => '',
                'status' => 'active',
                'description' => 'Synthesizes today\'s scheduled meetings, at-risk deals in pipeline, and overdue CRM tasks into an executive summary.'
            ],
            [
                'id' => 'st_5',
                'title' => 'Stalled Deals Reviver',
                'schedule' => 'Every Monday at 10:00 AM',
                'next_run' => 'Next Monday, 10:00 AM',
                'category' => 'deals',
                'icon' => 'dollar-sign',
                'badge' => '',
                'status' => 'active',
                'description' => 'Detects deals in pipeline with no activity or note added in over 14 days and automatically creates urgent follow-up tasks.'
            ],
            [
                'id' => 'st_6',
                'title' => 'Run batch script',
                'schedule' => 'Executed Nov 11, 14:20',
                'next_run' => 'Completed',
                'category' => 'crm',
                'icon' => 'check-circle-2',
                'badge' => '',
                'status' => 'completed',
                'description' => 'Batch customer data normalization and conversation index update executed successfully across channels.'
            ]
        ];

        $allTasks = array_merge($scheduledTasks, $systemPresets);

        sendJsonResponse('success', 'Agent settings retrieved.', [
            'agent_name' => $agentName,
            'status' => 'connected',
            'scheduled_tasks' => $allTasks
        ]);
    }

    // 6c. Update Agent Name & Settings
    elseif ($action === 'update_agent_settings') {
        $newName = trim($input['agent_name'] ?? '');
        if (empty($newName)) {
            sendJsonResponse('error', 'Agent name cannot be empty.', [], 400);
        }

        try {
            $stmt = $db->prepare("INSERT INTO ai_agent_memory (user_id, memory_type, key_name, value_text) 
                VALUES (?, 'setting', 'agent_name', ?) 
                ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)");
            $stmt->execute([$userId, $newName]);
        } catch (Throwable $e) {
            sendJsonResponse('error', 'Failed to update agent name: ' . $e->getMessage(), [], 500);
        }

        sendJsonResponse('success', 'Agent name updated successfully.', [
            'agent_name' => $newName
        ]);
    }

    // 6d. Get Performance Telemetry & Metrics
    elseif ($action === 'get_telemetry') {
        $recent = AgentTelemetry::getRecentLogs($userId, 20, $db);
        $summary = AgentTelemetry::getSummaryMetrics($userId, $db);
        sendJsonResponse('success', 'Telemetry retrieved.', [
            'summary' => $summary,
            'recent_logs' => $recent
        ]);
    }

    // 6e. Check Local Model Health & Status
    elseif ($action === 'local_ai_health') {
        $health = AgentAIClient::checkLocalHealth();
        sendJsonResponse('success', 'Local AI model health retrieved.', $health);
    }

    // 7. Approve & Execute Pending Action
    elseif ($action === 'approve_action') {
        $convId = trim($input['conversation_id'] ?? '');
        $toolId = trim($input['tool_id'] ?? '');
        $toolArgs = $input['arguments'] ?? [];

        if (empty($toolId) || empty($convId)) {
            sendJsonResponse('error', 'Conversation ID and Tool ID are required.', [], 400);
        }

        // Update the waiting message in ai_agent_messages to 'approved' and record edited args if any
        try {
            $stmtFind = $db->prepare("SELECT id, tool_results_json FROM ai_agent_messages WHERE conversation_id = ? AND user_id = ? AND status = 'waiting_approval' ORDER BY id DESC LIMIT 1");
            $stmtFind->execute([$convId, $userId]);
            $waitingRow = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if ($waitingRow) {
                $parsedResults = json_decode($waitingRow['tool_results_json'] ?? '{}', true) ?: [];
                if (!empty($toolArgs)) {
                    $parsedResults['arguments'] = $toolArgs;
                }
                $parsedResults['status'] = 'approved';
                $parsedResults['approved_at'] = date('Y-m-d H:i:s');
                
                $stmtUpd = $db->prepare("UPDATE ai_agent_messages SET status = 'approved', tool_results_json = ? WHERE id = ?");
                $stmtUpd->execute([json_encode($parsedResults), $waitingRow['id']]);

                // Update audit log
                try {
                    $stmtAudit = $db->prepare("UPDATE ai_agent_audit_logs SET approval_status = 'approved', input_json = ? WHERE conversation_id = ? AND user_id = ? AND tool_id = ? AND approval_status = 'rejected' ORDER BY id DESC LIMIT 1");
                    $stmtAudit->execute([json_encode($toolArgs), $convId, $userId, $toolId]);
                } catch (Throwable $eAudit) {}
            }
        } catch (Throwable $eDb) {
            error_log("Failed to update message status to approved: " . $eDb->getMessage());
        }

        // Check if pending message was paused at a Workflow DAG approval gate
        $pendingResults = isset($waitingRow) ? (json_decode($waitingRow['tool_results_json'] ?? '{}', true) ?: []) : [];
        $pendingWfId = $pendingResults['workflow_id'] ?? null;
        $pendingTaskKey = $pendingResults['task_key'] ?? null;

        if ($pendingWfId && $pendingTaskKey && class_exists('ExecutionEngine')) {
            try {
                $resumeRes = ExecutionEngine::resumeAfterApproval($pendingWfId, $pendingTaskKey, $userId, [], null, $db);
                sendJsonResponse('success', 'Workflow task approved and execution resumed.', [
                    'workflow_id' => $pendingWfId,
                    'task_key' => $pendingTaskKey,
                    'resume_result' => $resumeRes
                ]);
                return;
            } catch (Throwable $eResume) {
                // fall through to standard tool execution if DAG resume throws
            }
        }

        // Execute tool directly
        $toolResult = AIToolRegistry::executeTool($toolId, $userId, $toolArgs, $db);

        // Resume conversation with synthesis
        $resumeMsg = "Action confirmed and executed. Please review result and provide completion confirmation.";
        $res = AgentOrchestrator::processChat($userId, $convId, $resumeMsg, [
            'approved_tool_id' => $toolId,
            'approved_tool_result' => $toolResult
        ], null, $db);

        sendJsonResponse('success', 'Action approved and executed successfully.', [
            'tool_result' => $toolResult,
            'chat_response' => $res
        ]);
    }

    // 7b. Reject Pending Action
    elseif ($action === 'reject_action') {
        $convId = trim($input['conversation_id'] ?? '');
        $toolId = trim($input['tool_id'] ?? '');

        if (empty($convId)) {
            sendJsonResponse('error', 'Conversation ID is required.', [], 400);
        }

        try {
            $stmtFind = $db->prepare("SELECT id, tool_results_json FROM ai_agent_messages WHERE conversation_id = ? AND user_id = ? AND status = 'waiting_approval' ORDER BY id DESC LIMIT 1");
            $stmtFind->execute([$convId, $userId]);
            $waitingRow = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if ($waitingRow) {
                $parsedResults = json_decode($waitingRow['tool_results_json'] ?? '{}', true) ?: [];
                $parsedResults['status'] = 'rejected';
                $parsedResults['rejected_at'] = date('Y-m-d H:i:s');
                
                $stmtUpd = $db->prepare("UPDATE ai_agent_messages SET status = 'rejected', tool_results_json = ? WHERE id = ?");
                $stmtUpd->execute([json_encode($parsedResults), $waitingRow['id']]);

                try {
                    $stmtAudit = $db->prepare("UPDATE ai_agent_audit_logs SET approval_status = 'cancelled' WHERE conversation_id = ? AND user_id = ? AND tool_id = ? ORDER BY id DESC LIMIT 1");
                    $stmtAudit->execute([$convId, $userId, $toolId]);
                } catch (Throwable $eAudit) {}
            }
        } catch (Throwable $eDb) {
            error_log("Failed to update message status to rejected: " . $eDb->getMessage());
        }

        sendJsonResponse('success', 'Action cancelled and rejected successfully.');
    }

    // 7b. Get Connected Plugins & Status
    elseif ($action === 'get_plugins') {
        require_once __DIR__ . '/../../external_apps_helper.php';
        require_once __DIR__ . '/../../smtp_helper.php';

        $isGoogle = ExternalAppsHelper::isGoogleConnected($userId);
        $isZoom = ExternalAppsHelper::isZoomConnected($userId);

        $googleEmail = null;
        $googleScopes = [];
        if ($isGoogle) {
            $stmtG = $db->prepare("SELECT email, connected_email, scopes FROM external_app_connections WHERE user_id = ? AND provider = 'google' LIMIT 1");
            $stmtG->execute([$userId]);
            $gRow = $stmtG->fetch(PDO::FETCH_ASSOC);
            if ($gRow) {
                $googleEmail = !empty($gRow['connected_email']) ? $gRow['connected_email'] : $gRow['email'];
                $googleScopes = !empty($gRow['scopes']) ? explode(',', $gRow['scopes']) : [];
            }
        }

        $isCalendar = $isGoogle && in_array('https://www.googleapis.com/auth/calendar.events', $googleScopes);
        $isGmail = $isGoogle && (in_array('https://www.googleapis.com/auth/gmail.send', $googleScopes) || in_array('https://mail.google.com/', $googleScopes));

        $smtp = SMTPHelper::getSMTPConfig($userId);
        $isEmailConnected = ($isGmail || !empty($smtp));
        $emailAddress = $isGmail ? $googleEmail : ($smtp['sender_email'] ?? null);

        // WhatsApp
        $stmtWa = $db->prepare("SELECT id, business_name, phone_number, status FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
        $stmtWa->execute([$userId]);
        $wa = $stmtWa->fetch(PDO::FETCH_ASSOC);

        // Zoom
        $zoomEmail = null;
        if ($isZoom) {
            $stmtZ = $db->prepare("SELECT email, connected_email FROM external_app_connections WHERE user_id = ? AND provider = 'zoom' LIMIT 1");
            $stmtZ->execute([$userId]);
            $zRow = $stmtZ->fetch(PDO::FETCH_ASSOC);
            $zoomEmail = $zRow ? ($zRow['connected_email'] ?: $zRow['email']) : null;
        }

        $plugins = [
            [
                'id' => 'google_meet',
                'name' => 'Google Meet',
                'category' => 'meetings',
                'badge' => 'HD Video',
                'description' => '1-Click encrypted Google Meet room creation, instant video links, and calendar invitation dispatch.',
                'connected' => $isCalendar,
                'account' => $isCalendar ? $googleEmail : null,
                'connect_url' => '/backend/api/external_apps/auth.php?type=calendar',
                'disconnect_url' => '/backend/api/external_apps/disconnect.php?provider=google',
                'suggested_prompt' => 'Generate a Google Meet room link for our upcoming quarterly sales presentation.'
            ],
            [
                'id' => 'google_calendar',
                'name' => 'Google Calendar',
                'category' => 'meetings',
                'badge' => 'Calendar 31',
                'description' => 'Autonomous business meeting scheduling, conflict detection, and agenda synchronization.',
                'connected' => $isCalendar,
                'account' => $isCalendar ? $googleEmail : null,
                'connect_url' => '/backend/api/external_apps/auth.php?type=calendar',
                'disconnect_url' => '/backend/api/external_apps/disconnect.php?provider=google',
                'suggested_prompt' => 'Schedule a 30-minute discovery call with Alex Rivera for tomorrow at 3:00 PM.'
            ],
            [
                'id' => 'email',
                'name' => 'Gmail & Business Email',
                'category' => 'messaging',
                'badge' => $isGmail ? 'Gmail OAuth' : ($smtp ? 'SMTP / IMAP' : 'Email API'),
                'description' => 'Autonomous inbox monitoring, email draft authoring, automated follow-ups, and thread summarization.',
                'connected' => $isEmailConnected,
                'account' => $emailAddress,
                'connect_url' => '/dashboard/smtp.html',
                'disconnect_url' => $isGmail ? '/backend/api/external_apps/disconnect.php?provider=google' : null,
                'suggested_prompt' => 'Draft a compelling cold outreach email offering LinkPilot AI CRM to fintech founders.'
            ],
            [
                'id' => 'whatsapp',
                'name' => 'WhatsApp Cloud API',
                'category' => 'messaging',
                'badge' => 'Official API',
                'description' => 'Instant customer engagement, automated lead follow-ups, template broadcasting, and chat sync.',
                'connected' => !empty($wa),
                'account' => $wa ? ($wa['business_name'] . ' (' . $wa['phone_number'] . ')') : null,
                'connect_url' => '/dashboard/setup.html',
                'disconnect_url' => null,
                'suggested_prompt' => 'Check WhatsApp for any customer inquiries that are still unreplied.'
            ],
            [
                'id' => 'zoom',
                'name' => 'Zoom Video Meetings',
                'category' => 'meetings',
                'badge' => 'Cloud Video',
                'description' => 'Instant meeting creation, client calendar invitations, and meeting recording transcript access.',
                'connected' => $isZoom,
                'account' => $zoomEmail,
                'connect_url' => '/backend/api/external_apps/zoom_auth.php',
                'disconnect_url' => '/backend/api/external_apps/disconnect.php?provider=zoom',
                'suggested_prompt' => 'Create a Zoom meeting room for customer onboarding and prepare meeting notes template.'
            ],
            [
                'id' => 'linkpilot_crm',
                'name' => 'LinkPilot CRM Contacts & Deals',
                'category' => 'crm',
                'badge' => 'Core Database',
                'description' => 'Lead profiles, custom tags, sales pipeline stages, deal size, and company intelligence.',
                'connected' => true,
                'account' => 'Active Workspace',
                'connect_url' => null,
                'disconnect_url' => null,
                'suggested_prompt' => 'List top 5 deals in the pipeline currently in negotiation stage.'
            ],
            [
                'id' => 'web_scraper',
                'name' => 'Live Web Scraper & Google Search',
                'category' => 'ai',
                'badge' => 'Chromium Engine',
                'description' => 'Real-time online competitor research, market price lookup, and live webpage content extraction.',
                'connected' => true,
                'account' => 'Active Search Engine',
                'connect_url' => null,
                'disconnect_url' => null,
                'suggested_prompt' => 'Scrape pricing tiers and key marketing headlines from https://stripe.com/pricing'
            ]
        ];

        sendJsonResponse('success', 'Plugins retrieved successfully.', [
            'plugins' => $plugins,
            'active_count' => count(array_filter($plugins, function($p) { return !empty($p['connected']); }))
        ]);
    }

    // 7c. Autonomous Schedules Management
    elseif ($action === 'list_schedules') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $status = !empty($_GET['status']) ? trim($_GET['status']) : null;
        $schedules = WorkflowScheduler::listSchedules($userId, $status, $db);
        sendJsonResponse('success', 'Schedules retrieved successfully.', [
            'schedules' => $schedules
        ]);
    }

    elseif ($action === 'create_schedule') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $title = trim($input['title'] ?? 'Autonomous Prospecting Schedule');
        $cron = trim($input['cron_expression'] ?? '0 9 * * *');
        $timezone = trim($input['timezone'] ?? 'UTC');
        $taskType = trim($input['task_type'] ?? 'prospecting_and_outreach');
        $parameters = isset($input['parameters']) && is_array($input['parameters']) ? $input['parameters'] : [];
        $approvalPolicy = trim($input['approval_policy'] ?? 'digest_review');

        $schedule = WorkflowScheduler::createSchedule(
            $userId, $title, $cron, $timezone, $taskType, $parameters, null, $approvalPolicy, $db
        );
        WorkflowScheduler::logAudit($userId, 'create_schedule', 'schedule', $schedule['id'], ['title' => $title, 'cron' => $cron], null, $db);
        sendJsonResponse('success', 'Schedule created successfully.', [
            'schedule' => $schedule
        ]);
    }

    elseif ($action === 'update_schedule') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $scheduleId = trim($input['schedule_id'] ?? ($_GET['schedule_id'] ?? ''));
        if (empty($scheduleId)) {
            sendJsonResponse('error', 'Schedule ID is required.', [], 400);
        }
        $updated = WorkflowScheduler::updateSchedule($scheduleId, $userId, $input, $db);
        sendJsonResponse('success', 'Schedule updated successfully.', [
            'schedule' => $updated
        ]);
    }

    elseif ($action === 'pause_schedule') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $scheduleId = trim($input['schedule_id'] ?? ($_GET['schedule_id'] ?? ''));
        if (empty($scheduleId)) {
            sendJsonResponse('error', 'Schedule ID is required.', [], 400);
        }
        $ok = WorkflowScheduler::pauseSchedule($scheduleId, $userId, $db);
        if ($ok) {
            WorkflowScheduler::logAudit($userId, 'pause_schedule', 'schedule', $scheduleId, null, null, $db);
            sendJsonResponse('success', 'Schedule paused successfully.');
        } else {
            sendJsonResponse('error', 'Failed to pause schedule or unauthorized.', [], 400);
        }
    }

    elseif ($action === 'resume_schedule') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $scheduleId = trim($input['schedule_id'] ?? ($_GET['schedule_id'] ?? ''));
        if (empty($scheduleId)) {
            sendJsonResponse('error', 'Schedule ID is required.', [], 400);
        }
        $ok = WorkflowScheduler::resumeSchedule($scheduleId, $userId, $db);
        if ($ok) {
            WorkflowScheduler::logAudit($userId, 'resume_schedule', 'schedule', $scheduleId, null, null, $db);
            sendJsonResponse('success', 'Schedule resumed successfully.');
        } else {
            sendJsonResponse('error', 'Failed to resume schedule or unauthorized.', [], 400);
        }
    }

    elseif ($action === 'delete_schedule') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $scheduleId = trim($input['schedule_id'] ?? ($_GET['schedule_id'] ?? ''));
        if (empty($scheduleId)) {
            sendJsonResponse('error', 'Schedule ID is required.', [], 400);
        }
        $ok = WorkflowScheduler::deleteSchedule($scheduleId, $userId, $db);
        if ($ok) {
            sendJsonResponse('success', 'Schedule deleted successfully.');
        } else {
            sendJsonResponse('error', 'Failed to delete schedule or unauthorized.', [], 400);
        }
    }

    // 7d. Execution History & Retry
    elseif ($action === 'list_schedule_runs') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $scheduleId = !empty($_GET['schedule_id']) ? trim($_GET['schedule_id']) : null;
        $limit = !empty($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $runs = WorkflowScheduler::listRuns($userId, $scheduleId, $limit, $db);
        sendJsonResponse('success', 'Schedule runs retrieved successfully.', [
            'runs' => $runs
        ]);
    }

    elseif ($action === 'retry_schedule_run') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $runId = trim($input['run_id'] ?? ($_GET['run_id'] ?? ''));
        if (empty($runId)) {
            sendJsonResponse('error', 'Run ID is required.', [], 400);
        }
        $retryRes = WorkflowScheduler::retryScheduleRun($runId, $userId, $db);
        sendJsonResponse('success', 'Schedule run queued for retry.', $retryRes);
    }

    // 7e. Approval Center
    elseif ($action === 'list_pending_approvals') {
        require_once __DIR__ . '/../../agent/BatchApprovalEngine.php';
        $scheduleRunId = !empty($_GET['schedule_run_id']) ? trim($_GET['schedule_run_id']) : null;
        $items = BatchApprovalEngine::listPendingForUser($userId, $scheduleRunId, 100, $db);
        sendJsonResponse('success', 'Pending approvals retrieved successfully.', [
            'items' => $items,
            'total_pending' => count($items)
        ]);
    }

    elseif ($action === 'preview_approval_item') {
        require_once __DIR__ . '/../../agent/BatchApprovalEngine.php';
        $token = trim($input['token'] ?? ($_GET['token'] ?? ''));
        if (empty($token)) {
            sendJsonResponse('error', 'Approval token is required.', [], 400);
        }
        $preview = BatchApprovalEngine::previewItem($userId, $token, $db);
        sendJsonResponse('success', 'Approval item preview retrieved.', [
            'item' => $preview
        ]);
    }

    elseif ($action === 'update_approval_item') {
        require_once __DIR__ . '/../../agent/BatchApprovalEngine.php';
        $token = trim($input['token'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $body = trim($input['body'] ?? '');
        if (empty($token) || empty($subject) || empty($body)) {
            sendJsonResponse('error', 'Token, subject, and body are required for editing.', [], 400);
        }
        $res = BatchApprovalEngine::editItem($userId, $token, $subject, $body, $db);
        sendJsonResponse('success', 'Draft proposal updated and new token issued.', $res);
    }

    elseif ($action === 'approve_item') {
        require_once __DIR__ . '/../../agent/BatchApprovalEngine.php';
        require_once __DIR__ . '/../../agent/OutreachDispatcher.php';
        $token = trim($input['token'] ?? '');
        $dispatchNow = !empty($input['dispatch']);
        $provider = trim($input['provider'] ?? 'smtp');

        if (empty($token)) {
            sendJsonResponse('error', 'Approval token is required.', [], 400);
        }

        $appr = BatchApprovalEngine::approveItem($userId, $token, $db);
        $dispatchRes = null;
        if ($dispatchNow) {
            $dispatchRes = OutreachDispatcher::dispatchApprovedItem($userId, $token, $provider, [], $db);
        }

        sendJsonResponse('success', 'Proposal approved successfully.', [
            'approval' => $appr,
            'dispatch' => $dispatchRes
        ]);
    }

    elseif ($action === 'approve_selected') {
        require_once __DIR__ . '/../../agent/BatchApprovalEngine.php';
        require_once __DIR__ . '/../../agent/OutreachDispatcher.php';
        $tokens = isset($input['tokens']) && is_array($input['tokens']) ? $input['tokens'] : [];
        $dispatchNow = !empty($input['dispatch']);
        $provider = trim($input['provider'] ?? 'smtp');

        if (empty($tokens)) {
            sendJsonResponse('error', 'List of tokens is required.', [], 400);
        }

        $batchRes = BatchApprovalEngine::approveSelected($userId, $tokens, $db);
        $dispatches = [];
        if ($dispatchNow) {
            foreach ($batchRes['approved'] as $appr) {
                try {
                    $dRes = OutreachDispatcher::dispatchApprovedItem($userId, $appr['token'] ?? '', $provider, [], $db);
                    $dispatches[] = $dRes;
                } catch (Throwable $eD) {
                    $dispatches[] = ['error' => $eD->getMessage()];
                }
            }
        }

        sendJsonResponse('success', 'Selected proposals approved.', [
            'approvals' => $batchRes,
            'dispatches' => $dispatches
        ]);
    }

    elseif ($action === 'approve_all') {
        require_once __DIR__ . '/../../agent/BatchApprovalEngine.php';
        $scheduleRunId = trim($input['schedule_run_id'] ?? ($_GET['schedule_run_id'] ?? ''));
        if (empty($scheduleRunId)) {
            sendJsonResponse('error', 'schedule_run_id is required to approve all items in a batch.', [], 400);
        }
        $res = BatchApprovalEngine::approveAll($userId, $scheduleRunId, $db);
        sendJsonResponse('success', 'All pending proposals in batch approved.', $res);
    }

    elseif ($action === 'reject_item') {
        require_once __DIR__ . '/../../agent/BatchApprovalEngine.php';
        $token = trim($input['token'] ?? '');
        $reason = trim($input['reason'] ?? 'Rejected by user');
        if (empty($token)) {
            sendJsonResponse('error', 'Approval token is required.', [], 400);
        }
        $res = BatchApprovalEngine::rejectItem($userId, $token, $reason, $db);
        sendJsonResponse('success', 'Proposal rejected successfully.', $res);
    }

    // 7f. Prospect Review
    elseif ($action === 'list_discovered_prospects') {
        require_once __DIR__ . '/../../agent/LeadProspectingEngine.php';
        $filters = [
            'status' => !empty($_GET['status']) ? trim($_GET['status']) : null,
            'verification_status' => !empty($_GET['verification_status']) ? trim($_GET['verification_status']) : null,
            'search' => !empty($_GET['search']) ? trim($_GET['search']) : null,
            'schedule_id' => !empty($_GET['schedule_id']) ? trim($_GET['schedule_id']) : null,
        ];
        $limit = !empty($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $prospects = LeadProspectingEngine::listProspectsForUser($userId, $filters, $limit, $db);
        sendJsonResponse('success', 'Discovered prospects retrieved successfully.', [
            'prospects' => $prospects,
            'count' => count($prospects)
        ]);
    }

    // 7g. Outreach Logs
    elseif ($action === 'list_outreach_logs') {
        require_once __DIR__ . '/../../agent/OutreachDispatcher.php';
        $filters = [
            'status' => !empty($_GET['status']) ? trim($_GET['status']) : null,
            'provider' => !empty($_GET['provider']) ? trim($_GET['provider']) : null,
            'schedule_id' => !empty($_GET['schedule_id']) ? trim($_GET['schedule_id']) : null,
        ];
        $limit = !empty($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $logs = OutreachDispatcher::listLogsForUser($userId, $filters, $limit, $db);
        sendJsonResponse('success', 'Outreach logs retrieved successfully.', [
            'logs' => $logs,
            'count' => count($logs)
        ]);
    }

    // 7h. Operational Controls
    elseif ($action === 'get_operational_controls') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $settings = WorkflowScheduler::getWorkspaceSettings($userId, $db);
        sendJsonResponse('success', 'Operational controls retrieved.', [
            'settings' => $settings
        ]);
    }

    elseif ($action === 'update_operational_controls') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $updated = WorkflowScheduler::updateWorkspaceSettings($userId, $input, $db);
        sendJsonResponse('success', 'Operational controls updated successfully.', [
            'settings' => $updated
        ]);
    }

    // 7i. Audit History
    elseif ($action === 'get_audit_history') {
        require_once __DIR__ . '/../../agent/WorkflowScheduler.php';
        $limit = !empty($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $logs = WorkflowScheduler::getAuditLogs($userId, $limit, $db);
        sendJsonResponse('success', 'Audit history retrieved successfully.', [
            'audit_logs' => $logs
        ]);
    }

    // 8. Chat Execution (Streaming or Standard JSON)
    elseif ($action === 'chat') {
        $convId = trim($input['conversation_id'] ?? ($_GET['conversation_id'] ?? ''));
        $message = trim($input['message'] ?? '');

        if (empty($convId)) {
            $newConv = AgentOrchestrator::createConversation($userId, 'New Conversation', $db);
            $convId = $newConv['id'];
        }

        if (empty($message)) {
            sendJsonResponse('error', 'Message cannot be empty.', [], 400);
        }

        if ($isStream) {
            // Disable output buffering
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            // Flush SSE headers
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            flush();

            $emitter = function($event, $data) {
                echo "event: {$event}\n";
                echo "data: " . json_encode($data) . "\n\n";
                @ob_flush();
                @flush();
            };

            try {
                AgentOrchestrator::processChat($userId, $convId, $message, $input, $emitter, $db);
            } catch (Throwable $streamEx) {
                $emitter('error', ['message' => $streamEx->getMessage()]);
            }
            exit(0);
        } else {
            // Standard JSON Request/Response
            $result = AgentOrchestrator::processChat($userId, $convId, $message, $input, null, $db);
            sendJsonResponse('success', 'Agent response generated.', $result);
        }
    }

    else {
        sendJsonResponse('error', "Unknown action '{$action}'.", [], 400);
    }

} catch (Throwable $e) {
    if ($isStream) {
        echo "event: error\n";
        echo "data: " . json_encode(['message' => $e->getMessage()]) . "\n\n";
        @flush();
        exit(0);
    }
    sendJsonResponse('error', $e->getMessage(), [], 500);
}
