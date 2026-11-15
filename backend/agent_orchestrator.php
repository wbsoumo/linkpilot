<?php
// backend/agent_orchestrator.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/agent_db_migration.php';
require_once __DIR__ . '/ai_tool_registry.php';
require_once __DIR__ . '/agent_ai_client.php';
require_once __DIR__ . '/crm_sync_helper.php';
require_once __DIR__ . '/agent_cache.php';
require_once __DIR__ . '/agent_telemetry.php';
require_once __DIR__ . '/agent_router.php';
if (file_exists(__DIR__ . '/agent/AgentMigration2.php')) {
    require_once __DIR__ . '/agent/AgentMigration2.php';
    require_once __DIR__ . '/agent/PluginRegistry.php';
    require_once __DIR__ . '/agent/WorkflowEngine.php';
    require_once __DIR__ . '/agent/ContextManager.php';
}
if (file_exists(__DIR__ . '/agent/AgentMigration4.php')) {
    require_once __DIR__ . '/agent/AgentMigration4.php';
    require_once __DIR__ . '/agent/TaskPlanDAG.php';
    require_once __DIR__ . '/agent/VerificationEngine.php';
    require_once __DIR__ . '/agent/TaskPlanner.php';
    require_once __DIR__ . '/agent/ExecutionEngine.php';
}

class AgentOrchestrator {

    /**
     * Initializes environment and tables.
     */
    public static function init(PDO $db) {
        AgentDBMigration::run($db);
        if (class_exists('AgentMigration2')) {
            AgentMigration2::run($db);
        }
        if (class_exists('AgentMigration4')) {
            AgentMigration4::run($db);
        }
        AgentTelemetry::initTable($db);
        AIToolRegistry::init();
        if (class_exists('PluginRegistry')) {
            PluginRegistry::init($db);
        }
    }

