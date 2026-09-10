<?php
// backend/api/mobile/tasks.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

header('Content-Type: application/json');

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
        $filter = strtolower(trim($_GET['filter'] ?? 'all'));
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $todayDate = date('Y-m-d');

        $query = "SELECT t.*, co.name AS company_name, c.name AS contact_name 
                  FROM crm_tasks t 
                  LEFT JOIN crm_companies co ON t.company_id = co.id 
                  LEFT JOIN crm_contacts c ON t.contact_id = c.id 
                  WHERE t.user_id = :user_id";
        $params = ['user_id' => $userId];

        if ($search !== '') {
            $query .= " AND (t.title LIKE :search OR t.description LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $query .= " AND t.status = :status";
            $params['status'] = $status;
        }

        if ($filter === 'today') {
            $query .= " AND t.due_date = :today_date";
            $params['today_date'] = $todayDate;
        } elseif ($filter === 'overdue') {
            $query .= " AND t.due_date < :today_date AND t.status != 'completed'";
            $params['today_date'] = $todayDate;
        } elseif ($filter === 'upcoming') {
            $query .= " AND t.due_date > :today_date AND t.status != 'completed'";
            $params['today_date'] = $todayDate;
        } elseif ($filter === 'high') {
            $query .= " AND t.priority = 'high'";
        }

        $query .= " ORDER BY 
            CASE WHEN t.status = 'completed' THEN 2 ELSE 1 END, 
            t.due_date ASC, 
            CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll() ?: [];

        sendJsonResponse('success', 'Tasks loaded', ['tasks' => $tasks]);
    }

    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $title = trim($input['title'] ?? '');

        if (empty($title)) {
            sendJsonResponse('error', 'Task title is required.', [], 400);
        }

        $description = trim($input['description'] ?? '');
        $dueDate = !empty($input['due_date']) ? $input['due_date'] : null;
        $dueTime = !empty($input['due_time']) ? trim($input['due_time']) : null;
        $priority = trim($input['priority'] ?? 'medium');
        $status = trim($input['status'] ?? 'pending');

        $stmt = $db->prepare("INSERT INTO crm_tasks (user_id, title, description, due_date, due_time, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $description, $dueDate, $dueTime, $priority, $status]);
        $taskId = $db->lastInsertId();

        sendJsonResponse('success', 'Task created successfully', [
            'task' => [
                'id' => $taskId,
                'user_id' => $userId,
                'title' => $title,
                'description' => $description,
                'due_date' => $dueDate,
                'due_time' => $dueTime,
                'priority' => $priority,
                'status' => $status
            ]
        ]);
    }

    elseif ($method === 'PUT' || $method === 'UPDATE') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $taskId = (int)($input['id'] ?? $_GET['id'] ?? 0);

        if ($taskId <= 0) {
            sendJsonResponse('error', 'Task ID is required.', [], 400);
        }

        $stmtCheck = $db->prepare("SELECT * FROM crm_tasks WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$taskId, $userId]);
        $task = $stmtCheck->fetch();

        if (!$task) {
            sendJsonResponse('error', 'Task not found or access denied.', [], 404);
        }

        $title = trim($input['title'] ?? $task['title']);
        $description = trim($input['description'] ?? $task['description']);
        $dueDate = array_key_exists('due_date', $input) ? $input['due_date'] : $task['due_date'];
        $dueTime = array_key_exists('due_time', $input) ? $input['due_time'] : $task['due_time'];
        $priority = trim($input['priority'] ?? $task['priority']);
        $status = trim($input['status'] ?? $task['status']);

        $stmt = $db->prepare("UPDATE crm_tasks SET title = ?, description = ?, due_date = ?, due_time = ?, priority = ?, status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $description, $dueDate, $dueTime, $priority, $status, $taskId, $userId]);

        sendJsonResponse('success', 'Task updated successfully');
    }

    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $taskId = (int)($input['id'] ?? $_GET['id'] ?? 0);

        if ($taskId <= 0) {
            sendJsonResponse('error', 'Task ID is required.', [], 400);
        }

        $stmt = $db->prepare("DELETE FROM crm_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);

        sendJsonResponse('success', 'Task deleted successfully.');
    }

    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Task operation failed: ' . $e->getMessage(), [], 500);
}
