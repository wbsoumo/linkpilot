<?php
// backend/api/mobile/whatsapp.php

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
    // Check WhatsApp connection status
    $stmtWaCount = $db->prepare("SELECT COUNT(*) FROM whatsapp_contacts WHERE user_id = ?");
    $stmtWaCount->execute([$userId]);
    $isWhatsAppConnected = ((int)$stmtWaCount->fetchColumn()) > 0;

    if ($method === 'GET') {
        if ($action === 'messages' || isset($_GET['wa_contact_id'])) {
            $waContactId = (int)($_GET['wa_contact_id'] ?? $_GET['id'] ?? 0);
            if ($waContactId <= 0) {
                sendJsonResponse('error', 'wa_contact_id is required', [], 400);
            }

            $stmtContact = $db->prepare("SELECT * FROM whatsapp_contacts WHERE id = ? AND user_id = ?");
            $stmtContact->execute([$waContactId, $userId]);
            $contact = $stmtContact->fetch();

            if (!$contact) {
                sendJsonResponse('error', 'Conversation not found', [], 404);
            }

            // Load messages
            $stmtMsgs = $db->prepare("
                SELECT id, wa_contact_id, sender_type, message_text AS body, created_at, status 
                FROM whatsapp_messages 
                WHERE user_id = ? AND wa_contact_id = ? 
                ORDER BY created_at ASC 
                LIMIT 100
            ");
            $stmtMsgs->execute([$userId, $waContactId]);
            $messages = $stmtMsgs->fetchAll() ?: [];

            // Clear unread count
            $db->prepare("UPDATE whatsapp_contacts SET unread_count = 0 WHERE id = ? AND user_id = ?")->execute([$waContactId, $userId]);

            sendJsonResponse('success', 'Messages loaded', [
                'contact' => $contact,
                'messages' => $messages,
                'connected' => $isWhatsAppConnected
            ]);
        } else {
            // List conversations
            $search = trim($_GET['search'] ?? '');
            $query = "SELECT id, wa_id, name, avatar, last_message, last_message_time, unread_count, tag, reply_required 
                      FROM whatsapp_contacts 
                      WHERE user_id = :user_id";
            $params = ['user_id' => $userId];

            if ($search !== '') {
                $query .= " AND (name LIKE :search OR wa_id LIKE :search OR last_message LIKE :search)";
                $params['search'] = '%' . $search . '%';
            }

            $query .= " ORDER BY last_message_time DESC LIMIT 50";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $conversations = $stmt->fetchAll() ?: [];

            sendJsonResponse('success', 'Conversations loaded', [
                'conversations' => $conversations,
                'connected' => $isWhatsAppConnected
            ]);
        }
    }

    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $waContactId = (int)($input['wa_contact_id'] ?? 0);
        $body = trim($input['body'] ?? '');

        if ($waContactId <= 0 || empty($body)) {
            sendJsonResponse('error', 'wa_contact_id and body are required.', [], 400);
        }

        $stmtContact = $db->prepare("SELECT * FROM whatsapp_contacts WHERE id = ? AND user_id = ?");
        $stmtContact->execute([$waContactId, $userId]);
        $contact = $stmtContact->fetch();

        if (!$contact) {
            sendJsonResponse('error', 'Conversation not found', [], 404);
        }

        // Insert outbound message
        $stmtInsert = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, sender_type, message_text, status, created_at) VALUES (?, ?, 'outbound', ?, 'sent', NOW())");
        $stmtInsert->execute([$userId, $waContactId, $body]);
        $msgId = $db->lastInsertId();

        // Update contact last message
        $stmtUpdate = $db->prepare("UPDATE whatsapp_contacts SET last_message = ?, last_message_time = NOW() WHERE id = ?");
        $stmtUpdate->execute([$body, $waContactId]);

        sendJsonResponse('success', 'Message sent successfully', [
            'message' => [
                'id' => $msgId,
                'wa_contact_id' => $waContactId,
                'sender_type' => 'outbound',
                'body' => $body,
                'status' => 'sent',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }

    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'WhatsApp operation failed: ' . $e->getMessage(), [], 500);
}
