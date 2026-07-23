<?php
// backend/api/crm/save_custom_template.php

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

$templateId = isset($input['id']) ? (int)$input['id'] : null;
$name = trim($input['name'] ?? '');
$subject = trim($input['subject'] ?? '');
$category = trim($input['category'] ?? 'Sales');
$tag = trim($input['tag'] ?? 'Outreach');
$jsonData = isset($input['json_data']) ? (is_array($input['json_data']) ? json_encode($input['json_data']) : $input['json_data']) : null;
$htmlContent = $input['html_content'] ?? '';

if (empty($name)) {
    sendJsonResponse('error', 'Template name is required.', [], 400);
}

$db = Database::getConnection();

try {
    if ($templateId) {
        // Update existing template
        $stmt = $db->prepare("UPDATE custom_email_templates SET name = ?, subject = ?, category = ?, tag = ?, json_data = ?, html_content = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$name, $subject, $category, $tag, $jsonData, $htmlContent, $templateId, $userId]);
        
        sendJsonResponse('success', 'Custom template updated successfully.', [
            'id' => $templateId
        ]);
    } else {
        // Insert new template
        $stmt = $db->prepare("INSERT INTO custom_email_templates (user_id, name, subject, category, tag, json_data, html_content) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $name, $subject, $category, $tag, $jsonData, $htmlContent]);
        $newId = $db->lastInsertId();
        
        sendJsonResponse('success', 'Custom template created successfully.', [
            'id' => (int)$newId
        ]);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database error: ' . $e->getMessage(), [], 500);
}
