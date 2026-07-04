<?php
// backend/api/crm/meetings.php

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
    if ($method === 'GET') {
        $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
        $contactId = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;
        $upcoming = trim($_GET['upcoming'] ?? '');
        
        $query = "SELECT m.*, co.name AS company_name, c.name AS contact_name FROM crm_meetings m LEFT JOIN crm_companies co ON m.company_id = co.id LEFT JOIN crm_contacts c ON m.contact_id = c.id WHERE m.user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($companyId > 0) {
            $query .= " AND m.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        if ($contactId > 0) {
            $query .= " AND m.contact_id = :contact_id";
            $params['contact_id'] = $contactId;
        }
        if ($upcoming === 'true') {
            $query .= " AND m.start_time >= NOW() AND m.status = 'scheduled'";
        }
        
        $query .= " ORDER BY m.start_time ASC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $meetings = $stmt->fetchAll();
        
        sendJsonResponse('success', 'Meetings retrieved successfully', ['meetings' => $meetings]);
    }
    
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $title = trim($input['title'] ?? '');
        if (empty($title)) {
            sendJsonResponse('error', 'Meeting title is required.', [], 400);
        }
        
        $startTime = !empty($input['start_time']) ? $input['start_time'] : null;
        if (!$startTime) {
            sendJsonResponse('error', 'Meeting start time is required.', [], 400);
        }
        
        $endTime = !empty($input['end_time']) ? $input['end_time'] : null;
        $description = trim($input['description'] ?? '');
        $location = trim($input['location'] ?? '');
        $status = trim($input['status'] ?? 'scheduled');
        
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
        
        $stmt = $db->prepare("INSERT INTO crm_meetings (user_id, company_id, contact_id, title, description, start_time, end_time, location, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $companyId, $contactId, $title, $description, $startTime, $endTime, $location, $status
        ]);
        
        $meetingId = $db->lastInsertId();
        
        // Log on timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, 'Meeting Scheduled', ?)");
        $timelineStmt->execute([$userId, $companyId, $contactId, "Meeting '$title' was scheduled for $startTime."]);
        
        sendJsonResponse('success', 'Meeting created successfully', ['meeting_id' => $meetingId]);
    }
    
    elseif ($method === 'PUT' || $method === 'UPDATE') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $meetingId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($meetingId <= 0) {
            sendJsonResponse('error', 'Meeting ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT id, title, status, company_id, contact_id FROM crm_meetings WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$meetingId, $userId]);
        $meeting = $stmtCheck->fetch();
        if (!$meeting) {
            sendJsonResponse('error', 'Meeting not found or access denied.', [], 404);
        }
        
        $title = trim($input['title'] ?? $meeting['title']);
        $startTime = !empty($input['start_time']) ? $input['start_time'] : null;
        $endTime = !empty($input['end_time']) ? $input['end_time'] : null;
        $description = trim($input['description'] ?? '');
        $location = trim($input['location'] ?? '');
        $status = trim($input['status'] ?? $meeting['status']);
        
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : $meeting['company_id'];
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : $meeting['contact_id'];
        
        $stmt = $db->prepare("UPDATE crm_meetings SET company_id = ?, contact_id = ?, title = ?, description = ?, start_time = ?, end_time = ?, location = ?, status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $companyId, $contactId, $title, $description, $startTime, $endTime, $location, $status, $meetingId, $userId
        ]);
        
        // Log changes
        if ($status !== $meeting['status']) {
            $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, 'Meeting Status Changed', ?)");
            $timelineStmt->execute([$userId, $companyId, $contactId, "Meeting '$title' status was set to $status."]);
        }
        
        sendJsonResponse('success', 'Meeting updated successfully');
    }
    
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $meetingId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        
        if ($meetingId <= 0) {
            sendJsonResponse('error', 'Meeting ID is required.', [], 400);
        }
        
        $stmtCheck = $db->prepare("SELECT id, title FROM crm_meetings WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$meetingId, $userId]);
        $meeting = $stmtCheck->fetch();
        if (!$meeting) {
            sendJsonResponse('error', 'Meeting not found or access denied.', [], 404);
        }
        
        $stmt = $db->prepare("DELETE FROM crm_meetings WHERE id = ? AND user_id = ?");
        $stmt->execute([$meetingId, $userId]);
        
        sendJsonResponse('success', "Meeting '{$meeting['title']}' deleted successfully.");
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