    /**
     * List user conversations with recent snippet and message count.
     */
    public static function listConversations($userId, $search = '', ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();
        self::init($db);

        $sql = "SELECT c.id, c.title, c.summary, c.is_pinned, c.created_at, c.updated_at,
                       (SELECT COUNT(*) FROM ai_agent_messages WHERE conversation_id = c.id) as message_count,
                       (SELECT content FROM ai_agent_messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) as last_message
                FROM ai_agent_conversations c
                WHERE c.user_id = :user_id AND c.is_archived = 0";
        $binds = [':user_id' => $userId];

        if (!empty($search)) {
            $sql .= " AND (c.title LIKE :s OR c.id IN (SELECT conversation_id FROM ai_agent_messages WHERE user_id = :user_id2 AND content LIKE :s2))";
            $binds[':s'] = "%$search%";
            $binds[':user_id2'] = $userId;
            $binds[':s2'] = "%$search%";
        }

        $sql .= " ORDER BY c.is_pinned DESC, c.updated_at DESC LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute($binds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new conversation session.
     */
    public static function createConversation($userId, $title = 'New Conversation', ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();
        self::init($db);

        $convId = 'conv_' . bin2hex(random_bytes(12));
        $stmt = $db->prepare("INSERT INTO ai_agent_conversations (id, user_id, title) VALUES (?, ?, ?)");
        $stmt->execute([$convId, $userId, $title]);

        return [
            'id' => $convId,
            'title' => $title,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Rename conversation title.
     */
    public static function renameConversation($userId, $convId, $newTitle, ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();
        self::init($db);

        $title = trim($newTitle);
        if (empty($title)) throw new Exception("Title cannot be empty.");

        $stmt = $db->prepare("UPDATE ai_agent_conversations SET title = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $convId, $userId]);

        return ['id' => $convId, 'title' => $title];
    }

    /**
     * Delete conversation session and all related messages.
     */
    public static function deleteConversation($userId, $convId, ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();
        self::init($db);

        $stmt = $db->prepare("DELETE FROM ai_agent_messages WHERE conversation_id = ? AND user_id = ?");
        $stmt->execute([$convId, $userId]);

        $stmtC = $db->prepare("DELETE FROM ai_agent_conversations WHERE id = ? AND user_id = ?");
        $stmtC->execute([$convId, $userId]);

        return ['id' => $convId, 'deleted' => true];
    }

    /**
     * Fetch all messages in a conversation.
     */
    public static function getConversationMessages($userId, $convId, ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();
        self::init($db);

        $stmtC = $db->prepare("SELECT * FROM ai_agent_conversations WHERE id = ? AND user_id = ?");
        $stmtC->execute([$convId, $userId]);
        $conv = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$conv) throw new Exception("Conversation not found.");

        $stmtM = $db->prepare("SELECT id, role, content, tool_calls_json, tool_results_json, status, tokens_used, created_at FROM ai_agent_messages WHERE conversation_id = ? AND user_id = ? ORDER BY id ASC");
        $stmtM->execute([$convId, $userId]);
        $messages = $stmtM->fetchAll(PDO::FETCH_ASSOC);

        foreach ($messages as &$msg) {
            $msg['tool_calls'] = json_decode($msg['tool_calls_json'] ?? '[]', true) ?: [];
            $msg['tool_results'] = json_decode($msg['tool_results_json'] ?? '[]', true) ?: [];
            unset($msg['tool_calls_json'], $msg['tool_results_json']);
        }

        return [
            'conversation' => $conv,
            'messages' => $messages
        ];
    }

    /**
     * Builds comprehensive prompt and business context.
     */
    public static function buildSystemPrompt($userId, PDO $db) {
        // User profile & organization
        $user = [];
        try {
            $stmt = $db->prepare("SELECT u.name, u.email, u.role, p.company_name, p.industry, p.timezone FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {}
        $userName = $user['name'] ?? 'User';
        $companyName = $user['company_name'] ?? 'LinkPilot Workspace';

        // Integrations overview
        $integrationsSummary = "- WhatsApp Cloud API: Not Connected\n- Business Email: Not Connected\n- Google Calendar: Not Connected\n- Zoom Meetings: Not Connected";
        try {
            $stmtWa = $db->prepare("SELECT status, business_name FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
            $stmtWa->execute([$userId]);
            $wa = $stmtWa->fetch(PDO::FETCH_ASSOC);

            $smtp = SMTPHelper::getSMTPConfig($userId);
            $hasGmail = false;
            try {
                $stmtGmail = $db->prepare("SELECT access_token FROM external_app_connections WHERE user_id = ? AND provider = 'google' AND status = 'connected' LIMIT 1");
                $stmtGmail->execute([$userId]);
                $hasGmail = (bool)$stmtGmail->fetchColumn();
            } catch (Throwable $e) {}

            $hasGoogleCal = false;
            $hasZoom = false;
            if (class_exists('ExternalAppsHelper')) {
                try { $hasGoogleCal = ExternalAppsHelper::isGoogleConnected($userId); } catch (Throwable $e) {}
                try { $hasZoom = ExternalAppsHelper::isZoomConnected($userId); } catch (Throwable $e) {}
            }

            $integrationsSummary = "- WhatsApp Cloud API: " . ($wa ? "Connected ({$wa['business_name']})" : "Not Connected") . "\n";
            $integrationsSummary .= "- Business Email: " . ($smtp ? "Connected (SMTP: {$smtp['sender_email']})" : ($hasGmail ? "Connected (Gmail OAuth)" : "Not Connected")) . "\n";
            $integrationsSummary .= "- Google Calendar: " . ($hasGoogleCal ? "Connected" : "Not Connected") . "\n";
            $integrationsSummary .= "- Zoom Meetings: " . ($hasZoom ? "Connected" : "Not Connected");
        } catch (Throwable $e) {}

        // Long-term user memories
        $stmtMem = $db->prepare("SELECT key_name, value_text FROM ai_agent_memory WHERE user_id = ? LIMIT 20");
        $stmtMem->execute([$userId]);
        $memories = $stmtMem->fetchAll(PDO::FETCH_ASSOC);
        $memText = "";
        if (!empty($memories)) {
            $memText = "### STORED BUSINESS PREFERENCES & MEMORY:\n";
            foreach ($memories as $m) {
                $memText .= "- {$m['key_name']}: {$m['value_text']}\n";
            }
        }

        $agentName = class_exists('AgentRouter') ? AgentRouter::getAgentName($userId, $db) : 'Muse';
        $userTz = class_exists('AgentRouter') ? AgentRouter::getUserTimezone($userId, $db) : 'Asia/Kolkata';

        try {
            $tzObj = new DateTimeZone($userTz);
            $now = (new DateTime('now', $tzObj))->format('l, F j, Y, g:i A') . " ({$userTz})";
        } catch (Throwable $e) {
            $now = date('l, F j, Y, g:i A');
        }

        return "You are {$agentName}, the LinkPilot AI Agent: The Unified Autonomous Business Assistant & Intelligence Layer for LinkPilot.
You have direct, real-time access to control, manage, and execute operations across this LinkPilot workspace.

### OPERATIONAL IDENTITY & CONTEXT:
- Your Name: {$agentName} (Always introduce and address yourself as {$agentName})
- Current Time: {$now}
- User Timezone: {$userTz}
- User: {$userName} ({$companyName})
- Active Integrations:
{$integrationsSummary}
{$memText}

### STRICT DATE & TIME RULE:
- For any questions regarding today's date, current time, or day of the week, answer directly from your Current Time context above. NEVER call web_search or any tool for date or time queries.

### CASUAL GREETINGS:
- When the user sends a greeting (e.g. 'hi', 'hello', 'hey', 'good morning', 'who are you', etc.) or casual comment:
  1. Reply naturally, warmly, and concisely (1 to 2 sentences max).
  2. Answer ONLY what was asked without unnecessary rambling or dumping tool menus unprompted.
  3. Offer 2 to 3 short, relevant recommendations or things you can do for them today as bullet points.

### REAL-TIME WEB SEARCH & LIVE DATA:
- You have real-time access to the live internet through your tool library:
  1. Real-Time Weather: Whenever asked about the weather, temperature, or forecast in any city or location, ALWAYS call get_live_weather(location: '...'). Never guess or say you lack a sensor — you have the live weather tool!
  2. Live Web & Google Search: Whenever the user asks for external information, online competitor research, or current market news, ALWAYS call web_search(query: '...'). Do NOT search for current date or time.
  3. Website Content Scraper: Whenever the user provides a URL (e.g. https://...) or asks to extract, analyze, or summarize a webpage, ALWAYS call fetch_webpage_content(url: '...').
- Answer the user directly, accurately, and informatively with the real-time data retrieved.

### CAPABILITIES OVERVIEW:
You are not a passive text generator. You execute real actions using your tool library:
1. CRM: Search, retrieve, create, and update contacts, leads, notes, and tags.
2. Deals: Create, track, move stages, and evaluate at-risk deals in the sales pipeline.
3. Tasks: Schedule, assign, update, and complete tasks with priority and calendar sync.
4. WhatsApp: Read synchronized chats, detect unreplied customer conversations, send approved messages, and inspect campaigns.
5. Email: Search synchronized emails, read threads, draft responses, and send approved emails.
6. Automation: Monitor and toggle active trigger-action workflows.
7. Calendar & Meetings Plugin:
   - When asked to generate a Google Meet link, room link, or video call link, ALWAYS invoke generate_instant_meet(title: '...'). Do NOT halt or ask for date/time if not provided; generate the room immediately. NEVER call web_search to find a Google Meet link.
   - For scheduled meetings with an explicit future date/time, call create_meeting.
8. Analytics: Generate daily executive briefings, pipeline revenue summaries, and conversion KPI reports.
9. Web & Research: Live weather lookups, Google/web search, and direct webpage content extraction. (Do NOT use web search for Google Meet links or internal CRM actions).
10. Temporary File Generation & Delivery:
   - When the user asks to generate, export, format, or create a file (Excel, CSV, PDF, Word, TXT, JSON), you MUST ALWAYS call the tool `generate_export_file`.
   - You DO NOT have a Python sandbox, shell environment, or local filesystem. NEVER invent fake paths like `sandbox:/tmp/...`, `/tmp/...`, `file://...`, or fake URLs.
   - You can pass rows from a previous step, or set `source: 'contacts'` or `source: 'deals'` in `generate_export_file` and the server will automatically pull and export all CRM records!
   - In your final response, display ONLY the real `download_url` provided by `generate_export_file` as: `[Download <filename>](<download_url>)`.
   - Directly below the link, always include:
     *This download link is valid for 30 minutes. The file will be automatically deleted after expiry.*

### STRICT EXECUTION & SECURITY RULES:
1. AUTONOMOUS READ: All READ operations (searching contacts, reading WhatsApp chats, checking deals, analytics, etc.) execute automatically without asking.
2. APPROVAL REQUIRED FOR EXTERNAL/DESTRUCTIVE:
   - When asked to perform an external communication (sending an email, sending a WhatsApp message) or a destructive action (archiving a contact, deleting a deal), invoke the tool with the proposed payload. The system will prompt the user with an interactive Approval Card showing full details.
   - When calling send_email: Always craft a polished, modern, responsive HTML email body template with clean inline styles (e.g. elegant card container, branded accents, clear typography, highlight box for amounts or key items, and professional signoff). The user will be provided with an interactive HTML View and Edit option before dispatching.
3. FACTUAL DATA INTEGRITY:
   - Always report actual data returned by tools. Never invent messages or pretend a customer replied if the tool returned zero messages.
4. MULTI-STEP CHAINING:
   - If a user asks for a compound action (e.g., 'Find all customers who haven't responded on WhatsApp in 7 days and create follow-up tasks for them'), execute the query tool first, examine the results, and then create the corresponding tasks.
5. PROACTIVE RECOMMENDATIONS:
   - At the end of every helpful response, provide 1 to 3 relevant, actionable next steps based on the actual data.
6. FORMATTING:
   - Format responses using clean GitHub Markdown. Use bullet lists, bold highlights, and markdown tables for tabular data. Keep explanations professional, crisp, and executive-ready.";
    }

    /**
     * Executes the conversational chat loop with smart routing, tool calling, and streaming.
     */
    public static function processChat($userId, $convId, $userMessage, $options = [], ?callable $streamEmitter = null, ?PDO $db = null) {
        $processStartTime = microtime(true);
        if (!$db) $db = Database::getConnection();
        self::init($db);

        $userMessage = trim($userMessage);
        if (empty($userMessage)) throw new Exception("Message cannot be empty.");

        // Record User Message
        $stmtUserMsg = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, user_id, role, content) VALUES (?, ?, 'user', ?)");
        $stmtUserMsg->execute([$convId, $userId, $userMessage]);
        $userMsgId = $db->lastInsertId();

        // Check if conversation title needs auto-naming
        $stmtTitleCheck = $db->prepare("SELECT title FROM ai_agent_conversations WHERE id = ? AND user_id = ?");
        $stmtTitleCheck->execute([$convId, $userId]);
        $currentTitle = $stmtTitleCheck->fetchColumn();

        if ($currentTitle === 'New Conversation' || empty($currentTitle)) {
            $autoTitle = mb_substr(preg_replace('/[^\w\s-]/u', '', $userMessage), 0, 40);
            if (empty($autoTitle)) $autoTitle = 'Business Query';
            $autoTitle = ucfirst(trim($autoTitle));
            self::renameConversation($userId, $convId, $autoTitle, $db);
        }

        // =========================================================================
        // 0. CONVERSATIONAL APPROVAL & REJECTION INTERCEPTOR
        // Detects if the user replied with "send that mail", "done send that", "yes",
        // "approve", "cancel", etc., to an action waiting for approval without
        // clicking the button, executes it, and emits the resolved UI state!
        // =========================================================================
        if (empty($options['approved_tool_id'])) {
            $stmtPending = $db->prepare("SELECT id, tool_calls_json, tool_results_json FROM ai_agent_messages WHERE conversation_id = ? AND user_id = ? AND status = 'waiting_approval' ORDER BY id DESC LIMIT 1");
            $stmtPending->execute([$convId, $userId]);
            $pendingRow = $stmtPending->fetch(PDO::FETCH_ASSOC);

            if ($pendingRow) {
                $intent = self::checkConversationalApprovalIntent($userMessage);
                if ($intent === 'approved') {
                    return self::handleConversationalApproval($pendingRow, $userMessage, $userId, $convId, $streamEmitter, $db);
                } elseif ($intent === 'rejected') {
                    return self::handleConversationalRejection($pendingRow, $userMessage, $userId, $convId, $streamEmitter, $db);
                }
            }
        }

        // =========================================================================
        // 1. SMART REQUEST ROUTER CLASSIFICATION
        // =========================================================================
        $classification = AgentRouter::classify($userMessage, $userId, $db);

        // Path 1: Instant Response Engine (Greetings, Date/Time, Acknowledgements, Capabilities)
        // Bypasses LLM completely for 0 API costs, 0 token usage, < 5ms response time!
        if ($classification['path'] === AgentRouter::PATH_INSTANT) {
            return AgentRouter::handleInstantResponse($classification, $userMessage, $userId, $convId, $streamEmitter, $db);
        }

        // Fetch existing rolling summary of conversation
        $stmtSummary = $db->prepare("SELECT summary FROM ai_agent_conversations WHERE id = ? AND user_id = ?");
        $stmtSummary->execute([$convId, $userId]);
        $existingSummary = $stmtSummary->fetchColumn();

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => self::buildSystemPrompt($userId, $db)];

        // If conversation has an established rolling summary, inject it into system memory
        if (!empty($existingSummary)) {
            $messages[] = [
                'role' => 'system',
                'content' => "### PERSISTENT CONVERSATION MEMORY (ROLLING SUMMARY):\nThe following key facts, client names, contact details, deal sizes, and agreements were established earlier in this conversation:\n" . $existingSummary . "\n(Use this summary for complete memory recall, while keeping recent responses snappy and contextual.)"
            ];
        }

        // Fetch sliding window of recent conversation history (last 6 messages for fast token processing)
        $stmtHist = $db->prepare("SELECT role, content FROM ai_agent_messages WHERE conversation_id = ? AND user_id = ? AND id < ? ORDER BY id DESC LIMIT 6");
        $stmtHist->execute([$convId, $userId, $userMsgId]);
        $historyRows = array_reverse($stmtHist->fetchAll(PDO::FETCH_ASSOC));

        // =========================================================================
        // 1b. MULTI-TURN WORKFLOW & CONTEXT RESOLUTION (Phase 2 & Phase 3)
        // Resolves pronouns ('it', 'that', 'send him') and entities ('meet_link', 'file_url')
        // =========================================================================
        if (class_exists('ContextManager')) {
            $contextResolution = ContextManager::resolveContext($userId, $convId, $userMessage, $historyRows, $db);
            $activeWorkflow = $contextResolution['active_workflow'];
            $retainedPluginIds = $contextResolution['retained_plugin_ids'];

            if (!empty($contextResolution['prompt_context'])) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $contextResolution['prompt_context']
                ];
            }
        } else {
            $contextResolution = [
                'resolved_message' => $userMessage,
                'prompt_context' => '',
                'active_workflow' => null,
                'retained_plugin_ids' => []
            ];
            $activeWorkflow = null;
            $retainedPluginIds = [];
        }

        foreach ($historyRows as $row) {
            $role = $row['role'];
            $content = $row['content'];
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // =========================================================================
        // 1c. STRUCTURED TASK PLANNING & TWO-TIER EXECUTION ENGINE (Phase 4)
        // Synthesizes compound business requests into explicit DAG task graphs
        // and executes with safe dependencies, bounded retries, and verification.
        // =========================================================================
        if (class_exists('TaskPlanner') && class_exists('ExecutionEngine') && empty($options['approved_tool_id'])) {
            $dagPlan = TaskPlanner::createPlan($userMessage, $classification, $contextResolution, $userId, $convId, $db);
            if (count($dagPlan->getNodes()) > 0) {
                $workflowId = $activeWorkflow ? $activeWorkflow['id'] : null;
                $dagExecResult = ExecutionEngine::executeDAG($dagPlan, $userId, $convId, $workflowId, $streamEmitter, $db, $options);

                // If paused at Maker-Checker approval gate
                if (!empty($dagExecResult['approval_required'])) {
                    $appr = $dagExecResult['approval_required'];
                    $toolCallsJson = json_encode([[
                        'id' => $appr['approval_token'],
                        'name' => $appr['tool_id'],
                        'arguments' => $appr['arguments']
                    ]]);
                    $toolResultsJson = json_encode([
                        'status' => 'waiting_approval',
                        'workflow_id' => $dagExecResult['workflow_id'],
                        'task_key' => $appr['task_key'],
                        'tool_id' => $appr['tool_id'],
                        'tool_name' => $appr['tool_name'],
                        'arguments' => $appr['arguments'],
                        'approval_token' => $appr['approval_token']
                    ]);
                    $approvalPromptText = "I have prepared the planned action **{$appr['tool_name']}** for your review.\n\nPlease approve or reject this action to continue.";

                    $stmtApprMsg = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, user_id, role, content, tool_calls_json, tool_results_json, status, tokens_used) VALUES (?, ?, 'assistant', ?, ?, ?, 'waiting_approval', 0)");
                    $stmtApprMsg->execute([$convId, $userId, $approvalPromptText, $toolCallsJson, $toolResultsJson]);
                    $apprMsgId = $db->lastInsertId();

                    if ($streamEmitter) {
                        $streamEmitter('approval_required', [
                            'message_id' => $apprMsgId,
                            'workflow_id' => $dagExecResult['workflow_id'],
                            'task_key' => $appr['task_key'],
                            'tool_id' => $appr['tool_id'],
                            'tool_name' => $appr['tool_name'],
                            'arguments' => $appr['arguments'],
                            'approval_token' => $appr['approval_token']
                        ]);
                        $streamEmitter('done', ['message_id' => $apprMsgId, 'status' => 'waiting_approval']);
                    }

                    return [
                        'status' => 'waiting_approval',
                        'message_id' => $apprMsgId,
                        'content' => $approvalPromptText,
                        'workflow_id' => $dagExecResult['workflow_id'],
                        'approval_payload' => $appr,
                        'dag' => $dagExecResult['dag']
                    ];
                }

                $assistantContent = self::synthesizeDAGResponse($dagPlan, $dagExecResult);

                if ($streamEmitter) {
                    $streamEmitter('token', ['content' => $assistantContent]);
                    $streamEmitter('proactive_suggestions', [
                        'suggestions' => ['View execution logs', 'Schedule next action', 'Export CRM report']
                    ]);
                }

                $hasFailures = $dagPlan->hasFailures();
                $statusStr = $hasFailures ? 'failed' : 'completed';
                $stmtAssist = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, user_id, role, content, tool_calls_json, tool_results_json, status, tokens_used) VALUES (?, ?, 'assistant', ?, ?, ?, ?, 0)");
                $stmtAssist->execute([
                    $convId,
                    $userId,
                    $assistantContent,
                    json_encode(array_values($dagPlan->getNodes())),
                    json_encode($dagExecResult['entities']),
                    $statusStr
                ]);
                $finalMsgId = $db->lastInsertId();

                $nowTime = date('Y-m-d H:i:s');
                $db->prepare("UPDATE ai_agent_conversations SET updated_at = ? WHERE id = ?")->execute([$nowTime, $convId]);

                if ($streamEmitter) {
                    $streamEmitter('done', ['message_id' => $finalMsgId, 'status' => $statusStr]);
                }

                return [
                    'status' => 'success',
                    'message_id' => $finalMsgId,
                    'content' => $assistantContent,
                    'workflow_id' => $dagExecResult['workflow_id'],
                    'dag' => $dagExecResult['dag'],
                    'entities' => $dagExecResult['entities']
                ];
            }
        }

        // =========================================================================
        // 2. DYNAMIC TOOL DISCOVERY RETAINING ACTIVE WORKFLOW TOOLS
        // Discovers tools dynamically while preserving tools needed for active workflows
        // =========================================================================
        if (class_exists('PluginRegistry')) {
            $availableTools = PluginRegistry::getToolsForContext($userMessage, $classification, $retainedPluginIds, $historyRows);
        } else {
            $availableTools = [];
        }
        if (empty($availableTools)) {
            $availableTools = AIToolRegistry::getToolsForMessage($userMessage, $classification, $historyRows);
        }
        $toolExecutionSteps = [];
        $maxLoops = 6;
        $loopCount = 0;
        $finalAssistantText = "";
        $approvalRequiredPayload = null;
        $activeProvider = 'none';
        $activeModel = 'none';
        $totalAiLatency = 0;
        $totalTokensUsed = 0;

        while ($loopCount < $maxLoops) {
            $loopCount++;

            // Intelligent Model Routing: Call AI with model selected based on request complexity
            $aiResponse = AgentAIClient::callChat($messages, $availableTools, $userId, 1000, $classification['path']);
            $content = trim($aiResponse['content'] ?? '');
            $toolCalls = $aiResponse['tool_calls'] ?? [];
            $activeProvider = $aiResponse['provider'] ?? $activeProvider;
            $activeModel = $aiResponse['model'] ?? $activeModel;
            $activeFallbackFrom = $aiResponse['fallback_from'] ?? null;
            $activeFallbackReason = $aiResponse['fallback_reason'] ?? null;
            $totalAiLatency += (int)($aiResponse['latency_ms'] ?? 0);
            $totalTokensUsed += (int)($aiResponse['tokens_used'] ?? 0);

            if (empty($toolCalls)) {
                $finalAssistantText = $content;
                break;
            }

            // =========================================================================
            // 3. PARALLEL & SEQUENTIAL TOOL EXECUTION DISPATCHER
            // =========================================================================
            $hasApprovalPause = false;

            // Check if any tool in batch requires approval
            foreach ($toolCalls as $tc) {
                $toolDef = AIToolRegistry::getTool($tc['name']);
                if ($toolDef && ($toolDef['approval_required'] ?? false) && empty($options['approved_tool_id'])) {
                    $approvalRequiredPayload = [
                        'tool_id' => $tc['name'],
                        'tool_name' => $toolDef['name'],
                        'description' => $toolDef['description'],
                        'risk_level' => $toolDef['risk_level'],
                        'arguments' => $tc['arguments'],
                        'approval_token' => 'appr_' . bin2hex(random_bytes(8))
                    ];

                    if ($streamEmitter) {
                        $streamEmitter('approval_required', $approvalRequiredPayload);
                    }

                    // Transition workflow state machine to waiting_for_approval
                    if ($activeWorkflow) {
                        try {
                            WorkflowEngine::transitionState($activeWorkflow['id'], WorkflowEngine::STATE_WAITING_FOR_APPROVAL, $tc['name'], $tc['arguments'], 'Approval required', $db);
                        } catch (Throwable $eWf) {}
                    } else {
                        try {
                            $newWf = WorkflowEngine::createWorkflow($userId, $convId, $toolDef['plugin_id'] ?? 'crm_tasks_plugin', $tc['name'], 'User approval required for ' . $toolDef['name'], $tc['arguments'], null, $db);
                            WorkflowEngine::transitionState($newWf['id'], WorkflowEngine::STATE_WAITING_FOR_APPROVAL, $tc['name'], $tc['arguments'], 'Approval required', $db);
                            $activeWorkflow = $newWf;
                        } catch (Throwable $eWf) {}
                    }

                    $stmtAudit = $db->prepare("INSERT INTO ai_agent_audit_logs (user_id, conversation_id, tool_id, tool_name, risk_level, input_json, approval_status) VALUES (?, ?, ?, ?, ?, ?, 'rejected')");
                    $stmtAudit->execute([$userId, $convId, $tc['name'], $toolDef['name'], $toolDef['risk_level'], json_encode($tc['arguments'])]);

                    $hasApprovalPause = true;
                    $finalAssistantText = "I have prepared the action **" . htmlspecialchars($toolDef['name']) . "** for you. Because this performs an external communication or significant change, please review the preview details below and confirm to execute.";
                    break;
                }
            }

            if ($hasApprovalPause) {
                break;
            }

            // Execute independent tools concurrently/sequentially
            foreach ($toolCalls as $tc) {
                $toolId = $tc['name'];
                $toolArgs = $tc['arguments'];
                $toolDef = AIToolRegistry::getTool($toolId);

                if (!$toolDef) {
                    $toolResult = ["error" => "Tool '{$toolId}' is not recognized in LinkPilot registry."];
                    $stepStatus = 'failed';
                } else {
                    $isApprovalReq = $toolDef['approval_required'] ?? false;
                    $stepId = ($tc['id'] ?? '') ?: ($toolId . '_' . bin2hex(random_bytes(4)));

                    if ($streamEmitter) {
                        $streamEmitter('tool_start', [
                            'step_id' => $stepId,
                            'tool_id' => $toolId,
                            'tool_name' => $toolDef['name'],
                            'arguments' => $toolArgs
                        ]);
                    }

                    if ($activeWorkflow) {
                        try {
                            WorkflowEngine::transitionState($activeWorkflow['id'], WorkflowEngine::STATE_RUNNING, $toolId, $toolArgs, 'Executing ' . $toolId, $db);
                        } catch (Throwable $eWf) {}
                    }

                    try {
                        $toolResult = AIToolRegistry::executeTool($toolId, $userId, $toolArgs, $db);
                        $stepStatus = 'completed';
                    } catch (Throwable $e) {
                        $toolResult = ["error" => $e->getMessage()];
                        $stepStatus = 'failed';
                    }

                    // Log audit trail
                    try {
                        $stmtAudit = $db->prepare("INSERT INTO ai_agent_audit_logs (user_id, conversation_id, tool_id, tool_name, risk_level, input_json, output_json, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmtAudit->execute([
                            $userId,
                            $convId,
                            $toolId,
                            $toolDef['name'],
                            $toolDef['risk_level'],
                            json_encode($toolArgs),
                            json_encode($toolResult),
                            $isApprovalReq ? 'approved_by_user' : 'auto_approved'
                        ]);
                    } catch (Throwable $e) {}

                    // Extract and accumulate entities in workflow
                    $extractedEntities = [];
                    if (!empty($toolResult['meet_link'])) {
                        $extractedEntities['meet_link'] = $toolResult['meet_link'];
                    }
                    if (!empty($toolResult['download_url'])) {
                        $extractedEntities['file_url'] = $toolResult['download_url'];
                        $extractedEntities['filename'] = $toolArgs['filename'] ?? 'file';
                    }
                    if (!empty($toolResult['contact']['id'])) {
                        $extractedEntities['contact_id'] = $toolResult['contact']['id'];
                        $extractedEntities['contact_name'] = $toolResult['contact']['name'] ?? '';
                        $extractedEntities['contact_email'] = $toolResult['contact']['email'] ?? '';
                    }

                    if (!empty($extractedEntities)) {
                        if ($activeWorkflow) {
                            WorkflowEngine::updateEntities($activeWorkflow['id'], $extractedEntities, $db);
                        } else {
                            try {
                                $pluginId = $toolDef['plugin_id'] ?? 'crm_contacts_plugin';
                                $newWf = WorkflowEngine::createWorkflow($userId, $convId, $pluginId, $toolId, "Workflow for {$toolId}", $extractedEntities, null, $db);
                                WorkflowEngine::transitionState($newWf['id'], WorkflowEngine::STATE_COMPLETED, $toolId, $extractedEntities, 'Completed', $db);
                                $activeWorkflow = $newWf;
                            } catch (Throwable $eWf) {}
                        }
                    }

                    $toolExecutionSteps[] = [
                        'step_id' => $stepId,
                        'tool_id' => $toolId,
                        'tool_name' => $toolDef['name'],
                        'status' => $stepStatus,
                        'input' => $toolArgs,
                        'output' => $toolResult
                    ];

                    if ($streamEmitter) {
                        $streamEmitter('tool_complete', [
                            'step_id' => $stepId,
                            'tool_id' => $toolId,
                            'tool_name' => $toolDef['name'],
                            'status' => $stepStatus,
                            'result' => $toolResult
                        ]);
                    }
                }

                // Add tool result to conversational history for next LLM turn
                $messages[] = [
                    'role' => 'assistant',
                    'content' => "Calling tool {$toolId} with " . json_encode($toolArgs)
                ];
                $messages[] = [
                    'role' => 'user',
                    'content' => "Tool {$toolId} output: " . json_encode($toolResult)
                ];
            }
        }

        // Transition running workflow to completed if all tool executions finished without pause
        if ($activeWorkflow && class_exists('WorkflowEngine') && ($activeWorkflow['state'] ?? '') === WorkflowEngine::STATE_RUNNING && empty($approvalRequiredPayload)) {
            try {
                WorkflowEngine::transitionState($activeWorkflow['id'], WorkflowEngine::STATE_COMPLETED, null, [], 'Workflow turn finished', $db);
            } catch (Throwable $eWf) {}
        }

        // If no text was produced yet, generate final synthesis without re-passing tool schemas
        if (empty($finalAssistantText) && empty($approvalRequiredPayload)) {
            try {
                $finalAi = AgentAIClient::callChat($messages, [], $userId, 1000, $classification['path']);
                $finalAssistantText = trim($finalAi['content'] ?? '');
                $totalAiLatency += (int)($finalAi['latency_ms'] ?? 0);
                $totalTokensUsed += (int)($finalAi['tokens_used'] ?? 0);
            } catch (Throwable $synthesisEx) {
                error_log("Synthesis LLM warning: " . $synthesisEx->getMessage());
            }
        }

        // GUARANTEED REPLY GUARD: Never return empty text or leave user with no reply
        if (empty($finalAssistantText) && empty($approvalRequiredPayload)) {
            if (!empty($toolExecutionSteps)) {
                $synthesizedParts = [];
                foreach ($toolExecutionSteps as $step) {
                    $toolName = $step['tool_name'] ?? $step['tool_id'];
                    $output = $step['output'] ?? [];
                    if (isset($output['error'])) {
                        $synthesizedParts[] = "⚠️ **{$toolName}**: " . $output['error'];
                    } elseif (isset($output['message'])) {
                        $synthesizedParts[] = "✅ **{$toolName}**: " . $output['message'];
                    } elseif (isset($output['total']) && isset($output['contacts'])) {
                        $synthesizedParts[] = "✅ Found **" . intval($output['total']) . " contacts** in your CRM.";
                    } elseif (isset($output['total_deals'])) {
                        $synthesizedParts[] = "✅ Retrieved **" . intval($output['total_deals']) . " deals** from sales pipeline.";
                    } elseif (isset($output['tasks'])) {
                        $synthesizedParts[] = "✅ Retrieved **" . count($output['tasks']) . " tasks**.";
                    } elseif (isset($output['formatted_summary'])) {
                        $synthesizedParts[] = "🌤️ " . $output['formatted_summary'];
                    } elseif (isset($output['results']) && isset($output['query'])) {
                        $count = count($output['results']);
                        $synthesizedParts[] = "🔍 Found **{$count} web search results** for *\"" . htmlspecialchars($output['query']) . "\"*.";
                    } elseif (isset($output['extracted_content'])) {
                        $title = !empty($output['title']) ? $output['title'] : ($output['url'] ?? 'Webpage');
                        $synthesizedParts[] = "🌐 Successfully extracted content from **{$title}**.";
                    } else {
                        $synthesizedParts[] = "✅ **{$toolName}** executed successfully.";
                    }
                }
                $finalAssistantText = implode("\n\n", $synthesizedParts) . "\n\nWhat would you like me to do next with this information?";
            } else {
                $finalAssistantText = "I am connected to your LinkPilot workspace. I can search contacts, manage deals, inspect WhatsApp leads, or generate executive briefings.";
            }
        }

        // Self-Healing Interceptor: Check for hallucinated sandbox or /tmp file links or missing file creation!
        $hasFakeSandboxLink = (bool)preg_match('/\[([^\]]+)\]\(((?:sandbox:|\/tmp\/|file:)[^\)]+)\)/i', $finalAssistantText, $fakeMatch);
        $hasBareSandboxUrl = (bool)preg_match('/(?:sandbox:|\/tmp\/)([a-zA-Z0-9_\-\.\/]+)/i', $finalAssistantText, $bareMatch);
        $userAskedForFile = (bool)preg_match('/\b(create|make|generate|export|download|save|put)\b.+\b(file|excel|excle|exel|csv|pdf|word|docx?|sheet|spreadsheet)\b/i', $userMessage);

        $alreadyGeneratedFile = false;
        foreach ($toolExecutionSteps as $step) {
            if (($step['tool_id'] ?? '') === 'generate_export_file' && ($step['status'] ?? '') === 'completed') {
                $alreadyGeneratedFile = true;
                break;
            }
        }

        if (!$alreadyGeneratedFile && ($hasFakeSandboxLink || $hasBareSandboxUrl || ($userAskedForFile && !str_contains($finalAssistantText, 'download_temp_file.php')))) {
            $ext = 'xlsx';
            $detectStr = $finalAssistantText . ' ' . $userMessage;
            if (preg_match('/\.(xlsx|csv|pdf|docx|txt|json)/i', $detectStr, $extM)) {
                $ext = strtolower($extM[1]);
            } elseif (preg_match('/\b(csv)\b/i', $detectStr)) {
                $ext = 'csv';
            } elseif (preg_match('/\b(pdf)\b/i', $detectStr)) {
                $ext = 'pdf';
            } elseif (preg_match('/\b(word|docx?)\b/i', $detectStr)) {
                $ext = 'docx';
            }

            $genFilename = 'all_contacts.' . $ext;
            if (!empty($fakeMatch[1]) && preg_match('/([a-zA-Z0-9_\-]+\.(?:xlsx|csv|pdf|docx|txt|json))/i', $fakeMatch[1], $fnM)) {
                $genFilename = $fnM[1];
            } elseif (!empty($bareMatch[1]) && preg_match('/([a-zA-Z0-9_\-]+\.(?:xlsx|csv|pdf|docx|txt|json))/i', $bareMatch[1], $fnM)) {
                $genFilename = $fnM[1];
            }

            try {
                require_once __DIR__ . '/temp_file_service.php';
                $realFileRes = TempFileService::createTempFile(
                    $userId,
                    $convId,
                    $ext,
                    $genFilename,
                    'LinkPilot Contacts & CRM Export',
                    [], // will auto-populate from CRM
                    '',
                    'Automated file delivery',
                    $db
                );

                if (!empty($realFileRes['download_url'])) {
                    $realUrl = $realFileRes['download_url'];
                    $cleanNotice = "\n\n*This download link is valid for 30 minutes. The file will be automatically deleted after expiry.*";

                    if (!empty($fakeMatch[0])) {
                        $finalAssistantText = str_replace($fakeMatch[0], "[Download {$genFilename}]({$realUrl})", $finalAssistantText);
                    } elseif (!empty($bareMatch[0])) {
                        $finalAssistantText = str_replace($bareMatch[0], $realUrl, $finalAssistantText);
                    } else {
                        $finalAssistantText .= "\n\n[Download {$genFilename}]({$realUrl})";
                    }

                    if (!str_contains($finalAssistantText, '30 minutes')) {
                        $finalAssistantText .= $cleanNotice;
                    }

                    $toolExecutionSteps[] = [
                        'step_id' => 'generate_export_file_' . bin2hex(random_bytes(4)),
                        'tool_id' => 'generate_export_file',
                        'tool_name' => 'Generate Temporary File',
                        'status' => 'completed',
                        'input' => ['format' => $ext, 'filename' => $genFilename],
                        'output' => $realFileRes
                    ];
                }
            } catch (Throwable $e) {}
        }

        // Extract proactive recommendations
        $proactiveSuggestions = self::generateProactiveSuggestions($finalAssistantText, $toolExecutionSteps, $userMessage);

        // Ensure database connection is alive after AI call
        try {
            @$db->query("SELECT 1");
        } catch (Throwable $e) {
            $db = Database::getConnection(true);
        }

        // Save Assistant Message with workflow association
        $wfId = $activeWorkflow ? ($activeWorkflow['id'] ?? null) : null;
        $status = $approvalRequiredPayload ? 'waiting_approval' : 'completed';
        try {
            $stmtAssistant = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, workflow_id, user_id, role, content, tool_calls_json, tool_results_json, status, tokens_used) VALUES (?, ?, ?, 'assistant', ?, ?, ?, ?, ?)");
            $stmtAssistant->execute([
                $convId,
                $wfId,
                $userId,
                $finalAssistantText,
                json_encode($toolExecutionSteps),
                json_encode($approvalRequiredPayload ?: []),
                $status,
                $totalTokensUsed
            ]);
        } catch (Throwable $eMsg) {
            $stmtAssistant = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, user_id, role, content, tool_calls_json, tool_results_json, status, tokens_used) VALUES (?, ?, 'assistant', ?, ?, ?, ?, ?)");
            $stmtAssistant->execute([
                $convId,
                $userId,
                $finalAssistantText,
                json_encode($toolExecutionSteps),
                json_encode($approvalRequiredPayload ?: []),
                $status,
                $totalTokensUsed
            ]);
        }
        $assistantMsgId = $db->lastInsertId();

        // Update conversation timestamp
        $db->prepare("UPDATE ai_agent_conversations SET updated_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $convId]);

        // Background Rolling Summary: update memory periodically so no facts are ever lost
        self::maybeUpdateRollingSummary($userId, $convId, $existingSummary, $userMessage, $finalAssistantText, $db);

        $totalResponseTimeMs = (int)round((microtime(true) - $processStartTime) * 1000);

        // Record Performance Telemetry
        AgentTelemetry::logRequest(
            $userId,
            $convId,
            $userMessage,
            $classification['sub_type'] ?? 'unknown',
            $classification['path'],
            $activeProvider,
            $activeModel,
            array_map(function($t) { return $t['function']['name']; }, $availableTools),
            array_map(function($s) { return $s['tool_id']; }, $toolExecutionSteps),
            $totalAiLatency,
            0,
            $totalResponseTimeMs,
            $totalTokensUsed,
            $db,
            $activeFallbackFrom ?? null,
            $activeFallbackReason ?? null
        );

        $routingMeta = [
            'path' => $classification['path'],
            'sub_type' => $classification['sub_type'] ?? '',
            'provider' => $activeProvider,
            'model' => $activeModel,
            'fallback_from' => $activeFallbackFrom ?? null,
            'fallback_reason' => $activeFallbackReason ?? null,
            'tools_loaded_count' => count($availableTools),
            'execution_time_ms' => $totalResponseTimeMs
        ];

        if ($streamEmitter) {
            $streamEmitter('token', ['content' => $finalAssistantText]);
            $streamEmitter('proactive_suggestions', ['suggestions' => $proactiveSuggestions]);
            $streamEmitter('done', [
                'conversation_id' => $convId,
                'message_id' => $assistantMsgId,
                'status' => $status,
                'approval_required' => $approvalRequiredPayload,
                'routing' => $routingMeta
            ]);
        }

        return [
            'conversation_id' => $convId,
            'message_id' => (int)$assistantMsgId,
            'role' => 'assistant',
            'content' => $finalAssistantText,
            'tool_steps' => $toolExecutionSteps,
            'approval_required' => $approvalRequiredPayload,
            'proactive_suggestions' => $proactiveSuggestions,
            'status' => $status,
            'routing' => $routingMeta
        ];
    }

    /**
     * Incrementally updates rolling conversation summary in the background.
     * Retains key business facts (names, numbers, deals, dates, pending items) while keeping token size small.
     */
    public static function maybeUpdateRollingSummary($userId, $convId, $existingSummary, $userMsg, $assistantReply, PDO $db) {
        try {
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM ai_agent_messages WHERE conversation_id = ?");
            $stmtCount->execute([$convId]);
            $count = (int)$stmtCount->fetchColumn();

            // Run once conversation has enough context (>= 4 messages) and periodically or if empty
            if ($count >= 4 && ($count % 3 === 0 || empty($existingSummary))) {
                $summaryPrompt = [
                    [
                        'role' => 'system',
                        'content' => 'You are an executive memory compressor. Maintain a concise rolling summary (3 to 5 bullet points) of key facts from this conversation. Focus on: client/contact names, phone numbers/emails, deal values/stages, agreed decisions, and scheduled tasks. Exclude casual greetings. Return ONLY the bullet points.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Current summary:\n" . ($existingSummary ?: "None yet.") . "\n\nRecent exchange:\nUser: " . mb_substr($userMsg, 0, 400) . "\nAssistant: " . mb_substr($assistantReply, 0, 600) . "\n\nUpdated concise rolling summary:"
                    ]
                ];

                $aiSummary = AgentAIClient::callChat($summaryPrompt, [], $userId, 250);
                $newSummary = trim($aiSummary['content'] ?? '');
                if (!empty($newSummary) && strlen($newSummary) > 10) {
                    $stmtUpdate = $db->prepare("UPDATE ai_agent_conversations SET summary = ? WHERE id = ? AND user_id = ?");
                    $stmtUpdate->execute([$newSummary, $convId, $userId]);
                }
            }
        } catch (Throwable $e) {
            // Non-fatal, summary failure should never interrupt user chat
            error_log("Rolling summary update skipped: " . $e->getMessage());
        }
    }

    /**
     * Synthesizes relevant and contextual next steps based on real LinkPilot capabilities.
     */
    private static function generateProactiveSuggestions($replyText, array $toolSteps, $userPrompt) {
        $suggestions = [];
        $lower = strtolower($replyText . " " . $userPrompt);

        if (str_contains($lower, 'deal') || str_contains($lower, 'pipeline')) {
            $suggestions[] = "Show stalled deals requiring follow-up";
            $suggestions[] = "Move deal to Negotiation stage";
        }
        if (str_contains($lower, 'contact') || str_contains($lower, 'lead')) {
            $suggestions[] = "Create follow-up tasks for these contacts";
            $suggestions[] = "Draft follow-up email";
        }
        if (str_contains($lower, 'whatsapp')) {
            $suggestions[] = "Who sent the latest WhatsApp message?";
            $suggestions[] = "Summarize today's WhatsApp conversations";
        }
        if (str_contains($lower, 'task') || str_contains($lower, 'meeting')) {
            $suggestions[] = "What are my pending tasks for today?";
            $suggestions[] = "Schedule meeting on Google Meet";
        }

        if (empty($suggestions)) {
            $suggestions = [
                "Give me a daily executive briefing",
                "Show my pipeline conversion summary",
                "Check WhatsApp messages needing attention"
            ];
        }

        return array_slice(array_unique($suggestions), 0, 3);
    }

    /**
     * Determines whether a user message represents conversational approval or rejection
     * of a pending action waiting for confirmation.
     *
     * @param string $userMessage
     * @return string|null 'approved', 'rejected', or null
     */
    public static function checkConversationalApprovalIntent($userMessage) {
        $msg = trim($userMessage);
        if (empty($msg)) return null;

        $clean = strtolower(trim(preg_replace('/[^\w\s\']/u', ' ', $msg)));
        $clean = preg_replace('/\s+/', ' ', $clean);

        // 1. Explicit Rejection / Cancellation checks
        if (preg_match('/\b(don\'?t\s+send|do\s+not\s+send|never\s+send|cancel|reject|abort|stop|discard|leave\s+it|never\s*mind)\b/i', $clean)) {
            return 'rejected';
        }
        if (preg_match('/^(no|nope|nah|not\s+now|no\s+thanks|don\'?t)\b/i', $clean) && !preg_match('/\b(no\s+problem|no\s+worries)\b/i', $clean)) {
            return 'rejected';
        }

        // 2. Explicit Approval checks: e.g. "send that mail", "done send that", "send it", "approve", "go ahead", etc.
        if (preg_match('/\b(send\s*(?:that|it|this|the\s+mail|the\s+email|email|mail)?|done\s*send\s*(?:that|it|this)?|approve|approved|confirm|confirmed|proceed|execute|go\s*ahead|dispatch)\b/i', $clean)) {
            // Ensure no negation like "don't send"
            if (!preg_match('/\b(don\'?t|do\s+not|never|shouldn\'?t|cannot|can\'?t|won\'?t|not)\s+(send|approve|proceed|execute)\b/i', $clean)) {
                return 'approved';
            }
        }

        // 3. Affirmative conversational signals when an action is pending:
        if (preg_match('/^(yes|yep|yeah|yup|sure|ok|okay|k|done|fine|perfect|looks\s+good|do\s+it|please\s+do|yes\s+please|yes\s+do\s+it|proceed|confirmed?|approved?|go\s+ahead)$/i', $clean)) {
            return 'approved';
        }

        // 4. Phrases like "looks good send it", "perfect send that", "done send that"
        if (preg_match('/\b(looks\s+good|perfect|all\s+good|great|fine)\b.*\b(send|approve|confirm|proceed)\b/i', $clean)) {
            return 'approved';
        }

        return null;
    }

    /**
     * Executes pending approved action conversationally, updates DB message status,
     * emits SSE events to transform frontend card, and returns synthesized confirmation.
     */
    public static function handleConversationalApproval($pendingRow, $userMessage, $userId, $convId, ?callable $streamEmitter = null, ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();
        $pendingMsgId = (int)$pendingRow['id'];
        $parsedResults = json_decode($pendingRow['tool_results_json'] ?? '{}', true) ?: [];
        $toolId = $parsedResults['tool_id'] ?? '';
        $toolName = $parsedResults['tool_name'] ?? $toolId;
        $toolArgs = $parsedResults['arguments'] ?? [];

        // Check if user specified a replacement recipient in their message
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $userMessage, $mEmail)) {
            $toolArgs['recipient'] = $mEmail[0];
            $toolArgs['to'] = $mEmail[0];
        }

