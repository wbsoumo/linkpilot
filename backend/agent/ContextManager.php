<?php
// backend/agent/ContextManager.php
// Autonomous Agent Engine 2.0: Multi-Turn Context & Reference Resolution Engine.
// Tracks active workflow context, resolves pronouns ('it', 'that', 'send him', 'that file'),
// and bridges entities across turns to prevent the agent from asking for already-provided data.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WorkflowEngine.php';
require_once __DIR__ . '/PluginRegistry.php';

class ContextManager {

    /**
     * Resolves complete multi-turn context, entity references, active workflow state,
     * and pronoun anchors for an incoming user message.
     */
    public static function resolveContext(
        int $userId,
        string $convId,
        string $userMessage,
        array $recentMessages = [],
        ?PDO $db = null
    ): array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) {}
        }

        // 1. Fetch active workflow for this conversation
        $activeWorkflow = null;
        if ($db) {
            $activeWorkflow = WorkflowEngine::getActiveWorkflow($userId, $convId, $db);
        }
        $activeEntities = $activeWorkflow['entities'] ?? [];

        // 2. Extract recent entities from historical messages if workflow entities are sparse
        if (empty($activeEntities['meet_link']) || empty($activeEntities['file_url']) || empty($activeEntities['contact_id'])) {
            $extracted = self::extractEntitiesFromRecentMessages($recentMessages);
            $activeEntities = array_merge($extracted, $activeEntities);
        }

        // 3. Pronoun & Reference Resolution
        $pronounAnchors = self::resolvePronouns($userMessage, $activeEntities, $activeWorkflow);

        // 4. Identify relevant plugin IDs to retain
        $retainedPluginIds = [];
        if ($activeWorkflow && !empty($activeWorkflow['plugin_id'])) {
            $retainedPluginIds[] = $activeWorkflow['plugin_id'];
        }
        if (!empty($pronounAnchors['suggested_plugin'])) {
            $retainedPluginIds[] = $pronounAnchors['suggested_plugin'];
        }
        $retainedPluginIds = array_values(array_unique(array_filter($retainedPluginIds)));

        // 5. Build structured prompt injection block
        $promptContext = self::buildContextPromptBlock($activeWorkflow, $activeEntities, $pronounAnchors);

        return [
            'active_workflow' => $activeWorkflow,
            'active_entities' => $activeEntities,
            'pronoun_anchors' => $pronounAnchors,
            'retained_plugin_ids' => $retainedPluginIds,
            'prompt_context' => $promptContext
        ];
    }

    /**
     * Detects conversational pronouns ('it', 'that', 'the link', 'send him', 'that file')
     * and anchors them to concrete entities established in previous turns.
     */
    public static function resolvePronouns(string $userMessage, array $entities, ?array $activeWorkflow): array {
        $clean = strtolower($userMessage);
        $anchors = [];
        $suggestedPlugin = null;

        // References to links, meetings, or conferences ("it", "that", "the meet", "the link")
        $hasLinkPronoun = (bool)preg_match('/\b(it|that|this|the\s+link|the\s+meeting|the\s+meet|the\s+room|the\s+invite)\b/i', $clean);
        if ($hasLinkPronoun && !empty($entities['meet_link'])) {
            $anchors['it'] = [
                'type' => 'meet_link',
                'value' => $entities['meet_link'],
                'description' => "Google Meet Room: {$entities['meet_link']}"
            ];
            // If user said "send it to [email]", suggest email plugin
            if (preg_match('/\b(email|send|mail|share)\b/i', $clean)) {
                $suggestedPlugin = 'gmail_plugin';
            }
        }

        // References to generated files ("that file", "the document", "the spreadsheet", "the excel", "download it")
        $hasFilePronoun = (bool)preg_match('/\b(it|that|the\s+file|that\s+file|the\s+sheet|the\s+excel|the\s+document|the\s+pdf|the\s+csv|the\s+export)\b/i', $clean);
        if ($hasFilePronoun && !empty($entities['file_url'])) {
            $anchors['file'] = [
                'type' => 'file_url',
                'value' => $entities['file_url'],
                'filename' => $entities['filename'] ?? 'generated_file',
                'description' => "Exported File: " . ($entities['filename'] ?? 'document') . " ({$entities['file_url']})"
            ];
            if (preg_match('/\b(email|send|mail|share)\b/i', $clean)) {
                $suggestedPlugin = 'gmail_plugin';
            }
        }

        // References to a person ("him", "her", "them", "this contact", "the lead")
        $hasPersonPronoun = (bool)preg_match('/\b(him|her|them|this\s+person|this\s+contact|the\s+lead|the\s+client)\b/i', $clean);
        if ($hasPersonPronoun && !empty($entities['contact_name'])) {
            $anchors['person'] = [
                'type' => 'contact',
                'contact_id' => $entities['contact_id'] ?? null,
                'name' => $entities['contact_name'],
                'email' => $entities['contact_email'] ?? null,
                'phone' => $entities['contact_phone'] ?? null,
                'description' => "Target Contact: {$entities['contact_name']}" . (!empty($entities['contact_email']) ? " ({$entities['contact_email']})" : '')
            ];
            if (preg_match('/\b(whatsapp|text|message)\b/i', $clean)) {
                $suggestedPlugin = 'whatsapp_plugin';
            } elseif (preg_match('/\b(email|mail)\b/i', $clean)) {
                $suggestedPlugin = 'gmail_plugin';
            }
        }

        // Detect explicit destination email in current message
        if (preg_match('/[a-zA-Z0-9_\.\+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-\.]+/i', $userMessage, $emailMatch)) {
            $anchors['recipient_email'] = $emailMatch[0];
            $suggestedPlugin = 'gmail_plugin';
        }

        return [
            'anchors' => $anchors,
            'suggested_plugin' => $suggestedPlugin
        ];
    }

    /**
     * Extracts structured business entities from recent historical messages.
     */
    public static function extractEntitiesFromRecentMessages(array $messages): array {
        $entities = [];
        $reversed = array_reverse($messages);

        foreach ($reversed as $msg) {
            $content = $msg['content'] ?? '';

            // Extract Google Meet links
            if (empty($entities['meet_link']) && preg_match('/https:\/\/meet\.google\.com\/[a-z]{3}-[a-z]{4}-[a-z]{3}/i', $content, $meetM)) {
                $entities['meet_link'] = $meetM[0];
            }

            // Extract file download URLs
            if (empty($entities['file_url']) && preg_match('/https?:\/\/[^\s\)]+download_temp_file\.php\?token=[^\s\)]+/i', $content, $dlM)) {
                $entities['file_url'] = $dlM[0];
                if (preg_match('/\[([^\]]+)\]/i', $content, $lblM)) {
                    $entities['filename'] = $lblM[1];
                }
            }

            // Extract emails
            if (empty($entities['contact_email']) && preg_match('/[a-zA-Z0-9_\.\+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-\.]+/i', $content, $emM)) {
                $entities['contact_email'] = $emM[0];
            }
        }

        return $entities;
    }

    /**
     * Synthesizes an explicit prompt instructions block for the AI model.
     */
    public static function buildContextPromptBlock(?array $activeWorkflow, array $entities, array $pronounAnchors): string {
        $lines = [];
        $lines[] = "### MULTI-TURN WORKFLOW & ENTITY CONTEXT (DO NOT RE-ASK FOR THESE DETAILS):";

        if ($activeWorkflow) {
            $lines[] = "- Active Workflow: {$activeWorkflow['workflow_type']} (Plugin: {$activeWorkflow['plugin_id']})";
            $lines[] = "- Workflow Status: {$activeWorkflow['state']}";
            $lines[] = "- Goal: {$activeWorkflow['goal']}";
        }

        if (!empty($pronounAnchors['anchors'])) {
            $lines[] = "- RESOLVED PRONOUN REFERENCES:";
            foreach ($pronounAnchors['anchors'] as $refKey => $anchor) {
                if (is_array($anchor)) {
                    $desc = $anchor['description'] ?? json_encode($anchor);
                    $lines[] = "  * Reference '{$refKey}' refers to: {$desc}";
                } else {
                    $lines[] = "  * '{$refKey}': {$anchor}";
                }
            }
        }

        if (!empty($entities)) {
            $lines[] = "- ACTIVE ENTITIES IN SESSION:";
            if (!empty($entities['meet_link'])) $lines[] = "  * Active Google Meet URL: {$entities['meet_link']}";
            if (!empty($entities['file_url'])) $lines[] = "  * Active File Export: " . ($entities['filename'] ?? 'file') . " ({$entities['file_url']})";
            if (!empty($entities['contact_name'])) $lines[] = "  * Active Contact: {$entities['contact_name']} (ID: " . ($entities['contact_id'] ?? 'N/A') . ")";
            if (!empty($entities['recipient_email'])) $lines[] = "  * Destination Email: {$entities['recipient_email']}";
        }

        $lines[] = "- INSTRUCTION: Use these resolved entities directly. For example, if the user says 'send it to xyz@example.com', execute the send_email tool using the Active Google Meet URL above without asking the user to re-paste the link.";

        return implode("\n", $lines);
    }
}
