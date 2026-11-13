<?php
// backend/agent_router.php
// LinkPilot AI Agent: Smart Request Router & Instant Response Engine.
// Classifies incoming user messages into 4 execution paths:
// 1. PATH_INSTANT: Zero-LLM instant responses (greetings, date, time, basic acknowledgements, cached capabilities).
// 2. PATH_SIMPLE_AI: General questions and explanations without heavy tool payloads.
// 3. PATH_BUSINESS_OPS: Targeted CRM, WhatsApp, Email, Deals, Tasks, Calendar, and Analytics tool calling.
// 4. PATH_COMPLEX_AUTONOMOUS: Multi-step workflows, web research, webpage scraping, and compound operations.

require_once __DIR__ . '/agent_cache.php';
require_once __DIR__ . '/agent_telemetry.php';

class AgentRouter {

    const PATH_INSTANT = 'instant';
    const PATH_SIMPLE_AI = 'simple_ai';
    const PATH_BUSINESS_OPS = 'business_ops';
    const PATH_COMPLEX_AUTONOMOUS = 'complex_autonomous';

    /**
     * Classifies user message into an execution path with intent metadata.
     */
    public static function classify(string $userMessage, int $userId, ?PDO $db = null): array {
        $msg = trim($userMessage);
        $clean = strtolower($msg);
        $clean = preg_replace('/[\'`]/u', '', $clean); // today's -> todays, what's -> whats
        $clean = preg_replace('/[^\w\s\/:.-]/u', ' ', $clean);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        // 1. Detect Conceptual / Strategy / Advisory queries (Pure Simple AI, not tool operations)
        $isConceptualOrAdvice = (bool)preg_match('/\b(explain\s+(?:the\s+)?concept|what\s+is\s+(?:the\s+)?difference|define|draft\s+a\s+quote|tips\s+for|how\s+should\s+i\s+structure|what\s+does\s+.+\s+mean|what\s+are\s+common\s+objections|sales\s+standup|quote\s+for)\b/i', $clean);
        $hasExplicitDbAction = (bool)preg_match('/\b(search|find|show\s+me|get|list|create|update|move|delete|schedule|send|check)\b/i', $clean);

        if ($isConceptualOrAdvice && !$hasExplicitDbAction) {
            return [
                'path' => self::PATH_SIMPLE_AI,
                'sub_type' => 'conversational_qa',
                'confidence' => 0.95,
                'categories' => [],
                'reason' => 'User asked for business concept, advice, or creative text without workspace tool operations.'
            ];
        }

        // 2. Detect Business Intent Keywords & Specific Domains
        $businessCategories = [];

        // WhatsApp priority check
        if (preg_match('/\b(whatsapp|wa\s+chat|wa\s+message|unreplied\s+(chat|message|lead)|campaign|broadcast|template|send\s+text)\b/i', $clean)) {
            $businessCategories[] = 'whatsapp';
        }
        // Email priority check
        if (preg_match('/\b(email|emails|mail|inbox|thread|threads|draft\s+email|send\s+email|summarize\s+email)\b/i', $clean)) {
            $businessCategories[] = 'email';
        }
        // Analytics & Briefing priority check
        if (preg_match('/\b(briefing|daily\s+briefing|analytics|metric|metrics|kpi|kpis|credit|credits|balance|wallet|revenue\s+report)\b/i', $clean)) {
            $businessCategories[] = 'analytics';
        }
        // Meetings
        if (preg_match('/\b(meeting|meetings|calendar|appointment|schedule\s+meeting|zoom|google\s+meet)\b/i', $clean)) {
            $businessCategories[] = 'meetings';
        }
        // Deals & Pipeline
        if (preg_match('/\b(deal|deals|pipeline|stage|revenue|sale|sales|at\s+risk|stalled\s+deal)\b/i', $clean) && !in_array('analytics', $businessCategories)) {
            $businessCategories[] = 'deals';
        }
        // Tasks
        if (preg_match('/\b(task|tasks|todo|to-do|reminder|reminders|overdue|pending\s+task|schedule\s+task)\b/i', $clean)) {
            $businessCategories[] = 'tasks';
        }
        // CRM & Contacts (only if not exclusively a WhatsApp chat query)
        if (preg_match('/\b(contact|contacts|lead|leads|customer|customers|person|people|phone\s+number|tag|tags|note|notes)\b/i', $clean)) {
            if (!in_array('whatsapp', $businessCategories) || preg_match('/\b(contact|contacts|lead|leads|tag|note)\b/i', $clean)) {
                $businessCategories[] = 'crm';
            }
        }
        // Automation
        if (preg_match('/\b(workflow|workflows|automation|automations|trigger|active\s+workflow)\b/i', $clean)) {
            $businessCategories[] = 'automation';
        }

        $businessCategories = array_values(array_unique($businessCategories));
        $hasBusinessIntent = !empty($businessCategories);

        // 3. Detect Live External Research & Scraping Intent
        $hasUrl = (bool)preg_match('/https?:\/\/[^\s]+/i', $msg);
        $hasWeatherIntent = (bool)preg_match('/\b(weather|temperature|forecast|rain|climate|humidity|degrees?\s*(?:c|f)?|celsius|fahrenheit)\b/i', $clean);
        $hasWebSearchIntent = (bool)preg_match('/\b(search\s+google|google\s+search|web\s+search|search\s+the\s+web|browse\s+(?:the\s+)?internet|latest\s+(?:news|stocks|market|crypto|saas|competitor)|online\s+search)\b/i', $clean);

        // 4. Detect Multi-step Compound Intent
        $isCompound = (bool)preg_match('/\b(and\s+then|and\s+also|and\s+create|and\s+send|and\s+schedule|and\s+summarize|after\s+that|as\s+well\s+as|find\s+.+\s+and|search\s+.+\s+and|summarize\s+.+\s+and|for\s+each\s+.+\s+(?:create|send|schedule)|calculate\s+.+\s+and|list\s+required\s+actions)\b/i', $clean) || (count($businessCategories) >= 2);

        // =========================================================================
        // ROUTE 1: INSTANT RESPONSE ENGINE (Zero External LLM Cost & Latency)
        // Must NOT match if user expressed business, web search, or weather intent!
        // =========================================================================
        if (!$hasBusinessIntent && !$hasWeatherIntent && !$hasWebSearchIntent && !$hasUrl) {

            // 1a. Date & Time Requests
            $isDateTime = preg_match('/\b(what(?:\s*s|\s+is)?\s+(?:the\s+)?(?:date|time|day)|todays?\s+date|current\s+(?:date|time)|time\s+now|what\s+day\s+is\s+(?:it|today)|date\s+today|tell\s+me\s+the\s+(?:date|time))\b/i', $clean) ||
                          preg_match('/^(what\s+is\s+today|whats\s+today|current\s+day|todays?\s+date|today\s+date)\b/i', $clean);

            if ($isDateTime) {
                return [
                    'path' => self::PATH_INSTANT,
                    'sub_type' => 'date_time',
                    'confidence' => 0.99,
                    'categories' => [],
                    'reason' => 'User requested current date or time directly handled by system clock.'
                ];
            }

            // 1b. Pure Greetings
            $isGreeting = preg_match('/^\s*(hi|hello|hey|hola|namaste|good\s+(morning|afternoon|evening|day)|howdy|sup|greetings)\b/i', $clean);
            $wordCount = str_word_count($clean);
            if ($isGreeting && $wordCount <= 6) {
                return [
                    'path' => self::PATH_INSTANT,
                    'sub_type' => 'greeting',
                    'confidence' => 0.99,
                    'categories' => [],
                    'reason' => 'User sent a standard greeting with no business tool commands.'
                ];
            }

            // 1c. Politeness, Thanks & Acknowledgements
            $isAck = preg_match('/^\s*(thanks?(\s+you)?|thx|appreciate\s+it|great(\s+job)?|bye(\s+bye)?|goodbye|see\s+ya|see\s+you|cya|ok|okay|got\s+it|cool|sounds?\s+good|perfect|alright)\b/i', $clean) && $wordCount <= 6;
            if ($isAck) {
                return [
                    'path' => self::PATH_INSTANT,
                    'sub_type' => 'acknowledgement',
                    'confidence' => 0.99,
                    'categories' => [],
                    'reason' => 'User expressed basic polite acknowledgement or closing.'
                ];
            }

            // 1d. Agent Identity, Self-Introduction & Capabilities
            $isCapabilities = preg_match('/^\s*(who\s+are\s+you|what\s+(can\s+you\s+do|are\s+your\s+capabilities|are\s+you)|introduce\s+yourself|help(\s+me)?|what\s+tools\s+do\s+you\s+have)\b/i', $clean) && $wordCount <= 8;
            if ($isCapabilities) {
                return [
                    'path' => self::PATH_INSTANT,
                    'sub_type' => 'capabilities',
                    'confidence' => 0.98,
                    'categories' => [],
                    'reason' => 'User inquired about Agent identity or supported capabilities.'
                ];
            }
        }

        // =========================================================================
        // ROUTE 4: COMPLEX AUTONOMOUS TASKS
        // Compound multi-step workflows, web research, scraping, or multi-channel
        // =========================================================================
        if ($isCompound || $hasUrl || $hasWebSearchIntent || $hasWeatherIntent || count($businessCategories) >= 2) {
            $complexCategories = array_unique(array_merge(
                $businessCategories,
                $hasWeatherIntent ? ['web'] : [],
                $hasWebSearchIntent ? ['web'] : [],
                $hasUrl ? ['web'] : []
            ));

            return [
                'path' => self::PATH_COMPLEX_AUTONOMOUS,
                'sub_type' => 'complex_workflow',
                'confidence' => 0.95,
                'categories' => $complexCategories,
                'reason' => 'Compound multi-step execution, live research, or multi-channel workflow.'
            ];
        }

        // =========================================================================
        // ROUTE 3: BUSINESS OPERATIONS
        // Targeted single/dual category CRM, WhatsApp, Deals, Tasks, Meetings, Email
        // =========================================================================
        if ($hasBusinessIntent) {
            $primaryCategory = $businessCategories[0];
            return [
                'path' => self::PATH_BUSINESS_OPS,
                'sub_type' => $primaryCategory,
                'confidence' => 0.95,
                'categories' => $businessCategories,
                'reason' => 'Targeted business operation matching: ' . implode(', ', $businessCategories)
            ];
        }

        // =========================================================================
        // ROUTE 2: SIMPLE AI CONVERSATION
        // General Q&A, explanations, creative writing, sales strategy without tools
        // =========================================================================
        return [
            'path' => self::PATH_SIMPLE_AI,
            'sub_type' => 'conversational_qa',
            'confidence' => 0.90,
            'categories' => [],
            'reason' => 'General conversational question or reasoning without external workspace tool execution.'
        ];
    }