        $isEmail = ($toolId === 'send_email') || (!empty($toolArgs['recipient']) && !empty($toolArgs['subject']));

        // 1. Mark pending message as approved in DB
        $parsedResults['status'] = 'approved';
        $parsedResults['approved_at'] = date('Y-m-d H:i:s');
        $parsedResults['approval_mode'] = 'conversational';
        $parsedResults['arguments'] = $toolArgs;

        $stmtUpd = $db->prepare("UPDATE ai_agent_messages SET status = 'approved', tool_results_json = ? WHERE id = ?");
        $stmtUpd->execute([json_encode($parsedResults), $pendingMsgId]);

        // 2. Update audit log
        try {
            $stmtAudit = $db->prepare("UPDATE ai_agent_audit_logs SET approval_status = 'approved', input_json = ? WHERE conversation_id = ? AND user_id = ? AND tool_id = ? AND approval_status = 'rejected' ORDER BY id DESC LIMIT 1");
            $stmtAudit->execute([json_encode($toolArgs), $convId, $userId, $toolId]);
        } catch (Throwable $eAudit) {}

        // 3. Emit SSE event so UI approval card instantly transforms to checked & dispatched state
        if ($streamEmitter) {
            $streamEmitter('action_resolved', [
                'conversation_id' => $convId,
                'message_id' => $pendingMsgId,
                'status' => 'approved',
                'tool_id' => $toolId,
                'tool_name' => $toolName,
                'is_email' => $isEmail
            ]);
        }

