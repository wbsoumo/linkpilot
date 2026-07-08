<?php
// backend/api/profile/get_all_credit_logs.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

try {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM email_credit_transactions WHERE user_id = ?");
    $stmtCount->execute([$userId]);
    $totalCount = (int)$stmtCount->fetchColumn();

    $stmt = $db->prepare("
        SELECT id, type, credits, amount, payment_id, provider_used, status, created_at 
        FROM email_credit_transactions 
        WHERE user_id = :userId 
        ORDER BY id DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse('success', 'Credit logs loaded.', [
        'logs' => $logs,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalCount / $limit)
        ]
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to load credit logs: ' . $e->getMessage(), [], 500);
}
