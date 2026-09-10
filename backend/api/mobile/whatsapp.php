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
                SELECT id, wa_contact_id, COALESCE(direction, 'outbound') AS direction, COALESCE(direction, 'outbound') AS sender_type, COALESCE(body, '') AS body, created_at, status 
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
            $sql = "SELECT c.id, 
                           c.wa_id, 
                           COALESCE(c.profile_name, c.wa_id) AS name, 
                           NULL AS avatar, 
                           COALESCE((SELECT body FROM whatsapp_messages WHERE wa_contact_id = c.id OR RIGHT(wa_contact_id, 10) = RIGHT(c.wa_id, 10) ORDER BY created_at DESC LIMIT 1), '') AS last_message, 
                           COALESCE(c.last_message_at, NOW()) AS last_message_time, 
                           c.unread_count, 
                           c.tags AS tag, 
                           0 AS reply_required 
                    FROM whatsapp_contacts c 
                    JOIN (
                        SELECT RIGHT(wa_id, 10) as clean_id, MAX(id) as max_id
                        FROM whatsapp_contacts
                        WHERE user_id = :user_id1
                        GROUP BY RIGHT(wa_id, 10)
                    ) g ON c.id = g.max_id
                    WHERE c.user_id = :user_id2";
            $params = ['user_id1' => $userId, 'user_id2' => $userId];

            if ($search !== '') {
                $sql .= " AND (c.profile_name LIKE :search1 OR c.wa_id LIKE :search2)";
                $params['search1'] = '%' . $search . '%';
                $params['search2'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY c.last_message_at DESC, c.id DESC LIMIT 50";

            $stmt = $db->prepare($sql);
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