        // 4. Execute the tool or resume workflow task
        $execSuccess = true;
        $toolResult = [];
        $wfTaskResumed = false;
        $pendingResults = json_decode($pendingRow['tool_results_json'] ?? '{}', true) ?: [];
        $pendingWfId = $pendingResults['workflow_id'] ?? null;
        $pendingTaskKey = $pendingResults['task_key'] ?? null;

        if ($pendingWfId && $pendingTaskKey && class_exists('ExecutionEngine')) {
            try {
                $resumeRes = ExecutionEngine::resumeAfterApproval($pendingWfId, $pendingTaskKey, $userId, [], $streamEmitter, $db);
                $execSuccess = ($resumeRes['status'] === 'completed');
                $toolResult = $resumeRes['executed_tasks'][0]['output'] ?? ['status' => 'completed'];
                $wfTaskResumed = true;
            } catch (Throwable $eWf) {
                // fall through to standard execution if resume fails
            }
        }

        if (!$wfTaskResumed) {
            try {
                $toolResult = AIToolRegistry::executeTool($toolId, $userId, $toolArgs, $db);
                if (isset($toolResult['error']) && !empty($toolResult['error'])) {
                    $execSuccess = false;
                }
            } catch (Throwable $eExec) {
                $toolResult = ['error' => $eExec->getMessage()];
                $execSuccess = false;
            }
        }