    /**
     * Resolves the user's configured timezone safely.
     */
    public static function getUserTimezone(int $userId, ?PDO $db = null): string {
        $cacheKey = "user_tz_{$userId}";
        $cachedTz = AgentCache::get($cacheKey);
        if (!empty($cachedTz)) return $cachedTz;

        $resolvedTz = 'Asia/Kolkata'; // Default LinkPilot timezone

        if ($db) {
            try {
                // 1. Check user_profiles table
                $stmt = $db->prepare("SELECT timezone FROM user_profiles WHERE user_id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $val = $stmt->fetchColumn();
                if (!empty($val) && in_array($val, timezone_identifiers_list())) {
                    $resolvedTz = $val;
                } else {
                    // 2. Check ai_agent_memory table
                    $stmtMem = $db->prepare("SELECT value_text FROM ai_agent_memory WHERE user_id = ? AND key_name = 'timezone' LIMIT 1");
                    $stmtMem->execute([$userId]);
                    $memVal = $stmtMem->fetchColumn();
                    if (!empty($memVal) && in_array($memVal, timezone_identifiers_list())) {
                        $resolvedTz = $memVal;
                    }
                }
            } catch (Throwable $e) {}
        }

        AgentCache::set($cacheKey, $resolvedTz, 1800); // 30 min cache
        return $resolvedTz;
    }

    /**
     * Resolves Agent Custom Name.
     */
    public static function getAgentName(int $userId, ?PDO $db = null): string {
        $cacheKey = "agent_name_{$userId}";
        $cachedName = AgentCache::get($cacheKey);
        if (!empty($cachedName)) return $cachedName;

        $agentName = 'Muse';
        if ($db) {
            try {
                $stmt = $db->prepare("SELECT value_text FROM ai_agent_memory WHERE user_id = ? AND key_name = 'agent_name' LIMIT 1");
                $stmt->execute([$userId]);
                $customName = $stmt->fetchColumn();
                if (!empty($customName)) {
                    $agentName = trim($customName);
                }
            } catch (Throwable $e) {}
        }

        AgentCache::set($cacheKey, $agentName, 3600);
        return $agentName;
    }

    /**
     * Handles instant response generation entirely in PHP in < 5 ms.
     * Never calls OpenAI, Gemini, or any external API.
     */
    public static function handleInstantResponse(
        array $classification,
        string $userMessage,
        int $userId,
        string $convId,
        ?callable $streamEmitter = null,
        ?PDO $db = null
    ): array {
        $startTime = microtime(true);
        $subType = $classification['sub_type'] ?? 'greeting';
        $agentName = self::getAgentName($userId, $db);

        // Fetch User first name
        $userName = 'there';
        if ($db) {
            try {
                $stmt = $db->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $rawName = $stmt->fetchColumn();
                if (!empty($rawName)) {
                    $parts = explode(' ', trim($rawName));
                    $userName = $parts[0];
                }
            } catch (Throwable $e) {}
        }

        $responseText = "";
        $suggestions = [];

        switch ($subType) {
            case 'date_time':
                $userTz = self::getUserTimezone($userId, $db);
                try {
                    $tzObj = new DateTimeZone($userTz);
                    $now = new DateTime('now', $tzObj);
                } catch (Throwable $e) {
                    $tzObj = new DateTimeZone('Asia/Kolkata');
                    $now = new DateTime('now', $tzObj);
                    $userTz = 'Asia/Kolkata';
                }

                $formattedDate = $now->format('l, F j, Y');
                $formattedTime = $now->format('g:i A');
                $time12h = $now->format('h:i A');
                $time24h = $now->format('H:i');

                $responseText = "Today is **{$formattedDate}**.\n\nCurrent local time: **{$formattedTime}** (`{$userTz}`).";
                $suggestions = [
                    "Show today's meetings",
                    "Check hot leads",
                    "Review pending tasks"
                ];
                break;

            case 'greeting':
                $hour = (int)date('G');
                $timeGreeting = ($hour < 12) ? 'Good morning' : (($hour < 17) ? 'Good afternoon' : 'Good evening');

                $responseText = "{$timeGreeting}, **{$userName}**! 👋 I'm **{$agentName}**, your LinkPilot business copilot. I'm connected to your workspace and ready to help.\n\nHere is how I can assist you right now:";
                $responseText .= "\n* **Pipeline Check**: Review at-risk deals and pipeline revenue.";
                $responseText .= "\n* **WhatsApp Leads**: Scan unreplied high-intent customer chats.";
                $responseText .= "\n* **Daily Briefing**: Synthesize your tasks, appointments, and contacts.";

                $suggestions = [
                    "Daily briefing",
                    "Check hot leads",
                    "Show unreplied WhatsApp"
                ];
                break;

            case 'acknowledgement':
                $ackPhrases = [
                    "You're very welcome, {$userName}! Let me know whenever you need anything else with your CRM, messaging, or pipeline.",
                    "Glad I could help! I'm standing by whenever you'd like to check leads, schedule tasks, or review analytics.",
                    "Anytime, {$userName}! Feel free to ask whenever you need to take action in your LinkPilot workspace."
                ];
                $responseText = $ackPhrases[array_rand($ackPhrases)];
                $suggestions = [
                    "Check pending tasks",
                    "Show hot leads",
                    "Review deals pipeline"
                ];
                break;

            case 'capabilities':
            default:
                $responseText = "I am **{$agentName}**, your Autonomous Business Copilot for LinkPilot. Here is an overview of what I can do for your workspace:\n\n";
                $responseText .= "### 📇 CRM & Leads\n* Search and filter contacts by tags, email, company, or days since contacted.\n* Create and enrich leads with buying intent scores.\n\n";
                $responseText .= "### 💬 WhatsApp & Email\n* Inspect unreplied customer conversations.\n* Prepare and send approved personalized WhatsApp & Email messages.\n\n";
                $responseText .= "### 💼 Sales Pipeline & Deals\n* Track deals, move stages, and detect stalled or at-risk opportunities.\n\n";
                $responseText .= "### ⚡ Tasks, Calendar & Analytics\n* Schedule tasks, manage meetings (Google Meet, Zoom), and generate executive morning briefings.\n\n";
                $responseText .= "### 🌐 Live Web & Weather\n* Retrieve real-time weather and extract insights from web pages.";

                $suggestions = [
                    "Show hot leads",
                    "Daily briefing",
                    "Search contacts"
                ];
                break;
        }

        $totalMs = (int)round((microtime(true) - $startTime) * 1000);

        // Save Assistant Message to DB
        $assistantMsgId = 0;
        if ($db) {
            try {
                $stmt = $db->prepare("INSERT INTO ai_agent_messages (conversation_id, user_id, role, content, status, tokens_used) VALUES (?, ?, 'assistant', ?, 'completed', 0)");
                $stmt->execute([$convId, $userId, $responseText]);
                $assistantMsgId = (int)$db->lastInsertId();

                $db->prepare("UPDATE ai_agent_conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);
            } catch (Throwable $e) {}
        }

        // Stream SSE Events
        if ($streamEmitter) {
            $streamEmitter('token', ['content' => $responseText]);
            $streamEmitter('proactive_suggestions', ['suggestions' => $suggestions]);
            $streamEmitter('done', [
                'conversation_id' => $convId,
                'message_id' => $assistantMsgId,
                'status' => 'completed',
                'approval_required' => null,
                'routing' => [
                    'path' => self::PATH_INSTANT,
                    'sub_type' => $subType,
                    'execution_time_ms' => $totalMs
                ]
            ]);
        }

        // Record Telemetry
        AgentTelemetry::logRequest(
            $userId,
            $convId,
            $userMessage,
            $subType,
            self::PATH_INSTANT,
            'internal_php',
            'none',
            [],
            [],
            0,
            0,
            $totalMs,
            0,
            $db
        );

        return [
            'conversation_id' => $convId,
            'message_id' => $assistantMsgId,
            'role' => 'assistant',
            'content' => $responseText,
            'tool_steps' => [],
            'approval_required' => null,
            'proactive_suggestions' => $suggestions,
            'status' => 'completed',
            'routing' => [
                'path' => self::PATH_INSTANT,
                'sub_type' => $subType,
                'execution_time_ms' => $totalMs
            ]
        ];
    }
}
