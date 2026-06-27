<?php
// backend/api/profile/search_history.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$db = Database::getConnection();

// Query Parameters
$search = trim($_GET['search'] ?? '');
$provider = trim($_GET['provider'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 10);
$offset = ($page - 1) * $limit;
$export = trim($_GET['export'] ?? '');

try {
    $sql = "FROM email_search_history WHERE user_id = :user_id";
    $params = ['user_id' => $userId];

    if ($search !== '') {
        $sql .= " AND (name LIKE :search OR company LIKE :search OR email LIKE :search OR linkedin_url LIKE :search)";
        $params['search'] = "%{$search}%";
    }

    if ($provider !== '') {
        $sql .= " AND provider = :provider";
        $params['provider'] = $provider;
    }

    // Handle CSV Export
    if ($export === 'csv') {
        $stmtExport = $db->prepare("SELECT name, company, email, linkedin_url, provider, credits_used, created_at " . $sql . " ORDER BY id DESC");
        $stmtExport->bindValue(':user_id', $userId, PDO::PARAM_INT);
        if ($search !== '') $stmtExport->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
        if ($provider !== '') $stmtExport->bindValue(':provider', $provider, PDO::PARAM_STR);
        $stmtExport->execute();
        $records = $stmtExport->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=email_finder_history_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, ['Name', 'Company', 'Email Address', 'LinkedIn URL', 'Provider Used', 'Credits Deducted', 'Search Timestamp']);
        
        foreach ($records as $row) {
            fputcsv($output, [
                $row['name'],
                $row['company'],
                $row['email'] ?: 'Not Found',
                $row['linkedin_url'],
                ucfirst($row['provider']),
                $row['credits_used'],
                $row['created_at']
            ]);
        }
        fclose($output);
        exit;
    }

    // 1. Get total count
    $countSql = "SELECT COUNT(*) as total " . $sql;
    $stmtCount = $db->prepare($countSql);
    $stmtCount->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($search !== '') $stmtCount->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
    if ($provider !== '') $stmtCount->bindValue(':provider', $provider, PDO::PARAM_STR);
    $stmtCount->execute();
    $totalCount = (int)$stmtCount->fetch()['total'];

    // 2. Fetch history rows
    $dataSql = "SELECT id, name, company, email, linkedin_url, provider, credits_used, created_at " . $sql . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmtData = $db->prepare($dataSql);
    $stmtData->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($search !== '') $stmtData->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
    if ($provider !== '') $stmtData->bindValue(':provider', $provider, PDO::PARAM_STR);
    $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $history = $stmtData->fetchAll();

    // 3. Fetch unique providers for filter dropdown
    $stmtProviders = $db->prepare("SELECT DISTINCT provider FROM email_search_history WHERE user_id = ? AND provider IS NOT NULL ORDER BY provider");
    $stmtProviders->execute([$userId]);
    $providersList = array_column($stmtProviders->fetchAll(), 'provider');

    sendJsonResponse('success', 'Search history loaded.', [
        'history' => $history,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalCount / $limit)
        ],
        'providers' => $providersList
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Error fetching search history: ' . $e->getMessage(), [], 500);
}