        // 5. Audit log final result
        try {
            $stmtAudit = $db->prepare("INSERT INTO ai_agent_audit_logs (user_id, conversation_id, tool_id, tool_name, risk_level, input_json, output_json, approval_status) VALUES (?, ?, ?, ?, 'moderate', ?, ?, 'approved_by_user')");
            $stmtAudit->execute([$userId, $convId, $toolId, $toolName, json_encode($toolArgs), json_encode($toolResult)]);
        } catch (Throwable $e) {}

        // 5b. Synchronize Workflow state machine
        try {
            $activeWf = WorkflowEngine::getActiveWorkflow($userId, $convId, $db);
            if ($activeWf) {
                $nextState = $execSuccess ? WorkflowEngine::STATE_COMPLETED : WorkflowEngine::STATE_FAILED;
                WorkflowEngine::transitionState($activeWf['id'], $nextState, $toolId, $toolArgs, 'Conversational approval execution', $db);
            }
        } catch (Throwable $eWf) {}

        // 6. Formulate conversational confirmation response
        if ($isEmail) {
            $recipient = $toolArgs['recipient'] ?? ($toolArgs['to'] ?? 'the recipient');
            $subject = $toolArgs['subject'] ?? '';
            if ($execSuccess) {
                $confirmText = "I have approved and dispatched your email to **{$recipient}** with subject **\"{$subject}\"**.\n\n" .
                               "- **Status**: Dispatched successfully\n" .
                               "- **Recipient**: `{$recipient}`\n" .
                               "- **Subject**: {$subject}\n\n" .
                               "What would you like me to do next?";
            } else {
                $errMsg = $toolResult['error'] ?? 'Execution error';
                $confirmText = "I attempted to send the email to **{$recipient}**, but encountered an error: {$errMsg}\n\nPlease verify your email credentials in **Plugins** or try again.";
            }
        } else {
            if ($execSuccess) {
                $confirmText = "The action **{$toolName}** has been approved and executed successfully.\n\n" .
                               (isset($toolResult['message']) ? $toolResult['message'] : (isset($toolResult['content']) ? $toolResult['content'] : 'Action completed.')) . "\n\n" .
                               "What would you like me to do next?";
            } else {
                $errMsg = $toolResult['error'] ?? 'Execution error';
                $confirmText = "The action **{$toolName}** could not be completed: {$errMsg}";
            }
        }

