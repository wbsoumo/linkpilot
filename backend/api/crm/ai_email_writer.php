<?php
// backend/api/crm/ai_email_writer.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../wallet_helper.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Validate Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Read POST payload
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$prompt = trim($input['prompt'] ?? '');
$currentHtml = trim($input['current_html'] ?? '');
$history = $input['history'] ?? [];

if (empty($prompt)) {
    sendJsonResponse('error', 'Prompt cannot be empty', [], 400);
}

try {
    $systemPrompt = "You are LinkPilot AI Email Architect & HTML Template Developer. Your job is to generate responsive, high-converting HTML email templates or intelligently edit existing HTML email templates based on user instructions.

Rules:
1. Generate clean, modern, mobile-friendly inline-styled HTML suitable for email clients (Gmail, Outlook, Apple Mail).
2. Include dynamic tags like {first_name}, {company_name}, {email} where relevant.
3. If current_html is provided and non-empty, edit ONLY the requested parts (colors, text, sections, buttons) while preserving existing working template structure.
4. Return strictly a JSON object with keys:
   - \"reply\": A short friendly message explaining what was created or changed (e.g. \"Generated a modern product launch email with a dark purple CTA\").
   - \"html\": The full, complete, production-ready HTML string for the email template.
   - \"subject_suggestion\": A catchy subject line proposal for this email.

Do NOT include markdown formatting outside the JSON object. Output raw JSON ONLY.";

    $promptBody = "";
    if (!empty($currentHtml)) {
        $promptBody .= "Existing HTML Template Code:\n```html\n" . substr($currentHtml, 0, 5000) . "\n```\n\n";
    }

    if (count($history) > 0) {
        $promptBody .= "Previous Chat Context:\n";
        foreach ($history as $h) {
            $roleName = ($h['role'] === 'user') ? 'User' : 'Assistant';
            $promptBody .= "$roleName: " . $h['content'] . "\n";
        }
        $promptBody .= "\n";
    }

    $promptBody .= "User Request: $prompt\n\nAssistant JSON Output:";

    $aiResult = callAI($systemPrompt, $promptBody, $userId);
    $rawReply = trim($aiResult['text']);

    // Attempt to extract JSON if LLM wrapped it in markdown code blocks
    if (preg_match('/```json\s*(.*?)\s*```/s', $rawReply, $matches)) {
        $rawReply = trim($matches[1]);
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $rawReply, $matches)) {
        $rawReply = trim($matches[1]);
    }

    $parsed = json_decode($rawReply, true);
    if (!$parsed || !isset($parsed['html'])) {
        // Fallback HTML generation if raw JSON parse failed
        $cleanHtml = $rawReply;
        if (preg_match('/```html\s*(.*?)\s*```/s', $rawReply, $m)) {
            $cleanHtml = trim($m[1]);
        } elseif (preg_match('/<div.*<\/div>/s', $rawReply, $m)) {
            $cleanHtml = trim($m[0]);
        }
        
        $parsed = [
            'reply' => 'Email template updated as requested.',
            'html' => $cleanHtml,
            'subject_suggestion' => 'Special Update from {company_name}'
        ];
    }

    sendJsonResponse('success', 'Email generated successfully', [
        'reply' => $parsed['reply'] ?? 'Email written successfully!',
        'html' => $parsed['html'] ?? '',
        'subject_suggestion' => $parsed['subject_suggestion'] ?? ''
    ]);

} catch (Throwable $e) {
    sendJsonResponse('error', 'AI Email Writer error: ' . $e->getMessage(), [], 500);
}
