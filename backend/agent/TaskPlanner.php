<?php
// backend/agent/TaskPlanner.php
// Autonomous Agent Engine 2.0: Tier 1 Strategic Planning Engine.
// Synthesizes user requests into structured Directed Acyclic Graph (DAG) task plans,
// detecting explicit dependencies, mutating actions, and Maker-Checker approval gates.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/TaskPlanDAG.php';
require_once __DIR__ . '/PluginRegistry.php';
require_once __DIR__ . '/ContextManager.php';

class TaskPlanner {

    /**
     * Synthesizes an executable DAG plan from the user prompt and context.
     */
    public static function createPlan(
        string $userMessage,
        array $classification,
        array $contextResolution,
        int $userId,
        string $convId,
        ?PDO $db = null
    ): TaskPlanDAG {
        $clean = strtolower($userMessage);
        $entities = $contextResolution['active_entities'] ?? [];
        $anchors = $contextResolution['pronoun_anchors']['anchors'] ?? [];

        // 1. Check for standard multi-step patterns (Deterministic fast path, 0ms latency)
        $patternDAG = self::detectDeterministicPattern($clean, $userMessage, $entities, $anchors);
        if ($patternDAG) {
            return $patternDAG;
        }

        // 2. Single-step intent mapping (e.g. search, instant meet, export, briefings)
        $singleStepDAG = self::detectSingleStepIntent($clean, $userMessage, $classification, $entities);
        if ($singleStepDAG) {
            return $singleStepDAG;
        }

        // 3. Fallback: General inquiry / conversational plan
        return new TaskPlanDAG([], $entities);
    }

    /**
     * Detects common compound multi-step business goals.
     */
    private static function detectDeterministicPattern(
        string $clean,
        string $rawMessage,
        array $entities,
        array $anchors
    ): ?TaskPlanDAG {
        // Pattern A: "Create a meeting / meet link and email/send it to [email/person]"
        $wantsMeeting = (bool)preg_match('/\b(meet|meeting|calendar|schedule|google\s+meet)\b/i', $clean);
        $wantsEmail = (bool)preg_match('/\b(email|mail|send|share)\b/i', $clean);
        preg_match('/[a-zA-Z0-9_\.\+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-\.]+/i', $rawMessage, $emailMatch);
        $recipientEmail = $emailMatch[0] ?? ($anchors['recipient_email'] ?? ($entities['contact_email'] ?? ''));

        if ($wantsMeeting && $wantsEmail && !empty($recipientEmail)) {
            $nodes = [
                [
                    'task_key' => 'task_1',
                    'tool_id' => 'generate_instant_meet',
                    'title' => 'Create Encrypted Google Meet Room',
                    'dependencies' => [],
                    'arguments' => ['title' => 'Client Video Meeting'],
                    'is_mutating' => true,
                    'requires_approval' => false
                ],
                [
                    'task_key' => 'task_2',
                    'tool_id' => 'send_email',
                    'title' => "Send Meeting Invitation to {$recipientEmail}",
                    'dependencies' => ['task_1'],
                    'arguments' => [
                        'recipient' => $recipientEmail,
                        'subject' => 'Your Google Meet Link',
                        'body' => "Hi,\n\nHere is our Google Meet video conference link:\n{{task_1.output.meet_link}}\n\nLooking forward to speaking with you!"
                    ],
                    'is_mutating' => true,
                    'requires_approval' => true // Maker-Checker gate
                ]
            ];
            return new TaskPlanDAG($nodes, $entities);
        }

        // Pattern B: "Export contacts / data in [excel/csv/pdf] and email it to [email]"
        $wantsExport = (bool)preg_match('/\b(export|download|excel|csv|pdf|word|sheet)\b/i', $clean);
        if ($wantsExport && $wantsEmail && !empty($recipientEmail)) {
            $ext = 'xlsx';
            if (preg_match('/\b(csv)\b/i', $clean)) $ext = 'csv';
            elseif (preg_match('/\b(pdf)\b/i', $clean)) $ext = 'pdf';

            $nodes = [
                [
                    'task_key' => 'task_1',
                    'tool_id' => 'generate_export_file',
                    'title' => "Generate {$ext} Export Document",
                    'dependencies' => [],
                    'arguments' => ['format' => $ext, 'filename' => "contacts_export.{$ext}"],
                    'is_mutating' => true,
                    'requires_approval' => false
                ],
                [
                    'task_key' => 'task_2',
                    'tool_id' => 'send_email',
                    'title' => "Send Document to {$recipientEmail}",
                    'dependencies' => ['task_1'],
                    'arguments' => [
                        'recipient' => $recipientEmail,
                        'subject' => "LinkPilot CRM Export: contacts_export.{$ext}",
                        'body' => "Hi,\n\nYour requested CRM export has been prepared and is ready for download:\n{{task_1.output.download_url}}\n\n*Note: This link expires in 30 minutes.*"
                    ],
                    'is_mutating' => true,
                    'requires_approval' => true
                ]
            ];
            return new TaskPlanDAG($nodes, $entities);
        }

        // Pattern C: Multi-turn continuation: "Send it to [email]" when active entity has meet_link or file_url
        if ($wantsEmail && !empty($recipientEmail) && (!empty($entities['meet_link']) || !empty($entities['file_url']))) {
            $linkVal = $entities['meet_link'] ?? $entities['file_url'];
            $subject = !empty($entities['meet_link']) ? 'Google Meet Conference Link' : 'Your Requested File Download';
            $nodes = [
                [
                    'task_key' => 'task_1',
                    'tool_id' => 'send_email',
                    'title' => "Dispatch Email to {$recipientEmail}",
                    'dependencies' => [],
                    'arguments' => [
                        'recipient' => $recipientEmail,
                        'subject' => $subject,
                        'body' => "Hi,\n\nPlease find the details requested below:\n{$linkVal}\n\nBest regards,\nLinkPilot Workspace"
                    ],
                    'is_mutating' => true,
                    'requires_approval' => true
                ]
            ];
            return new TaskPlanDAG($nodes, $entities);
        }

        return null;
    }