        // 7. Stream response tokens & completion
        $proactiveSuggestions = ["Check inbox for replies", "Schedule follow-up task", "View CRM pipeline"];
        if ($streamEmitter) {
            $streamEmitter('token', ['content' => $confirmText]);
            $streamEmitter('proactive_suggestions', ['suggestions' => $proactiveSuggestions]);
        }

        // 8. Save Assistant Confirmation Message
        $stmtAssistant = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, user_id, role, content, tool_calls_json, tool_results_json, status, tokens_used) VALUES (?, ?, 'assistant', ?, ?, ?, 'completed', 0)");
        $stmtAssistant->execute([
            $convId,
            $userId,
            $confirmText,
            json_encode([
                [
                    'step_id' => 'conv_appr_' . bin2hex(random_bytes(4)),
                    'tool_id' => $toolId,
                    'tool_name' => $toolName,
                    'status' => $execSuccess ? 'completed' : 'failed',
                    'input' => $toolArgs,
                    'output' => $toolResult
                ]
            ]),
            json_encode([
                'action_resolved' => [
                    'tool_id' => $toolId,
                    'status' => 'approved',
                    'message_id' => $pendingMsgId,
                    'tool_result' => $toolResult
                ]
            ])
        ]);
        $assistantMsgId = $db->lastInsertId();

        $db->prepare("UPDATE ai_agent_conversations SET updated_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $convId]);

        if ($streamEmitter) {
            $streamEmitter('done', [
                'conversation_id' => $convId,
                'message_id' => $assistantMsgId,
                'status' => 'completed',
                'action_resolved' => [
                    'tool_id' => $toolId,
                    'status' => 'approved',
                    'message_id' => $pendingMsgId
                ]
            ]);
        }

        return [
            'conversation_id' => $convId,
            'message_id' => (int)$assistantMsgId,
            'role' => 'assistant',
            'content' => $confirmText,
            'tool_steps' => [
                [
                    'tool_id' => $toolId,
                    'tool_name' => $toolName,
                    'status' => $execSuccess ? 'completed' : 'failed',
                    'input' => $toolArgs,
                    'output' => $toolResult
                ]
            ],
            'status' => 'completed',
            'action_resolved' => [
                'tool_id' => $toolId,
                'status' => 'approved',
                'message_id' => $pendingMsgId,
                'tool_result' => $toolResult
            ]
        ];
    }

    /**
     * Cancels pending action conversationally, updates DB message status,
     * emits SSE event to update frontend card, and returns cancellation confirmation.
     */
    public static function handleConversationalRejection($pendingRow, $userMessage, $userId, $convId, ?callable $streamEmitter = null, ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();
        $pendingMsgId = (int)$pendingRow['id'];
        $parsedResults = json_decode($pendingRow['tool_results_json'] ?? '{}', true) ?: [];
        $toolId = $parsedResults['tool_id'] ?? '';
        $toolName = $parsedResults['tool_name'] ?? $toolId;
        $toolArgs = $parsedResults['arguments'] ?? [];
        $isEmail = ($toolId === 'send_email') || (!empty($toolArgs['recipient']) && !empty($toolArgs['subject']));

        // 1. Mark pending message as rejected in DB
        $parsedResults['status'] = 'rejected';
        $parsedResults['rejected_at'] = date('Y-m-d H:i:s');
        $parsedResults['approval_mode'] = 'conversational_cancellation';

        $stmtUpd = $db->prepare("UPDATE ai_agent_messages SET status = 'rejected', tool_results_json = ? WHERE id = ?");
        $stmtUpd->execute([json_encode($parsedResults), $pendingMsgId]);

        // 2. Emit SSE event to cancel UI card
        if ($streamEmitter) {
            $streamEmitter('action_resolved', [
                'conversation_id' => $convId,
                'message_id' => $pendingMsgId,
                'status' => 'rejected',
                'tool_id' => $toolId,
                'tool_name' => $toolName,
                'is_email' => $isEmail
            ]);
        }

        // 2b. Synchronize Workflow state machine to cancelled
        try {
            $pendingResults = json_decode($pendingRow['tool_results_json'] ?? '{}', true) ?: [];
            $pendingWfId = $pendingResults['workflow_id'] ?? null;
            $pendingTaskKey = $pendingResults['task_key'] ?? null;

            if ($pendingWfId && $pendingTaskKey && class_exists('ExecutionEngine')) {
                try {
                    ExecutionEngine::rejectTask($pendingWfId, $pendingTaskKey, $userId, 'User rejected action conversationally', $streamEmitter, $db);
                } catch (Throwable $eWfTask) {}
            }

            $activeWf = WorkflowEngine::getActiveWorkflow($userId, $convId, $db);
            if ($activeWf) {
                WorkflowEngine::transitionState($activeWf['id'], WorkflowEngine::STATE_CANCELLED, null, [], 'User rejected action conversationally', $db);
            }
        } catch (Throwable $eWf) {}

        $cancelText = $isEmail 
            ? "I have cancelled sending the email. The draft has been discarded.\n\nLet me know if you would like me to adjust the content or assist with anything else!"
            : "I have cancelled the action **{$toolName}** as requested.\n\nWhat would you like to work on next?";

        if ($streamEmitter) {
            $streamEmitter('token', ['content' => $cancelText]);
        }

        // 3. Save Assistant Message
        $stmtAssistant = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, user_id, role, content, tool_calls_json, tool_results_json, status, tokens_used) VALUES (?, ?, 'assistant', ?, '[]', ?, 'completed', 0)");
        $stmtAssistant->execute([
            $convId,
            $userId,
            $cancelText,
            json_encode([
                'action_resolved' => [
                    'tool_id' => $toolId,
                    'status' => 'rejected',
                    'message_id' => $pendingMsgId
                ]
            ])
        ]);
        $assistantMsgId = $db->lastInsertId();

        $db->prepare("UPDATE ai_agent_conversations SET updated_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $convId]);

        if ($streamEmitter) {
            $streamEmitter('done', [
                'conversation_id' => $convId,
                'message_id' => $assistantMsgId,
                'status' => 'completed',
                'action_resolved' => [
                    'tool_id' => $toolId,
                    'status' => 'rejected',
                    'message_id' => $pendingMsgId
                ]
            ]);
        }

        return [
            'conversation_id' => $convId,
            'message_id' => (int)$assistantMsgId,
            'role' => 'assistant',
            'content' => $cancelText,
            'status' => 'completed',
            'action_resolved' => [
                'tool_id' => $toolId,
                'status' => 'rejected',
                'message_id' => $pendingMsgId
            ]
        ];
    }

    /**
     * Synthesizes an executive response from a completed TaskPlanDAG.
     */
    public static function synthesizeDAGResponse(TaskPlanDAG $dag, array $execResult): string {
        $nodes = $dag->getNodes();
        $entities = $dag->getEntities();
        $completed = [];
        $failed = [];
        $skipped = [];

        foreach ($nodes as $node) {
            if ($node['state'] === 'completed') {
                $completed[] = $node;
            } elseif ($node['state'] === 'failed') {
                $failed[] = $node;
            } elseif ($node['state'] === 'skipped') {
                $skipped[] = $node;
            }
        }

        $lines = [];

        if (!empty($entities['meet_link']) && !empty($entities['recipient_email'])) {
            $lines[] = "I have generated your Google Meet conference room and dispatched the invitation to **{$entities['recipient_email']}**.\n";
            $lines[] = "- **Meeting Room**: {$entities['meet_link']}";
            $lines[] = "- **Recipient**: `{$entities['recipient_email']}`";
            $lines[] = "- **Status**: Dispatched & verified in mail transport";
        } elseif (!empty($entities['file_url']) && !empty($entities['recipient_email'])) {
            $filename = $entities['filename'] ?? 'export file';
            $lines[] = "I have generated **{$filename}** and emailed the secure download link to **{$entities['recipient_email']}**.\n";
            $lines[] = "- **Download Link**: [Download File]({$entities['file_url']}) *(expires in 30 minutes)*";
            $lines[] = "- **Recipient**: `{$entities['recipient_email']}`";
            $lines[] = "- **Status**: Dispatched & verified";
        } elseif (!empty($entities['meet_link'])) {
            $lines[] = "I have generated your instant Google Meet conference room:\n";
            $lines[] = "- **Meeting Room**: {$entities['meet_link']}";
            $lines[] = "- **Status**: Ready to join (verified room format)";
        } elseif (!empty($entities['file_url'])) {
            $filename = $entities['filename'] ?? 'export file';
            $lines[] = "Your requested export **{$filename}** is ready:\n";
            $lines[] = "- **Download Link**: [Download Document]({$entities['file_url']}) *(valid for 30 minutes)*";
            $lines[] = "- **Status**: File verified on secure storage";
        } else {
            $lines[] = "Task workflow execution completed successfully.\n";
            foreach ($completed as $c) {
                $verNote = !empty($c['verification_note']) ? " (" . $c['verification_note'] . ")" : "";
                $lines[] = "- **{$c['title']}**: Completed{$verNote}";
            }
        }

        if (!empty($failed)) {
            $lines[] = "\n**Execution Notices:**";
            foreach ($failed as $f) {
                $lines[] = "- Notice on *{$f['title']}*: " . ($f['error'] ?? 'Execution error');
            }
        }

        if (!empty($skipped)) {
            $lines[] = "\n**Skipped Actions:**";
            foreach ($skipped as $s) {
                $lines[] = "- *{$s['title']}*: Skipped due to predecessor task outcome.";
            }
        }

        $lines[] = "\nWhat would you like me to do next?";
        return implode("\n", $lines);
    }
}
