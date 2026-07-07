<?php
// backend/api/external_apps/gmail.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../external_apps_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$recipientEmail = trim($input['recipient_email'] ?? '');
$subject = trim($input['subject'] ?? '');
$body = trim($input['body'] ?? '');

if (empty($recipientEmail) || empty($subject) || empty($body)) {
    sendJsonResponse('error', 'Recipient email, subject, and body are required.', [], 400);
}

try {
    if ($action === 'send') {
        $res = ExternalAppsHelper::sendGmailEmail($userId, $recipientEmail, $subject, $body);
        if ($res['status']) {
            // Find lead by recipient email to update status
            $db = Database::getConnection();
            $stmtFind = $db->prepare("SELECT id FROM lead_vault WHERE user_id = ? AND email = ? ORDER BY id DESC LIMIT 1");
            $stmtFind->execute([$userId, $recipientEmail]);
            $lead = $stmtFind->fetch();
            
            if ($lead) {
                $leadId = $lead['id'];
                $emailFullText = "Subject: $subject\n\n$body";
                
                $stmtUpdate = $db->prepare("
                    UPDATE lead_vault 
                    SET current_status = 'Contacted', last_contact_date = CURRENT_TIMESTAMP, generated_email = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$emailFullText, $leadId]);
            }
            
            sendJsonResponse('success', 'Email dispatched successfully via Gmail API.', ['message_id' => $res['message_id']]);
        } else {
            sendJsonResponse('error', $res['message'], [], 400);
        }
    } elseif ($action === 'draft') {
        $res = ExternalAppsHelper::createGmailDraft($userId, $recipientEmail, $subject, $body);
        if ($res['status']) {
            sendJsonResponse('success', 'Gmail draft created successfully.', ['draft_id' => $res['draft_id']]);
        } else {
            sendJsonResponse('error', $res['message'], [], 400);
        }
    } else {
        sendJsonResponse('error', 'Invalid action specified.', [], 400);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Gmail action failed: ' . $e->getMessage(), [], 500);
}
