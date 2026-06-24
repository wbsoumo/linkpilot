<?php
// backend/api/generate/whatsapp.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$postContent = trim($input['post_content'] ?? '');
$postUrl = trim($input['post_url'] ?? '');
$authorName = trim($input['author_name'] ?? '');
$companyName = trim($input['company_name'] ?? '');
$phone = trim($input['phone'] ?? '');

if (empty($postContent)) {
    sendJsonResponse('error', 'Post content is required for AI generation.', [], 400);
}

$db = Database::getConnection();

try {
    // 1. Fetch Sender Profile
    $stmtUser = $db->prepare("SELECT name FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $senderUser = $stmtUser->fetch();
    
    $stmtProfile = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $stmtProfile->execute([$userId]);
    $senderProfile = $stmtProfile->fetch();
    
    $senderName = $senderUser['name'] ?? 'Professional';
    $senderTitle = $senderProfile['job_title'] ?? 'Professional';
    $senderCompany = $senderProfile['company_name'] ?? '';
    
    // 2. Build Prompts
    $systemPrompt = "You are LinkPilot AI.
Write a short, friendly, yet professional WhatsApp outreach message (maximum 60 words).
Ensure it is conversational, highly personalized, and refers to their recent LinkedIn post.
Avoid robotic intros. Include a clear call to action.
Include emojis where appropriate to keep it engaging.

SENDER:
Name: {$senderName}
Title: {$senderTitle}
Company: {$senderCompany}
";

    $userPrompt = "LinkedIn Post Content:
\"\"\"
{$postContent}
\"\"\"

Recipient Name: {$authorName}

Generate the WhatsApp outreach message. Return ONLY the message content.";

    // 3. Call OpenRouter
    $aiResult = callOpenRouter($systemPrompt, $userPrompt);
    $message = $aiResult['text'];
    $tokensUsed = $aiResult['tokens'];
    
    // 4. Save to whatsapp_generations
    $stmtWhatsapp = $db->prepare("INSERT INTO whatsapp_generations (user_id, phone_number, message, status) VALUES (?, ?, ?, 'generated')");
    $stmtWhatsapp->execute([$userId, empty($phone) ? null : $phone, $message]);
    
    // 5. Store AI Generation record
    $stmtGen = $db->prepare("INSERT INTO ai_generations (user_id, type, post_content, generated_content, tokens_used) VALUES (?, 'whatsapp', ?, ?, ?)");
    $stmtGen->execute([$userId, $postContent, $message, $tokensUsed]);
    
    // 6. Update Stats and log activity
    updateStatistic($userId, 'total_requests');
    updateStatistic($userId, 'whatsapp_generated');
    logActivity($userId, "Generated WhatsApp outreach message for: " . ($authorName ?: 'Unknown'));
    
    sendJsonResponse('success', 'WhatsApp message generated successfully.', [
        'message' => $message,
        'tokens_used' => $tokensUsed
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Error generating WhatsApp message: ' . $e->getMessage(), [], 500);
}
