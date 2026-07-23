<?php
// backend/api/crm/get_custom_templates.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];

$db = Database::getConnection();
$templateId = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    if ($templateId) {
        $stmt = $db->prepare("SELECT * FROM custom_email_templates WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$templateId, $userId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            sendJsonResponse('error', 'Template not found.', [], 404);
        }
        
        sendJsonResponse('success', 'Template details loaded.', [
            'template' => $template
        ]);
    } else {
        $stmt = $db->prepare("SELECT id, name, subject, category, tag, created_at, updated_at FROM custom_email_templates WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$userId]);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJsonResponse('success', 'Templates loaded.', [
            'templates' => $templates
        ]);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database error: ' . $e->getMessage(), [], 500);
}
