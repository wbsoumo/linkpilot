<?php
// backend/api/crm/ai_builder_helper.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$prompt = trim($input['prompt'] ?? '');
$action = trim($input['action'] ?? 'rewrite');

if (empty($prompt)) {
    sendJsonResponse('error', 'Prompt is required.', [], 400);
}

// Build standard AI system prompt based on action
$systemPrompt = "You are LinkPilot Email AI Assistant. Your task is to generate high-performing email copy or layout suggestions.
Keep your tone professional, persuasive, and optimized for marketing conversions. Return ONLY the requested text, without any conversational wrappers, markdown formatting markers (like ```), or comments.";

switch ($action) {
    case 'subject':
        $systemPrompt .= "\nGenerate 5 highly engaging, high-open-rate subject lines and preview texts based on the user's description. Keep them short, punchy, and include emojis where appropriate.";
        break;
    case 'hero':
        $systemPrompt .= "\nGenerate content for an email hero banner based on the user's description. Return a structured JSON containing 'title', 'subtitle', and 'cta_text'. Example: {\"title\": \"Headline\", \"subtitle\": \"Sub-headline\", \"cta_text\": \"Action Button\"}";
        break;
    case 'cta':
        $systemPrompt .= "\nRewrite the user's call to action (CTA) to make it highly persuasive, action-oriented, and conversion-focused. Provide 3 options.";
        break;
    case 'rewrite':
    default:
        $systemPrompt .= "\nRewrite, expand, or improve the grammar and readability of the user's text. Ensure it is professional and highly readable.";
        break;
}

try {
    $aiResult = callAI($systemPrompt, $prompt, $userId);
    
    sendJsonResponse('success', 'AI response generated successfully.', [
        'result' => $aiResult
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'AI processing failed: ' . $e->getMessage(), [], 500);
}
