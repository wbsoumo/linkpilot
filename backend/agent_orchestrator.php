<?php
// backend/agent_orchestrator.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/agent_db_migration.php';
require_once __DIR__ . '/ai_tool_registry.php';
require_once __DIR__ . '/agent_ai_client.php';
require_once __DIR__ . '/crm_sync_helper.php';

class AgentOrchestrator {

    /**
     * Initializes environment and tables.
     */
    public static function init(PDO $db) {
        AgentDBMigration::run($db);
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

        $now = date('l, F j, Y, g:i A');

        return "You are the LinkPilot AI Agent: The Unified Autonomous Business Assistant & Intelligence Layer for LinkPilot.
You have direct, real-time access to control, manage, and execute operations across this LinkPilot workspace.

### OPERATIONAL IDENTITY & CONTEXT:
- Current Time: $now
- User: $userName ($companyName)
- Active Integrations:
$integrationsSummary
$memText

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
     * Executes the conversational chat loop with tool calling and streaming.
     */
    public static function processChat($userId, $convId, $userMessage, $options = [], ?callable $streamEmitter = null, ?PDO $db = null) {
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

        // Fetch sliding window of recent conversation history (last 12 messages)
        $stmtHist = $db->prepare("SELECT role, content, tool_calls_json, tool_results_json FROM ai_agent_messages WHERE conversation_id = ? AND user_id = ? AND id < ? ORDER BY id DESC LIMIT 12");
        $stmtHist->execute([$convId, $userId, $userMsgId]);
        $historyRows = array_reverse($stmtHist->fetchAll(PDO::FETCH_ASSOC));

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => self::buildSystemPrompt($userId, $db)];

        foreach ($historyRows as $row) {
            $role = $row['role'];
            $content = $row['content'];
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $availableTools = AIToolRegistry::getOpenAITools();
        $toolExecutionSteps = [];
        $maxLoops = 6;
        $loopCount = 0;
        $finalAssistantText = "";
        $approvalRequiredPayload = null;

        while ($loopCount < $maxLoops) {
            $loopCount++;

            // Call AI
            $aiResponse = AgentAIClient::callChat($messages, $availableTools, $userId);
            $content = $aiResponse['content'];
            $toolCalls = $aiResponse['tool_calls'];

            if (empty($toolCalls)) {
                $finalAssistantText = $content;
                break;
            }

            // Process tool calls
            $hasApprovalPause = false;
            foreach ($toolCalls as $tc) {
                $toolId = $tc['name'];
                $toolArgs = $tc['arguments'];
                $toolDef = AIToolRegistry::getTool($toolId);

                if (!$toolDef) {
                    $toolResult = ["error" => "Tool '{$toolId}' is not recognized in LinkPilot registry."];
                } else {
                    $isApprovalReq = $toolDef['approval_required'] ?? false;

                    // If approval is required and user hasn't explicitly pre-approved this step
                    if ($isApprovalReq && empty($options['approved_tool_id'])) {
                        $approvalRequiredPayload = [
                            'tool_id' => $toolId,
                            'tool_name' => $toolDef['name'],
                            'description' => $toolDef['description'],
                            'risk_level' => $toolDef['risk_level'],
                            'arguments' => $toolArgs,
                            'approval_token' => 'appr_' . bin2hex(random_bytes(8))
                        ];

                        if ($streamEmitter) {
                            $streamEmitter('approval_required', $approvalRequiredPayload);
                        }

                        // Log audit trail
                        $stmtAudit = $db->prepare("INSERT INTO ai_agent_audit_logs (user_id, conversation_id, tool_id, tool_name, risk_level, input_json, approval_status) VALUES (?, ?, ?, ?, ?, ?, 'rejected')");
                        $stmtAudit->execute([$userId, $convId, $toolId, $toolDef['name'], $toolDef['risk_level'], json_encode($toolArgs)]);

                        $hasApprovalPause = true;
                        $finalAssistantText = "I have prepared the action **" . htmlspecialchars($toolDef['name']) . "** for you. Because this performs an external communication or significant change, please review the preview details below and confirm to execute.";
                        break;
                    }

                    // Execute tool
                    if ($streamEmitter) {
                        $streamEmitter('tool_start', [
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

                    $toolExecutionSteps[] = [
                        'tool_id' => $toolId,
                        'tool_name' => $toolDef['name'],
                        'status' => $stepStatus,
                        'input' => $toolArgs,
                        'output' => $toolResult
                    ];

                    if ($streamEmitter) {
                        $streamEmitter('tool_complete', [
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

            if ($hasApprovalPause) {
                break;
            }
        }

        // If no text was produced yet, generate final synthesis
        if (empty($finalAssistantText) && empty($approvalRequiredPayload)) {
            $finalAi = AgentAIClient::callChat($messages, [], $userId);
            $finalAssistantText = $finalAi['content'];
        }

        // Extract proactive recommendations
        $proactiveSuggestions = self::generateProactiveSuggestions($finalAssistantText, $toolExecutionSteps, $userMessage);

        // Save Assistant Message
        $stmtAssistant = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, user_id, role, content, tool_calls_json, tool_results_json, status) VALUES (?, ?, 'assistant', ?, ?, ?, ?)");
        $status = $approvalRequiredPayload ? 'waiting_approval' : 'completed';
        $stmtAssistant->execute([
            $convId,
            $userId,
            $finalAssistantText,
            json_encode($toolExecutionSteps),
            json_encode($approvalRequiredPayload ?: []),
            $status
        ]);
        $assistantMsgId = $db->lastInsertId();

        // Update conversation timestamp
        $db->prepare("UPDATE ai_agent_conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);

        if ($streamEmitter) {
            $streamEmitter('token', ['content' => $finalAssistantText]);
            $streamEmitter('proactive_suggestions', ['suggestions' => $proactiveSuggestions]);
            $streamEmitter('done', [
                'conversation_id' => $convId,
                'message_id' => $assistantMsgId,
                'status' => $status,
                'approval_required' => $approvalRequiredPayload
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
            'status' => $status
        ];
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
