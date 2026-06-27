<?php
// backend/api/admin/get_credit_logs.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Admin Privilege
$user = JWTHelper::requireAdmin();

$db = Database::getConnection();

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 15);
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

try {
    $sql = "FROM email_credit_transactions t JOIN users u ON t.user_id = u.id";
    $params = [];

    if ($search !== '') {
        $sql .= " WHERE u.name LIKE :search OR u.email LIKE :search OR t.payment_id LIKE :search OR t.type LIKE :search";
        $params['search'] = "%{$search}%";
    }

    // 1. Get Count
    $stmtCount = $db->prepare("SELECT COUNT(*) as total " . $sql);
    if ($search !== '') $stmtCount->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
    $stmtCount->execute();
    $totalCount = (int)$stmtCount->fetch()['total'];

    // 2. Fetch Data
    $dataSql = "SELECT t.id, t.user_id, t.type, t.credits, t.amount, t.payment_id, t.provider_used, t.status, t.created_at, u.name as user_name, u.email as user_email " . $sql . " ORDER BY t.id DESC LIMIT :limit OFFSET :offset";
    
    $stmtData = $db->prepare($dataSql);
    if ($search !== '') $stmtData->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
    $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $logs = $stmtData->fetchAll();

    sendJsonResponse('success', 'Credit transaction logs retrieved.', [
        'logs' => $logs,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalCount / $limit)
        ]
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to retrieve credit logs: ' . $e->getMessage(), [], 500);
}
