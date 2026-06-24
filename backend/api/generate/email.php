<?php
// backend/api/generate/email.php

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
$recipientEmail = trim($input['email'] ?? '');
$recipientPhone = trim($input['phone'] ?? '');
$authorProfileUrl = trim($input['author_profile_url'] ?? '');

if (empty($postContent)) {
    sendJsonResponse('error', 'Post content is required for AI generation.', [], 400);
}

$db = Database::getConnection();

try {
    // 1. Fetch Sender Profile for Personalization
    $stmtUser = $db->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $senderUser = $stmtUser->fetch();
    
    $stmtProfile = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $stmtProfile->execute([$userId]);
    $senderProfile = $stmtProfile->fetch();
    
    if (!$senderUser) {
        sendJsonResponse('error', 'User details not found.', [], 404);
    }
    
    // Construct Sender Bio Details
    $senderName = $senderUser['name'];
    $senderEmail = $senderUser['email'];
    $senderType = $senderProfile['user_type'] ?? 'Professional';
    $senderTitle = $senderProfile['job_title'] ?? 'Professional';
    $senderExperience = ($senderProfile['experience_years'] ?? 0) . ' years';
    $senderSkills = $senderProfile['skills'] ?? '';
    $senderCompany = $senderProfile['company_name'] ?? '';
    $senderWebsite = $senderProfile['website'] ?? '';
    $senderPortfolio = $senderProfile['portfolio_url'] ?? '';
    $senderLinkedIn = $senderProfile['linkedin_url'] ?? '';
    $senderAbout = $senderProfile['about_me'] ?? '';
    
    // 2. Build Prompts
    $systemPrompt = "You are LinkPilot AI, a premium outreach generator.
Your goal is to write a highly personalized, human-sounding, professional outreach email based on a LinkedIn post.
Ensure it is NOT robotic, uses natural transition sentences, and focuses on building a relationship.
Do NOT use clichés (like 'I hope this email finds you well', 'deep dive', 'game changer', 'synergy').
Maintain a maximum word count of 250 words.
Structure the response strictly in JSON format with two keys:
'subject': A catchy, personalized email subject line.
'body': The HTML formatted email body (using <p> and <br> tags, no outer markdown blocks).

SENDER PROFILE:
Name: {$senderName}
Email: {$senderEmail}
Role/Type: {$senderType}
Title: {$senderTitle}
Company: {$senderCompany}
Experience: {$senderExperience}
Skills: {$senderSkills}
Website: {$senderWebsite}
Portfolio: {$senderPortfolio}
LinkedIn: {$senderLinkedIn}
About: {$senderAbout}
";

    $userPrompt = "LinkedIn Post Content:
\"\"\"
{$postContent}
\"\"\"

Post Details:
Author Name: {$authorName}
Author Company: {$companyName}
Recipient Email Address (if available): {$recipientEmail}

Generate a personalized outreach email to {$authorName} referencing their post. Build a bridge between their post and the sender's professional background where natural. Do not make up fake accomplishments. Keep it polite, clear, and focused. Return ONLY the JSON object.";

    // 3. Call OpenRouter
    $aiResult = callOpenRouter($systemPrompt, $userPrompt, $userId);
    $responseText = $aiResult['text'];
    $tokensUsed = $aiResult['tokens'];
    
    // Clean JSON response (sometimes LLMs return markdown fences)
    if (preg_match('/```json\s*(.*?)\s*```/s', $responseText, $matches)) {
        $jsonStr = $matches[1];
    } else {
        $jsonStr = $responseText;
    }
    
    $emailData = json_decode(trim($jsonStr), true);
    
    if (!$emailData || !isset($emailData['subject']) || !isset($emailData['body'])) {
        // Fallback parsing if JSON decode failed
        $subject = "Outreach regarding your post - " . ($authorName ?: 'LinkedIn Post');
        $body = str_replace("\n", "<br>", htmlspecialchars($responseText));
    } else {
        $subject = $emailData['subject'];
        $body = $emailData['body'];
    }
    
    // 4. Save to Lead Vault if author details exist
    if (!empty($authorName)) {
        $stmtLead = $db->prepare("
            INSERT INTO lead_vault (user_id, name, company_name, linkedin_url, email, phone_number, post_url, post_content, source) 
            VALUES (:user_id, :name, :company, :linkedin, :email, :phone, :post_url, :post_content, 'LinkedIn Extension')
        ");
        $stmtLead->execute([
            'user_id' => $userId,
            'name' => $authorName,
            'company' => $companyName ?: null,
            'linkedin' => $authorProfileUrl ?: null,
            'email' => $recipientEmail ?: null,
            'phone' => $recipientPhone ?: null,
            'post_url' => $postUrl ?: null,
            'post_content' => $postContent
        ]);
    }
    
    // 5. Store AI Generation record
    $fullGenerated = json_encode(['subject' => $subject, 'body' => $body]);
    $stmtGen = $db->prepare("INSERT INTO ai_generations (user_id, type, post_content, generated_content, tokens_used) VALUES (?, 'email', ?, ?, ?)");
    $stmtGen->execute([$userId, $postContent, $fullGenerated, $tokensUsed]);
    
    // 6. Update Stats and log activity
    updateStatistic($userId, 'total_requests');
    updateStatistic($userId, 'emails_generated');
    logActivity($userId, "Generated personalized outreach email for lead: " . ($authorName ?: 'Unknown'));
    
    sendJsonResponse('success', 'Email outreach generated successfully.', [
        'subject' => $subject,
        'body' => $body,
        'tokens_used' => $tokensUsed
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Error generating email: ' . $e->getMessage(), [], 500);
}
