<?php
// backend/api/generate/comment.php

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
$authorName = trim($input['author_name'] ?? '');

if (empty($postContent)) {
    sendJsonResponse('error', 'Post content is required for AI generation.', [], 400);
}

$db = Database::getConnection();

try {
    // 1. Fetch Sender Info
    $stmtUser = $db->prepare("SELECT name FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $senderUser = $stmtUser->fetch();
    
    $stmtProfile = $db->prepare("SELECT user_type, job_title FROM user_profiles WHERE user_id = ?");
    $stmtProfile->execute([$userId]);
    $senderProfile = $stmtProfile->fetch();
    
    $senderName = $senderUser['name'] ?? 'Professional';
    $senderTitle = $senderProfile['job_title'] ?? 'Professional';
    
    // 2. Build Prompts
    $systemPrompt = "You are LinkPilot AI.
Generate a natural, insightful, human-sounding LinkedIn comment (maximum 40 words) for a given post.
Avoid generic compliments like 'Great post!', 'Thanks for sharing!', or robotic language.
Add value, ask a question, or highlight a specific insight from the post content.
Make it sound like a real person engaging in the industry.

SENDER:
Name: {$senderName}
Title: {$senderTitle}
";

    $userPrompt = "LinkedIn Post Content:
\"\"\"
{$postContent}
\"\"\"

Post Author: {$authorName}

Generate the LinkedIn comment. Return ONLY the comment content.";

    // 3. Call OpenRouter
    $aiResult = callOpenRouter($systemPrompt, $userPrompt, $userId);
    $comment = $aiResult['text'];
    $tokensUsed = $aiResult['tokens'];
    
    // 4. Save to comment_generations
    $stmtComment = $db->prepare("INSERT INTO comment_generations (user_id, comment, status) VALUES (?, ?, 'generated')");
    $stmtComment->execute([$userId, $comment]);
    
    // 5. Store AI Generation record
    $stmtGen = $db->prepare("INSERT INTO ai_generations (user_id, type, post_content, generated_content, tokens_used) VALUES (?, 'comment', ?, ?, ?)");
    $stmtGen->execute([$userId, $postContent, $comment, $tokensUsed]);
    
    // 6. Update Stats and log activity
    updateStatistic($userId, 'total_requests');
    updateStatistic($userId, 'comments_generated');
    logActivity($userId, "Generated LinkedIn comment for post by: " . ($authorName ?: 'Unknown'));
    
    sendJsonResponse('success', 'LinkedIn comment generated successfully.', [
        'comment' => $comment,
        'tokens_used' => $tokensUsed
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Error generating comment: ' . $e->getMessage(), [], 500);
}
