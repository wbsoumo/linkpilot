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
    $systemPrompt = "You are LinkPilot AI Lead Email Architect, specializing in Stripo.email-grade high-converting, ultra-premium HTML email templates (matching top Stripo.email/templates standards).

Your task is to generate or edit production-ready HTML email code that looks like a $1,000 professional Stripo email template.

STRIPO-GRADE DESIGN RULES & ARCHITECTURE:
1. MAX WIDTH & CONTAINER:
   - Outer background wrapper table: background-color: #f4f6f8 (or sleek dark #0f172a if dark theme requested).
   - Main content card table: max-width: 600px, width: 100%, margin: 0 auto, background-color: #ffffff, border-radius: 16px, box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), overflow: hidden.

2. HEADER & LOGO:
   - Clean header bar with brand logo placeholder or stylized header title, pre-header preview text support.

3. HERO SECTION:
   - Eye-catching banner hero area with bold gradient or solid background, large clear headline (24px - 32px font-weight: 800), engaging subheadline, and high-contrast primary CTA button.

4. CONTENT & MULTI-COLUMN CARDS:
   - Modular 2-column or 3-column feature cards with subtle borders (#e2e8f0 or #cbd5e1), padding 20px, rounded corners (12px), and clear typography.
   - High readability text: font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #334155.

5. PREMIUM CALL-TO-ACTION (CTA) BUTTONS:
   - Bulletproof HTML button (display: inline-block; padding: 14px 32px; background: linear-gradient/solid color; color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 14px; text-align: center; font-family: sans-serif;).

6. DYNAMIC VARIABLES & FOOTER:
   - Incorporate dynamic merge tags like {first_name}, {company_name}, {email} naturally.
   - Footer section with subtle social links, company address, and compliance unsubscribe link ({unsubscribe_url} or #).

7. DUAL MODE / ITERATIVE EDITS:
   - If current_html is provided, edit requested sections (e.g. colors, images, text, CTA) while retaining Stripo-level visual layout polish.

RESPONSE FORMAT:
Return strictly a JSON object with keys:
- \"reply\": A short friendly message describing the Stripo-style layout designed.
- \"html\": Complete, production-ready, bulletproof HTML code (with embedded <style> and inline CSS for full email client compatibility).
- \"subject_suggestion\": Catchy, high-open-rate subject line.

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
