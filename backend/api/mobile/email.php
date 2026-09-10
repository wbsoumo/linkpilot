<?php
// backend/api/mobile/email.php

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
    // Check Gmail/SMTP connection status
    $stmtSmtp = $db->prepare("SELECT COUNT(*) FROM smtp_accounts WHERE user_id = ?");
    $stmtSmtp->execute([$userId]);
    $isGmailConnected = ((int)$stmtSmtp->fetchColumn()) > 0;

    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $emailId = (int)$_GET['id'];
            $stmt = $db->prepare("SELECT * FROM received_emails WHERE id = ? AND user_id = ?");
            $stmt->execute([$emailId, $userId]);
            $email = $stmt->fetch();

            if (!$email) {
                $stmtSent = $db->prepare("SELECT id, recipient_email AS sender_email, recipient_email AS sender_name, subject, body AS body_html, body AS body_text, created_at AS received_date, 1 AS is_read, 0 AS is_starred, 'Sent' AS category FROM sent_emails WHERE id = ? AND user_id = ?");
                $stmtSent->execute([$emailId, $userId]);
                $email = $stmtSent->fetch();
            }

            if (!$email) {
                sendJsonResponse('error', 'Email not found', [], 404);
            }

            // Auto-mark as read
            if (isset($email['is_read']) && !$email['is_read']) {
                $db->prepare("UPDATE received_emails SET is_read = 1 WHERE id = ?")->execute([$emailId]);
                $email['is_read'] = 1;
            }

            sendJsonResponse('success', 'Email detail retrieved', [
                'email' => $email,
                'connected' => $isGmailConnected
            ]);
        } else {
            // List emails
            $search = trim($_GET['search'] ?? '');
            $folder = strtolower(trim($_GET['folder'] ?? 'inbox'));

            if ($folder === 'sent') {
                $query = "SELECT id, recipient_email AS sender_email, recipient_email AS sender_name, subject, body AS body_text, created_at AS received_date, 1 AS is_read, 0 AS is_starred, 'Sent' AS category 
                          FROM sent_emails 
                          WHERE user_id = :user_id";
                $params = ['user_id' => $userId];
                if ($search !== '') {
                    $query .= " AND (recipient_email LIKE :search OR subject LIKE :search OR body LIKE :search)";
                    $params['search'] = '%' . $search . '%';
                }
                $query .= " ORDER BY created_at DESC LIMIT 50";
            } else {
                $query = "SELECT id, sender_name, sender_email, subject, body_text, received_date, is_read, is_starred, category, priority 
                          FROM received_emails 
                          WHERE user_id = :user_id";
                $params = ['user_id' => $userId];
                if ($search !== '') {
                    $query .= " AND (sender_name LIKE :search OR sender_email LIKE :search OR subject LIKE :search OR body_text LIKE :search)";
                    $params['search'] = '%' . $search . '%';
                }
                $query .= " ORDER BY received_date DESC LIMIT 50";
            }

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $emails = $stmt->fetchAll() ?: [];

            sendJsonResponse('success', 'Emails loaded', [
                'emails' => $emails,
                'connected' => $isGmailConnected,
                'folder' => $folder
            ]);
        }
    }

    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $recipient = trim($input['recipient_email'] ?? $input['recipient'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $body = trim($input['body'] ?? '');

        if (empty($recipient) || empty($subject) || empty($body)) {
            sendJsonResponse('error', 'Recipient email, subject, and body are required.', [], 400);
        }

        // Insert into sent_emails
        $stmt = $db->prepare("INSERT INTO sent_emails (user_id, recipient_email, subject, body, status) VALUES (?, ?, ?, ?, 'sent')");
        $stmt->execute([$userId, $recipient, $subject, $body]);
        $sentId = $db->lastInsertId();

        sendJsonResponse('success', 'Email sent successfully', ['sent_id' => $sentId]);
    }

    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }

} catch (Exception $e) {
    sendJsonResponse('error', 'Email operation failed: ' . $e->getMessage(), [], 500);
}
