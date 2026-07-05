<?php
// backend/api/crm/tasks.php

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
        $status = trim($_GET['status'] ?? '');
        $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
        $contactId = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;
        $leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;
        $today = trim($_GET['today'] ?? '');
        
        $query = "SELECT t.*, co.name AS company_name, c.name AS contact_name FROM crm_tasks t LEFT JOIN crm_companies co ON t.company_id = co.id LEFT JOIN crm_contacts c ON t.contact_id = c.id WHERE t.user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($status !== '') {
            $query .= " AND t.status = :status";
            $params['status'] = $status;
        }
        if ($companyId > 0) {
            $query .= " AND t.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        if ($contactId > 0) {
            $query .= " AND t.contact_id = :contact_id";
            $params['contact_id'] = $contactId;
        }
        if ($leadId > 0) {
            $query .= " AND t.lead_id = :lead_id";
            $params['lead_id'] = $leadId;
        }
        if ($today === 'true') {
            $query .= " AND t.due_date = CURRENT_DATE()";
        }
        
        $query .= " ORDER BY t.due_date ASC, t.priority DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        
        sendJsonResponse('success', 'Tasks retrieved successfully', ['tasks' => $tasks]);
    }
    
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $title = trim($input['title'] ?? '');
        if (empty($title)) {
            sendJsonResponse('error', 'Task title is required.', [], 400);
        }
        
        $description = trim($input['description'] ?? '');
        $dueDate = !empty($input['due_date']) ? $input['due_date'] : null;
        $priority = trim($input['priority'] ?? 'medium');
        $status = trim($input['status'] ?? 'pending');
        $dueTime = !empty($input['due_time']) ? trim($input['due_time']) : null;
        $meetLink = !empty($input['meet_link']) ? trim($input['meet_link']) : null;
        
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
        $leadId = !empty($input['lead_id']) ? (int)$input['lead_id'] : null;
        
        $stmt = $db->prepare("INSERT INTO crm_tasks (user_id, company_id, contact_id, lead_id, title, description, due_date, status, priority, due_time, meet_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $companyId, $contactId, $leadId, $title, $description, $dueDate, $status, $priority, $dueTime, $meetLink
        ]);
        
        $taskId = $db->lastInsertId();
        
        // Log on timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, lead_id, activity_type, description) VALUES (?, ?, ?, ?, 'Task Created', ?)");
        $timelineStmt->execute([$userId, $companyId, $contactId, $leadId, "Task '$title' was created (Due: $dueDate)."]);
        
        sendJsonResponse('success', 'Task created successfully', ['task_id' => $taskId]);
    }
    
    elseif ($method === 'PUT' || $method === 'UPDATE') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $taskId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($taskId <= 0) {
            sendJsonResponse('error', 'Task ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT id, title, status, company_id, contact_id, lead_id, due_time, meet_link FROM crm_tasks WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$taskId, $userId]);
        $task = $stmtCheck->fetch();
        if (!$task) {
            sendJsonResponse('error', 'Task not found or access denied.', [], 404);
        }
        
        $title = trim($input['title'] ?? $task['title']);
        $description = trim($input['description'] ?? '');
        $dueDate = !empty($input['due_date']) ? $input['due_date'] : null;
        $priority = trim($input['priority'] ?? 'medium');
        $status = trim($input['status'] ?? $task['status']);
        $dueTime = isset($input['due_time']) ? (!empty($input['due_time']) ? trim($input['due_time']) : null) : $task['due_time'];
        $meetLink = isset($input['meet_link']) ? (!empty($input['meet_link']) ? trim($input['meet_link']) : null) : $task['meet_link'];
        
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : $task['company_id'];
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : $task['contact_id'];
        $leadId = !empty($input['lead_id']) ? (int)$input['lead_id'] : $task['lead_id'];
        
        $stmt = $db->prepare("UPDATE crm_tasks SET company_id = ?, contact_id = ?, lead_id = ?, title = ?, description = ?, due_date = ?, status = ?, priority = ?, due_time = ?, meet_link = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $companyId, $contactId, $leadId, $title, $description, $dueDate, $status, $priority, $dueTime, $meetLink, $taskId, $userId
        ]);
        
        // Log changes
        if ($status !== $task['status']) {
            $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, lead_id, activity_type, description) VALUES (?, ?, ?, ?, 'Task Completed', ?)");
            $timelineStmt->execute([$userId, $companyId, $contactId, $leadId, "Task '$title' was marked as $status."]);
        }
        
        sendJsonResponse('success', 'Task updated successfully');
    }
    
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        
        if ($taskId <= 0) {
            sendJsonResponse('error', 'Task ID is required.', [], 400);
        }
        
        $stmtCheck = $db->prepare("SELECT id, title FROM crm_tasks WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$taskId, $userId]);
        $task = $stmtCheck->fetch();
        if (!$task) {
            sendJsonResponse('error', 'Task not found or access denied.', [], 404);
        }
        
        $stmt = $db->prepare("DELETE FROM crm_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        
        sendJsonResponse('success', "Task '{$task['title']}' deleted successfully.");
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
