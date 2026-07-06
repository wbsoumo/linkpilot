<?php
// backend/api/whatsapp/debug_db.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

header('Content-Type: application/json');

try {
    $user = JWTHelper::requireAuth();
    $userId = $user['id'];
    $db = Database::getConnection();
    
    $stmt = $db->prepare("SELECT id, user_id, business_name, display_phone_number, status, quality_rating, messaging_limit, created_at, updated_at FROM whatsapp_accounts WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'user_id' => $userId,
        'accounts' => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
