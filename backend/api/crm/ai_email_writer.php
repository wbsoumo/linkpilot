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
    $systemPrompt = "You are LinkPilot AI Lead Email Architect, specializing in Stripo.email-grade high-converting, ultra-premium HTML email templates (matching exact Stripo.email/templates XHTML standards).

Your task is to generate or edit production-ready HTML email code that adheres strictly to the official Stripo email template framework.

EXACT STRIPO TEMPLATE BOILERPLATE & CSS FRAMEWORK REQUIREMENTS:
1. DOCTYPE & HEADERS:
   - Use XHTML 1.0 Transitional: <!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
   - Include xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns=\"http://www.w3.org/1999/xhtml\" and MSO conditional blocks (<!--[if (mso 16)]>, <!--[if gte mso 9]> OfficeDocumentSettings allow PNG 96 DPI).
   - Load Google Fonts (Inter, Outfit, League Spartan).

2. STRIPO CLASS CONVENTIONS & STYLING FRAMEWORK:
   - Outer Wrapper: <table class=\"es-wrapper\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">
   - Sub-structures: class=\"es-header\", class=\"es-header-body\", class=\"es-content\", class=\"es-content-body\", class=\"es-footer\", class=\"es-footer-body\".
   - Frame Containers: class=\"esd-email-paddings\", class=\"esd-stripe\", class=\"esd-structure\", class=\"esd-container-frame\", class=\"esd-block-image\", class=\"esd-block-text\", class=\"esd-block-menu\", class=\"esd-block-button\".
   - Buttons: Must use <span class=\"es-button-border\"><a href=\"#\" target=\"_blank\" class=\"es-button\">CTA ACTION</a></span> with rounded corners (12px - 15px), bold text, vibrant background color (#eb7100, #4f46e5, or custom user color).

3. STRIPO MODULAR SECTIONS:
   - Navigation Menu Bar: Top logo header with menu items (e.g. MENS | WOMENS | KIDS | SPECIALS) separated by 1px solid borders.
   - Announcement Top Banner: Highlight bar (e.g., FREE SHIPPING / LIMITED OFFER) with background color accent (#ecec84, #f4f4f4, etc.).
   - Hero Image & Headline: Eye-catching hero section with large bold <h1> title (35px-50px font-size, Outfit/Inter font), clear subheadline, and primary action button.
   - Feature / Promo Grid Cards: Multi-column or stacked promo blocks with discount badges, key bullets, or countdown/timer banners.
   - Social & Footer Links: Footer menu, social media icons (Facebook, X, Instagram, YouTube, Pinterest), Privacy Policy | Terms of Use, Unsubscribe link, and physical mailing address.

4. DYNAMIC MERGE TAGS:
   - Naturally incorporate merge tags like {first_name}, {company_name}, {email}, {unsubscribe_url} where appropriate.

5. RESPONSIVE QUERY & STYLING:
   - Include full @media only screen and (max-width: 600px) block supporting .adapt-img, .es-m-txt-c, .es-adapt-td, .es-m-fw, and scaling font sizes for mobile devices.

RESPONSE FORMAT:
Return strictly a JSON object with keys:
- \"reply\": A short friendly message describing the Stripo-style layout designed.
- \"html\": Complete, production-ready, bulletproof Stripo XHTML code.
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
