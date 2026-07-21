<?php
// backend/api/crm/save_chat_feedback.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Enable CORS
sendCorsHeaders();

// Validate Auth
try {
    $user = JWTHelper::requireAuth();
    $userId = $user['id'];
    $db = Database::getConnection();
} catch (Exception $e) {
    sendJsonResponse('error', 'Unauthorized: ' . $e->getMessage(), [], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$question = trim($input['question'] ?? '');
$answer = trim($input['answer'] ?? '');
$feedbackType = trim($input['feedback_type'] ?? '');

if (empty($question) || empty($answer) || empty($feedbackType)) {
    sendJsonResponse('error', 'Question, answer, and feedback_type are required.', [], 400);
}

if ($feedbackType !== 'like' && $feedbackType !== 'dislike') {
    sendJsonResponse('error', 'Invalid feedback type. Must be like or dislike.', [], 400);
}

try {
    // Dynamic table creation
    $db->exec("CREATE TABLE IF NOT EXISTS `ai_chat_feedback` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `question` TEXT NOT NULL,
        `answer` TEXT NOT NULL,
        `feedback_type` VARCHAR(10) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Check if duplicate feedback exists, update it if so, otherwise insert
    $stmtCheck = $db->prepare("SELECT id FROM ai_chat_feedback WHERE user_id = ? AND question = ? AND answer = ?");
    $stmtCheck->execute([$userId, $question, $answer]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $stmtUpdate = $db->prepare("UPDATE ai_chat_feedback SET feedback_type = ? WHERE id = ?");
        $stmtUpdate->execute([$feedbackType, $existing['id']]);
    } else {
        $stmtInsert = $db->prepare("INSERT INTO ai_chat_feedback (user_id, question, answer, feedback_type) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$userId, $question, $answer, $feedbackType]);
    }

    sendJsonResponse('success', 'Feedback saved successfully.', []);
} catch (Exception $e) {
    sendJsonResponse('error', 'Database error: ' . $e->getMessage(), [], 500);
}
