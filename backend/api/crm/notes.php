<?php
// backend/api/crm/notes.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $content = trim($input['content'] ?? '');
        if (empty($content)) {
            sendJsonResponse('error', 'Note content is required.', [], 400);
        }
        
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
        
        $stmt = $db->prepare("INSERT INTO crm_notes (user_id, company_id, contact_id, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $companyId, $contactId, $content]);
        $noteId = $db->lastInsertId();
        
        // Log on timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, 'Note Added', ?)");
        $snippet = strlen($content) > 50 ? substr($content, 0, 50) . '...' : $content;
        $timelineStmt->execute([$userId, $companyId, $contactId, "A note was added: '$snippet'"]);
        
        sendJsonResponse('success', 'Note added successfully', ['note_id' => $noteId]);
    }
    
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $noteId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        
        if ($noteId <= 0) {
            sendJsonResponse('error', 'Note ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT id, company_id, contact_id FROM crm_notes WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$noteId, $userId]);
        $note = $stmtCheck->fetch();
        if (!$note) {
            sendJsonResponse('error', 'Note not found or access denied.', [], 404);
        }
        
        $stmt = $db->prepare("DELETE FROM crm_notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$noteId, $userId]);
        
        sendJsonResponse('success', 'Note deleted successfully.');
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
