<?php
// backend/api/crm/automation.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../libs/PHPMailer/SMTP.php';
require_once __DIR__ . '/../../libs/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    
    elseif ($method === 'TEST_SEND_EMAIL') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;
        
        $recipient = filter_var(trim($input['recipient'] ?? ''), FILTER_VALIDATE_EMAIL);
        $subject = trim($input['subject'] ?? 'Workflow Builder Test Email');
        $body = trim($input['body'] ?? 'This is a test notification from LinkPilot Workflow Builder.');
        
        if (!$recipient) {
            sendJsonResponse('error', 'A valid override recipient email is required to run the test.', [], 400);
        }
        
        $stmtSmtp = $db->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption FROM imap_smtp_configurations WHERE user_id = ?");
        $stmtSmtp->execute([$userId]);
        $smtp = $stmtSmtp->fetch();
        
        $mail = new PHPMailer(true);
        try {
            if ($smtp && !empty($smtp['smtp_host'])) {
                $mail->isSMTP();
                $mail->Host = $smtp['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtp['smtp_username'];
                $mail->Password = decryptData($smtp['smtp_password']);
                $mail->Port = (int)$smtp['smtp_port'];
                $mail->SMTPSecure = strtolower($smtp['smtp_encryption']) === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->isMail();
            }
            
            $mail->setFrom($smtp['smtp_username'] ?? 'noreply@linkpilot.ai', 'LinkPilot CRM Workflow Test');
            $mail->addAddress($recipient);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = "
                <div style='font-family: sans-serif; padding: 20px; color: #1e293b; background-color: #f8fafc;'>
                    <h2 style='color: #4f46e5;'>LinkPilot Workflow Test Dispatch</h2>
                    <p>Hello,</p>
                    <p>This email was triggered by running a test inside your LinkPilot CRM Visual Workflow Builder.</p>
                    <div style='background-color: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin: 15px 0;'>
                        <p style='margin: 0 0 10px 0;'><strong>Test Email Content:</strong></p>
                        <p style='margin: 0; white-space: pre-wrap;'>{$body}</p>
                    </div>
                    <p style='font-size: 11px; color: #64748b;'>You received this because your address was specified as the test override email recipient.</p>
                </div>
            ";
            
            $mail->send();
            sendJsonResponse('success', 'Test email dispatched successfully!');
        } catch (Exception $e) {
            try {
                $headers = "From: " . ($smtp['smtp_username'] ?? 'noreply@linkpilot.ai') . "\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                mail($recipient, $subject, $mail->Body, $headers);
                sendJsonResponse('success', 'Test email dispatched successfully (via fallback mailer)!');
            } catch (\Throwable $ex) {
                sendJsonResponse('error', 'Failed to dispatch SMTP email: ' . $mail->ErrorInfo);
            }
        }
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
