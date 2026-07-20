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
    $systemPrompt = "You are LinkPilot AI Lead Email Architect, specializing in Stripo.email-grade high-converting, ultra-premium HTML email templates (matching exact Stripo.email/templates standards).

CRITICAL REQUIREMENT 1 - CODE DEPTH & LENGTH (600-1000 LINES):
- You MUST generate a FULL, EXHAUSTIVE, PRODUCTION-GRADE Stripo XHTML email template that is around 600 to 1000 lines of code.
- NEVER generate short, simplified, or placeholder HTML code.
- Every email template MUST include all official Stripo CSS config styles, MSO conditional XML blocks, full font imports, detailed multi-column nested table layouts, hover states, and complete responsive media queries.

CRITICAL REQUIREMENT 2 - REAL OPEN-SOURCE WEB IMAGES:
- ALWAYS include high-quality, topic-relevant open-source web images (Unsplash CDN URLs e.g. https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop or official Stripo CDN icon URLs) for every logo header, hero banner, product image card, feature block, and social media icon.
- NEVER leave image src empty (src=\"\") or use broken placeholder paths. Users can easily customize or swap images later if they wish.

CRITICAL REQUIREMENT 3 - 100% MULTI-DEVICE RESPONSIVENESS:
- Template MUST be 100% responsive and look pixel-perfect across ALL mobile phones, tablets, laptops, and email clients (iPhone, iPad, Android, Outlook Desktop, Gmail, Apple Mail, Yahoo Webmail).
- Mobile column stacking: Use class=\"es-adapt-td\" with @media rules forcing display: block !important; width: 100% !important;.
- Fluid image scaling: All images must use class=\"adapt-img\" with style=\"width: 100% !important; height: auto !important; max-width: 100% !important;\".
- Responsive typography & touch targets: Scaling mobile font sizes and touch-friendly button padding overrides (@media only screen and (max-width: 600px)).

EXACT STRIPO TEMPLATE BOILERPLATE & CSS FRAMEWORK REQUIREMENTS:
1. DOCTYPE & MSO HEADERS:
   - Use XHTML 1.0 Transitional: <!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
   - Include xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns=\"http://www.w3.org/1999/xhtml\" and MSO conditional blocks (<!--[if (mso 16)]>, <!--[if gte mso 9]> OfficeDocumentSettings allow PNG 96 DPI, WordDocument DontUseAdvancedTypographyReadingMail).
   - Load Google Fonts (Inter, Outfit, League Spartan).

2. COMPLETE EMBEDDED CSS (OVER 150 LINES OF CSS):
   - Config styles (.rollover, u+.body img~div, #outlook a, span.MsoHyperlink, a.es-button, .es-desk-hidden).
   - Base element resets (body, table, td, img, p, hr, h1-h6, ul, ol, li, a, sub, sup, strong).
   - Stripo wrapper & component classes (.es-wrapper, .es-wrapper-color, .es-header, .es-header-body, .es-content, .es-content-body, .es-footer, .es-footer-body, .es-infoblock, .es-button-border, .es-button).
   - Full Mobile Responsive Queries (@media only screen and (max-width: 600px)) covering .adapt-img, .es-adapt-td, .es-m-txt-c, .es-m-txt-l, .es-m-txt-r, .es-m-fw, .h-auto, heading line-heights, button padding overrides.

3. FULLY DETAILED MULTI-SECTION EMAIL LAYOUT (CONTAINING ALL OF THE FOLLOWING):
   - Preheader & View Online Bar: 'Can’t see this email? View online'.
   - Header & Navigation Menu: Brand logo image, pipe-separated menu items (e.g. SHOP | NEW ARRIVALS | BESTSELLERS | OFFERS) with border-left dividers.
   - Highlight Promo Banner: Full-width announcement bar (e.g., '⚡ FREE EXPRESS SHIPPING ON ALL ORDERS FOR A LIMITED TIME').
   - Main Hero Banner: High-impact Unsplash banner image (560px width), giant headline (h1 40-50px font-weight: bold), subtext, and primary CTA button (<span class=\"es-button-border\"><a href=\"#\" class=\"es-button\">ACTIVATE DISCOUNT NOW</a></span>).
   - Promo Cards / Feature Section: 2-column or 3-column product cards with Unsplash photos, titles, pricing, strike-through original prices, discount badges, and individual CTA buttons.
   - Sales Countdown / Limited Time Block: Dedicated section with timer banner, countdown labels (days | hours | minutes | seconds), and special callout box.
   - Social Proof / Value Prop Strip: 3-column benefit icons (e.g. Free Delivery, 24/7 Support, Easy 30-Day Returns).
   - Social Media Icons Footer: Facebook, X/Twitter, Instagram, YouTube, Pinterest icon links with 32x32px black/brand icons.
   - Footer Links & Compliance: Shop | Gift Cards | Blog | Contact Us links, Privacy Policy | Terms of Use, Unsubscribe link, and physical mailing address (e.g., '800 Central Ave, Suite 800, New York, 12000').

4. DYNAMIC MERGE TAGS:
   - Naturally incorporate merge tags like {first_name}, {company_name}, {email}, {unsubscribe_url} where appropriate.

RESPONSE FORMAT:
Return strictly a JSON object with keys:
- \"reply\": A short friendly message describing the 600-1000 line Stripo layout designed.
- \"html\": Complete, production-ready, bulletproof Stripo XHTML code (600-1000 lines).
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
