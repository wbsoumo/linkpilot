<?php
// backend/ai_agent_planner.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_tool_registry.php';
require_once __DIR__ . '/agent_context_engine.php';
require_once __DIR__ . '/agent_execution_engine.php';

class AIAgentPlanner {

    /**
     * Generates a dynamic multi-step execution plan for a user goal.
     */
    public static function createPlan($userId, $goal, array $requestMeta = [], ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();

        $context = AgentContextEngine::buildUserContext($userId, $requestMeta, $db);
        $availableTools = AIToolRegistry::listTools();

        // 1. Check for requested capabilities that do not exist
        $goalLower = strtolower($goal);
        if (str_contains($goalLower, 'phone call') || str_contains($goalLower, 'call him') || str_contains($goalLower, 'make a call')) {
            if (!$context['integrations']['voice_calls']['connected']) {
                return [
                    'status' => 'capability_unavailable',
                    'goal' => $goal,
                    'missing_capability' => 'voice_calls',
                    'message' => "I understand what you want, but phone calling is not available in LinkPilot yet. I can send an email or WhatsApp message instead if you would like.",
                    'available_alternatives' => ['send_email', 'send_whatsapp']
                ];
            }
        }

        // 2. Perform entity resolution for entity names mentioned in goal
        $entityMatches = [];
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?\b/', $goal, $matches);
        $potentialNames = array_unique($matches[0] ?? []);

        foreach ($potentialNames as $name) {
            if (in_array(strtolower($name), ['find', 'search', 'send', 'create', 'update', 'linkpilot', 'crm', 'ai'])) continue;
            $res = AgentContextEngine::resolveEntity($userId, $name, $db);
            if ($res && $res['ambiguous']) {
                return [
                    'status' => 'ambiguous_entity',
                    'goal' => $goal,
                    'ambiguous_term' => $name,
                    'candidates' => $res['candidates'],
                    'message' => $res['message']
                ];
            } elseif ($res && $res['resolved']) {
                $entityMatches[] = $res['entity'];
            }
        }

        // 3. Construct System Prompt for AI Dynamic Planner
        $toolsJson = json_encode($availableTools, JSON_PRETTY_PRINT);
        $ctxJson = json_encode($context['workspace'], JSON_PRETTY_PRINT);

        $systemPrompt = "You are the LinkPilot AI Universal Agent Planner.
Convert the user's natural language goal into a structured multi-step execution plan using ONLY available tools listed below.

AVAILABLE TOOLS:
$toolsJson

CURRENT WORKSPACE CONTEXT:
$ctxJson

RULES:
1. Do NOT hardcode workflow functions. Dynamically select and combine generic tools.
2. For READ operations (risk_level: 'READ'), set requires_approval: false.
3. For WRITE, EXTERNAL, or DESTRUCTIVE operations, set requires_approval: true.
4. If the goal is general conversational chat or a simple greeting, set is_chat: true.
5. Return JSON ONLY (no markdown blocks around it) matching this structure:
{
  \"is_chat\": false,
  \"chat_reply\": \"...\",
  \"goal\": \"...\",
  \"summary\": \"Brief summary of planned action\",
  \"steps\": [
    {
      \"id\": \"step_1\",
      \"tool_id\": \"search_contacts\",
      \"tool_name\": \"Search Contacts\",
      \"risk_level\": \"READ|WRITE|EXTERNAL|DESTRUCTIVE\",
      \"requires_approval\": false,
      \"input\": {
        \"q\": \"...\"
      }
    }
  ]
}";

        $userPrompt = "User Goal: \"$goal\"";
        $rawText = '';
        $planData = null;

        try {
            $ai = callAI($systemPrompt, $userPrompt, $userId);
            $rawText = $ai['text'] ?? '';
            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
            $firstBrace = strpos($cleanJson, '{');
            $lastBrace = strrpos($cleanJson, '}');
            if ($firstBrace !== false && $lastBrace !== false) {
                $cleanJson = substr($cleanJson, $firstBrace, $lastBrace - $firstBrace + 1);
            }
            $planData = json_decode($cleanJson, true);
        } catch (Throwable $t) {
            $planData = null;
        }

        if (!$planData || (!empty($planData['is_chat']) && $planData['is_chat'])) {
            return [
                'status' => 'chat',
                'goal' => $goal,
                'message' => $planData['chat_reply'] ?? "Hello! How can I assist you with your LinkPilot CRM workspace today?"
            ];
        }

        return [
            'status' => 'plan_created',
            'execution_id' => 'exec_' . uniqid(),
            'goal' => $goal,
            'summary' => $planData['summary'] ?? 'Dynamic workflow plan created',
            'steps' => $planData['steps'] ?? []
        ];
    }
}
