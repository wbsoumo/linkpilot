<?php
// backend/api/crm/delete_custom_template.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$templateId = isset($input['id']) ? (int)$input['id'] : null;

if (!$templateId) {
    sendJsonResponse('error', 'Template ID is required.', [], 400);
}

$db = Database::getConnection();

try {
    $stmt = $db->prepare("DELETE FROM custom_email_templates WHERE id = ? AND user_id = ?");
    $stmt->execute([$templateId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        sendJsonResponse('success', 'Custom template deleted successfully.');
    } else {
        sendJsonResponse('error', 'Template not found or not owned by you.', [], 404);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database error: ' . $e->getMessage(), [], 500);
}