    /**
     * Detects single-step autonomous tasks.
     */
    private static function detectSingleStepIntent(
        string $clean,
        string $rawMessage,
        array $classification,
        array $entities
    ): ?TaskPlanDAG {
        // 1. Google Meet generation
        if (preg_match('/\b(create|make|generate|start)\b.+\b(meet|meeting|video\s+room|conference)\b/i', $clean) && !str_contains($clean, 'send') && !str_contains($clean, 'email')) {
            $nodes = [
                [
                    'task_key' => 'task_1',
                    'tool_id' => 'generate_instant_meet',
                    'title' => 'Generate Instant Google Meet Room',
                    'dependencies' => [],
                    'arguments' => ['title' => 'Instant Video Call'],
                    'is_mutating' => true,
                    'requires_approval' => false
                ]
            ];
            return new TaskPlanDAG($nodes, $entities);
        }

        // 2. Export temporary file
        if (preg_match('/\b(create|make|generate|export|download)\b.+\b(file|excel|csv|pdf|word|docx?|sheet|spreadsheet)\b/i', $clean) && !str_contains($clean, 'send') && !str_contains($clean, 'email')) {
            $ext = 'xlsx';
            if (preg_match('/\b(csv)\b/i', $clean)) $ext = 'csv';
            elseif (preg_match('/\b(pdf)\b/i', $clean)) $ext = 'pdf';
            elseif (preg_match('/\b(word|docx?)\b/i', $clean)) $ext = 'docx';

            $nodes = [
                [
                    'task_key' => 'task_1',
                    'tool_id' => 'generate_export_file',
                    'title' => "Export CRM Data ({$ext})",
                    'dependencies' => [],
                    'arguments' => ['format' => $ext, 'filename' => "contacts.{$ext}"],
                    'is_mutating' => true,
                    'requires_approval' => false
                ]
            ];
            return new TaskPlanDAG($nodes, $entities);
        }

        // 3. Web Research
        if (preg_match('/\b(search|google|web|who is|what is|competitor|weather)\b/i', $clean) && preg_match('/\b(search|weather)\b/i', $clean)) {
            $toolId = str_contains($clean, 'weather') ? 'get_live_weather' : 'web_search';
            $nodes = [
                [
                    'task_key' => 'task_1',
                    'tool_id' => $toolId,
                    'title' => ($toolId === 'get_live_weather') ? 'Get Live Weather' : 'Google Web Search',
                    'dependencies' => [],
                    'arguments' => ($toolId === 'get_live_weather') ? ['location' => 'auto'] : ['query' => $rawMessage],
                    'is_mutating' => false,
                    'requires_approval' => false
                ]
            ];
            return new TaskPlanDAG($nodes, $entities);
        }

        return null;
    }
}
