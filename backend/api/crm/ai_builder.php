<?php
// backend/api/crm/ai_builder.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../wallet_helper.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Validate Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Read POST payload
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$message = trim($input['message'] ?? '');
$history = $input['history'] ?? []; // Array of {role: 'user'|'assistant', content: '...'}

if (empty($message)) {
    sendJsonResponse('error', 'Message cannot be empty', [], 400);
}

try {
    $systemPrompt = "You are the LinkPilot AI Automation Builder, a conversational agent who helps users design visual automation workflows.
Your goal is to talk with the user, clarify their trigger, condition criteria, actions, and follow-up paths.

Rules:
1. Converse naturally. If their request is vague (e.g., 'help me create a workflow'), ask them clarifying questions like:
   - What event should start this workflow? (e.g. incoming email, new lead form, webhook, etc.)
   - Are there any rules/conditions to check? (e.g. check if lead already exists, filter by tag, check budget etc.)
   - What actions should happen automatically? (e.g. send an email outreach, notify Slack team, create a task, etc.)
2. If they provide some details but miss others, ask them for the missing details. Keep the conversation interactive and focused.
3. Once they have answered your questions and you both agree on the logic, set the 'ready' field to true and supply the structured 'summary' JSON details.
4. If you are still in Q&A mode, set 'ready' to false and 'summary' to null.

You MUST respond ONLY with a raw JSON object matching this schema:
{
  \"reply\": \"Your conversational response, questions, or confirmation statement to the user.\",
  \"ready\": false,
  \"summary\": null
}

Or, if ready is true:
{
  \"reply\": \"Fantastic! I have formulated the complete workflow logic for you. Here is the summary:\",
  \"ready\": true,
  \"summary\": {
    \"title\": \"Email Lead Router & Task Creator\",
    \"description\": \"Monitors incoming emails, verifies lead existence, assigns sales tasks, and alerts the team on Slack.\",
    \"steps\": [
       { \"step\": 1, \"type\": \"Trigger\", \"label\": \"Email Received\" },
       { \"step\": 2, \"type\": \"Condition\", \"label\": \"Check if Lead Exists?\" },
       { \"step\": 3, \"type\": \"Action (YES)\", \"label\": \"Create CRM Task\" },
       { \"step\": 4, \"type\": \"Action (NO)\", \"label\": \"Add to Nurture Campaign\" },
       { \"step\": 5, \"type\": \"Action\", \"label\": \"Send Slack Alert\" }
    ]
  }
}

Do NOT wrap the JSON response in markdown code blocks like ```json ... ```. Output raw JSON ONLY. Any deviation from this raw JSON response pattern will break the parser.";

    // Compile history
    $promptBody = "";
    if (count($history) > 0) {
        $promptBody .= "Conversation history:\n";
        foreach ($history as $h) {
            $roleName = ($h['role'] === 'user') ? 'User' : 'Assistant';
            $promptBody .= "$roleName: " . $h['content'] . "\n";
        }
        $promptBody .= "\n";
    }
    $promptBody .= "User: $message\n\nAssistant Response (strictly JSON):";

    $aiResult = callAI($systemPrompt, $promptBody, $userId);
    $rawReply = trim($aiResult['text']);

    // Attempt to extract JSON if LLM wrapped it in markdown code blocks
    if (preg_match('/```json\s*(.*?)\s*```/s', $rawReply, $matches)) {
        $rawReply = trim($matches[1]);
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $rawReply, $matches)) {
        $rawReply = trim($matches[1]);
    }

    $parsed = json_decode($rawReply, true);
    if (!$parsed) {
        // Fallback if parsing failed
        $parsed = [
            'reply' => $aiResult['text'],
            'ready' => false,
            'summary' => null
        ];
    }

    sendJsonResponse('success', 'AI response generated', $parsed);

} catch (Throwable $e) {
    sendJsonResponse('error', 'AI Builder backend error: ' . $e->getMessage(), [
        'reply' => 'I encountered an error connecting to the AI models: ' . $e->getMessage(),
        'ready' => false,
        'summary' => null
    ], 200);
}
