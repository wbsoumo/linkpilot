<?php
// backend/api/crm/documents.php

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
        // Handle file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            sendJsonResponse('error', 'No file was uploaded or an upload error occurred.', [], 400);
        }
        
        $file = $_FILES['file'];
        $filename = basename($file['name']);
        $fileSize = $file['size'];
        
        $companyId = !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null;
        $contactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
        
        // Setup upload directory in the workspace
        $uploadDir = __DIR__ . '/../../uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate a unique path to prevent overwriting
        $uniqueName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
        $destination = $uploadDir . $uniqueName;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Relativize path for DB
            $dbPath = 'backend/uploads/documents/' . $uniqueName;
            
            $stmt = $db->prepare("INSERT INTO crm_documents (user_id, company_id, contact_id, filename, file_path, file_size) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $companyId, $contactId, $filename, $dbPath, $fileSize]);
            $docId = $db->lastInsertId();
            
            // Log to timeline
            $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, 'Document Uploaded', ?)");
            $timelineStmt->execute([$userId, $companyId, $contactId, "Uploaded document: '$filename'"]);
            
            sendJsonResponse('success', 'Document uploaded successfully', [
                'document_id' => $docId,
                'filename' => $filename,
                'file_path' => $dbPath,
                'file_size' => $fileSize
            ]);
        } else {
            sendJsonResponse('error', 'Failed to move uploaded file to target folder.', [], 500);
        }
    }
    
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $docId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        
        if ($docId <= 0) {
            sendJsonResponse('error', 'Document ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT * FROM crm_documents WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$docId, $userId]);
        $doc = $stmtCheck->fetch();
        if (!$doc) {
            sendJsonResponse('error', 'Document not found or access denied.', [], 404);
        }
        
        // Delete local file if exists
        $absolutePath = __DIR__ . '/../../' . $doc['file_path'];
        if (file_exists($absolutePath)) {
            @unlink($absolutePath);
        }
        
        // Delete DB record
        $stmt = $db->prepare("DELETE FROM crm_documents WHERE id = ? AND user_id = ?");
        $stmt->execute([$docId, $userId]);
        
        sendJsonResponse('success', "Document '{$doc['filename']}' deleted successfully.");
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
