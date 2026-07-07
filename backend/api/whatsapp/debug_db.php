<?php
// backend/api/whatsapp/debug_db.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

header('Content-Type: application/json');

// Bypassing auth check for diagnostic review
try {
    $db = Database::getConnection();
    
    $stmtAcc = $db->query("SELECT id, user_id, business_name, display_phone_number, status, quality_rating FROM whatsapp_accounts ORDER BY id DESC LIMIT 5");
    $accounts = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtMsg = $db->query("SELECT id, wa_contact_id, message_id, direction, type, body, status, error_message, created_at FROM whatsapp_messages ORDER BY id DESC LIMIT 10");
    $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtWh = $db->query("SELECT id, status, error_message, created_at, SUBSTRING(payload, 1, 1000) as payload_chunk FROM whatsapp_webhook_logs ORDER BY id DESC LIMIT 15");
    $webhooks = $stmtWh->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'accounts' => $accounts,
        'recent_messages' => $messages,
        'recent_webhooks' => $webhooks
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
