<?php
// backend/api/admin/logs.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Admin privileges
$admin = JWTHelper::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$db = Database::getConnection();

// Query Parameters
$search = trim($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 20);
$offset = ($page - 1) * $limit;

try {
    $sql = "FROM activity_logs l JOIN users u ON l.user_id = u.id";
    $params = [];
    
    if ($search !== '') {
        $sql .= " WHERE u.name LIKE :search OR u.email LIKE :search OR l.action LIKE :search";
        $params['search'] = "%{$search}%";
    }
    
    // 1. Get total logs count
    $stmtCount = $db->prepare("SELECT COUNT(*) as total " . $sql);
    $stmtCount->execute($params);
    $totalCount = (int)$stmtCount->fetch()['total'];
    
    // 2. Fetch log rows
    $stmtData = $db->prepare("
        SELECT l.id, l.action, l.ip_address, l.created_at, u.name as user_name, u.email as user_email
        " . $sql . "
        ORDER BY l.id DESC
        LIMIT :limit OFFSET :offset
    ");
    
    // Bind limit & offset as integers
    if ($search !== '') $stmtData->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
    $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmtData->execute();
    $logs = $stmtData->fetchAll();
    
    sendJsonResponse('success', 'Logs loaded.', [
        'logs' => $logs,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalCount / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Error loading activity logs: ' . $e->getMessage(), [], 500);
}
