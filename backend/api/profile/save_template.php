<?php
// backend/api/profile/save_template.php

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
$templateId = trim($input['active_email_template'] ?? '');

if (empty($templateId)) {
    sendJsonResponse('error', 'Template ID is required.', [], 400);
}

// Validate template ID
require_once __DIR__ . '/../../email_template_helper.php';
$templates = EmailTemplateHelper::getTemplates();
$valid = false;
foreach ($templates as $t) {
    if ($t['id'] === $templateId) {
        $valid = true;
        break;
    }
}

if (!$valid) {
    sendJsonResponse('error', 'Invalid Template ID.', [], 400);
}

$db = Database::getConnection();

try {
    $stmt = $db->prepare("UPDATE users SET active_email_template = ? WHERE id = ?");
    $stmt->execute([$templateId, $userId]);
    
    logActivity($userId, "Updated active email template layout to: " . $templateId);
    
    sendJsonResponse('success', 'Email template layout saved successfully.');
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error saving template selection: ' . $e->getMessage(), [], 500);
}
