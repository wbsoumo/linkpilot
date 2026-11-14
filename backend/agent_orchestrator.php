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

class AgentOrchestrator {

    /**
     * Initializes environment and tables.
     */
    public static function init(PDO $db) {
        AgentDBMigration::run($db);
        AgentTelemetry::initTable($db);
        AIToolRegistry::init();
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
        $stmt = $db->prepare("SELECT u.name, u.email, u.role, p.company_name, p.industry, p.timezone FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
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
7. Calendar: List and schedule business meetings (Google Meet, Zoom).
8. Analytics: Generate daily executive briefings, pipeline revenue summaries, and conversion KPI reports.
9. Web & Research: Live weather lookups, Google/web search, and direct webpage content extraction.

### STRICT EXECUTION & SECURITY RULES:
1. AUTONOMOUS READ: All READ operations (searching contacts, reading WhatsApp chats, checking deals, analytics, etc.) execute automatically without asking.
2. APPROVAL REQUIRED FOR EXTERNAL/DESTRUCTIVE:
   - When asked to perform an external communication (sending an email, sending a WhatsApp message) or a destructive action (archiving a contact, deleting a deal), invoke the tool with the proposed payload. The system will prompt the user with an interactive Approval Card showing full details.
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

        foreach ($historyRows as $row) {
            $role = $row['role'];
            $content = $row['content'];
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // =========================================================================
        // 2. DYNAMIC CATEGORY-BASED TOOL LOADING
        // =========================================================================
        $availableTools = AIToolRegistry::getToolsForMessage($userMessage, $classification);
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

        // Extract proactive recommendations
        $proactiveSuggestions = self::generateProactiveSuggestions($finalAssistantText, $toolExecutionSteps, $userMessage);

        // Ensure database connection is alive after AI call
        try {
            @$db->query("SELECT 1");
        } catch (Throwable $e) {
            $db = Database::getConnection(true);
        }

        // Save Assistant Message
        $stmtAssistant = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, user_id, role, content, tool_calls_json, tool_results_json, status, tokens_used) VALUES (?, ?, 'assistant', ?, ?, ?, ?, ?)");
        $status = $approvalRequiredPayload ? 'waiting_approval' : 'completed';
        $stmtAssistant->execute([
            $convId,
            $userId,
            $finalAssistantText,
            json_encode($toolExecutionSteps),
            json_encode($approvalRequiredPayload ?: []),
            $status,
            $totalTokensUsed
        ]);
        $assistantMsgId = $db->lastInsertId();

        // Update conversation timestamp
        $db->prepare("UPDATE ai_agent_conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);

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
            $db
        );

        $routingMeta = [
            'path' => $classification['path'],
            'sub_type' => $classification['sub_type'] ?? '',
            'provider' => $activeProvider,
            'model' => $activeModel,
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
}
