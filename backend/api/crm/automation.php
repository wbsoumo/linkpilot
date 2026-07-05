<?php
// backend/api/crm/automation.php

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
        $stmt = $db->prepare("SELECT * FROM automation_workflows WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $workflows = $stmt->fetchAll();
        
        // Decode actions_json for user frontend formatting
        foreach ($workflows as &$wf) {
            $wf['actions'] = json_decode($wf['actions_json'], true);
        }
        
        sendJsonResponse('success', 'Automation workflows retrieved successfully', ['workflows' => $workflows]);
    }
    
    elseif ($method === 'LOG_RUN') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;
        
        $wfId = isset($input['workflow_id']) ? (int)$input['workflow_id'] : null;
        $wfName = trim($input['workflow_name'] ?? 'Unnamed Workflow');
        $status = trim($input['status'] ?? 'success');
        $executionTime = isset($input['execution_time']) ? (float)$input['execution_time'] : 0.0;
        $errorMsg = isset($input['error_message']) ? trim($input['error_message']) : null;
        
        $stmt = $db->prepare("INSERT INTO workflow_execution_logs (user_id, workflow_id, workflow_name, status, execution_time, error_message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $wfId, $wfName, $status, $executionTime, $errorMsg]);
        
        sendJsonResponse('success', 'Execution log saved successfully');
    }
    
    elseif ($method === 'GET_LOGS') {
        $stmt = $db->prepare("SELECT * FROM workflow_execution_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$userId]);
        $logs = $stmt->fetchAll();
        sendJsonResponse('success', 'Execution logs retrieved successfully', ['logs' => $logs]);
    }
    
    elseif ($method === 'DUPLICATE') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;
        
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            sendJsonResponse('error', 'Workflow ID is required.', [], 400);
        }
        
        $stmtCheck = $db->prepare("SELECT * FROM automation_workflows WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$id, $userId]);
        $wf = $stmtCheck->fetch();
        if (!$wf) {
            sendJsonResponse('error', 'Workflow not found or access denied.', [], 404);
        }
        
        $newName = $wf['name'] . ' (Copy)';
        $stmt = $db->prepare("INSERT INTO automation_workflows (user_id, name, trigger_type, trigger_value, actions_json, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $newName, $wf['trigger_type'], $wf['trigger_value'], $wf['actions_json'], $wf['is_active']]);
        
        sendJsonResponse('success', 'Workflow duplicated successfully');
    }
    
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            sendJsonResponse('error', 'Workflow name is required.', [], 400);
        }
        
        $triggerType = trim($input['trigger_type'] ?? 'email_category_detected');
        $triggerValue = trim($input['trigger_value'] ?? '');
        $actions = $input['actions'] ?? [];
        
        $actionsJson = is_array($actions) ? json_encode($actions) : $actions;
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;
        
        $id = (int)($input['id'] ?? 0);
        
        if ($id > 0) {
            // Update
            $stmtCheck = $db->prepare("SELECT id FROM automation_workflows WHERE id = ? AND user_id = ?");
            $stmtCheck->execute([$id, $userId]);
            if (!$stmtCheck->fetch()) {
                sendJsonResponse('error', 'Workflow not found or access denied.', [], 404);
            }
            
            $stmt = $db->prepare("UPDATE automation_workflows SET name = ?, trigger_type = ?, trigger_value = ?, actions_json = ?, is_active = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $triggerType, $triggerValue, $actionsJson, $isActive, $id, $userId]);
            sendJsonResponse('success', 'Workflow updated successfully');
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO automation_workflows (user_id, name, trigger_type, trigger_value, actions_json, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $triggerType, $triggerValue, $actionsJson, $isActive]);
            $wfId = $db->lastInsertId();
            sendJsonResponse('success', 'Workflow created successfully', ['workflow_id' => $wfId]);
        }
    }
    
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        
        if ($id <= 0) {
            sendJsonResponse('error', 'Workflow ID is required.', [], 400);
        }
        
        $stmtCheck = $db->prepare("SELECT id, name FROM automation_workflows WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$id, $userId]);
        $wf = $stmtCheck->fetch();
        if (!$wf) {
            sendJsonResponse('error', 'Workflow not found or access denied.', [], 404);
        }
        
        $stmt = $db->prepare("DELETE FROM automation_workflows WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        
        sendJsonResponse('success', "Workflow '{$wf['name']}' deleted successfully.");
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
