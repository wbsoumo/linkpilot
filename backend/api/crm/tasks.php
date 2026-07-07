<?php
// backend/api/crm/tasks.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../external_apps_helper.php';

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
        $remarks = isset($input['remarks']) ? trim($input['remarks']) : null;
        
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
        $leadId = !empty($input['lead_id']) ? (int)$input['lead_id'] : null;
        
        $syncToCalendar = !empty($input['sync_to_calendar']) ? 1 : 0;
        
        $stmt = $db->prepare("INSERT INTO crm_tasks (user_id, company_id, contact_id, lead_id, title, description, due_date, status, priority, due_time, meet_link, remarks, sync_to_calendar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $companyId, $contactId, $leadId, $title, $description, $dueDate, $status, $priority, $dueTime, $meetLink, $remarks, $syncToCalendar
        ]);
        
        $taskId = $db->lastInsertId();

        // Sync to Google Calendar if enabled
        if ($syncToCalendar === 1) {
            try {
                if (ExternalAppsHelper::isGoogleConnected($userId)) {
                    ExternalAppsHelper::createGoogleCalendarEventFromTask($userId, $taskId, $title, $description, $dueDate, $dueTime);
                }
            } catch (Exception $e) {
                error_log("Google Calendar Task Sync failed: " . $e->getMessage());
            }
        }
        
        // Log on timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, lead_id, activity_type, description) VALUES (?, ?, ?, ?, 'Task Created', ?)");
        $timelineStmt->execute([$userId, $companyId, $contactId, $leadId, "Task '$title' was created (Due: $dueDate)."]);
        
        sendJsonResponse('success', 'Task created successfully', ['task_id' => $taskId]);
    }
    
    elseif ($method === 'GENERATE_MEET') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $taskId = (int)($input['task_id'] ?? $input['id'] ?? $_GET['id'] ?? 0);
        
        if ($taskId <= 0) {
            sendJsonResponse('error', 'Task ID is required.', [], 400);
        }

        try {
            $meetLink = ExternalAppsHelper::generateGoogleMeetForTask($userId, $taskId);
            sendJsonResponse('success', 'Google Meet Link generated successfully!', ['meet_link' => $meetLink]);
        } catch (Exception $e) {
            sendJsonResponse('error', $e->getMessage(), [], 400);
        }
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
        $stmtCheck = $db->prepare("SELECT id, title, status, company_id, contact_id, lead_id, due_date, due_time, meet_link, remarks, google_event_id, sync_to_calendar FROM crm_tasks WHERE id = ? AND user_id = ?");
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
        $remarks = isset($input['remarks']) ? trim($input['remarks']) : $task['remarks'];
        
        $syncToCalendar = isset($input['sync_to_calendar']) ? (!empty($input['sync_to_calendar']) ? 1 : 0) : (int)$task['sync_to_calendar'];

        $stmt = $db->prepare("UPDATE crm_tasks SET company_id = ?, contact_id = ?, lead_id = ?, title = ?, description = ?, due_date = ?, status = ?, priority = ?, due_time = ?, meet_link = ?, remarks = ?, sync_to_calendar = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $companyId, $contactId, $leadId, $title, $description, $dueDate, $status, $priority, $dueTime, $meetLink, $remarks, $syncToCalendar, $taskId, $userId
        ]);

        // Sync changes to Google Calendar
        try {
            if ($syncToCalendar === 1) {
                if (ExternalAppsHelper::isGoogleConnected($userId)) {
                    if (!empty($task['google_event_id'])) {
                        ExternalAppsHelper::updateGoogleCalendarEventFromTask($userId, $task['google_event_id'], $title, $description, $dueDate, $dueTime);
                    } else {
                        ExternalAppsHelper::createGoogleCalendarEventFromTask($userId, $taskId, $title, $description, $dueDate, $dueTime);
                    }
                }
            } else {
                if (!empty($task['google_event_id'])) {
                    if (ExternalAppsHelper::isGoogleConnected($userId)) {
                        ExternalAppsHelper::deleteGoogleCalendarEvent($userId, $task['google_event_id']);
                    }
                    $db->prepare("UPDATE crm_tasks SET google_event_id = NULL WHERE id = ?")->execute([$taskId]);
                }
            }
        } catch (Exception $e) {
            error_log("Google Calendar Task Sync update failed: " . $e->getMessage());
        }
        
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
        
        $stmtCheck = $db->prepare("SELECT id, title, google_event_id FROM crm_tasks WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$taskId, $userId]);
        $task = $stmtCheck->fetch();
        if (!$task) {
            sendJsonResponse('error', 'Task not found or access denied.', [], 404);
        }
        
        // Delete Google Calendar Event if synced
        try {
            if (!empty($task['google_event_id']) && ExternalAppsHelper::isGoogleConnected($userId)) {
                ExternalAppsHelper::deleteGoogleCalendarEvent($userId, $task['google_event_id']);
            }
        } catch (Exception $e) {
            error_log("Google Calendar Task delete failed: " . $e->getMessage());
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
