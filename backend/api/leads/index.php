<?php
// backend/api/leads/index.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$db = Database::getConnection();

// Query Parameters
$search = trim($_GET['search'] ?? '');
$source = trim($_GET['source'] ?? '');
$company = trim($_GET['company'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 10);
$offset = ($page - 1) * $limit;

try {
    // Base SQL
    $sql = "FROM lead_vault WHERE user_id = :user_id";
    $params = ['user_id' => $userId];
    
    // Filters
    if ($search !== '') {
        $sql .= " AND (name LIKE :search OR company_name LIKE :search OR email LIKE :search OR phone_number LIKE :search)";
        $params['search'] = "%{$search}%";
    }
    
    if ($source !== '') {
        $sql .= " AND source = :source";
        $params['source'] = $source;
    }
    
    if ($company !== '') {
        $sql .= " AND company_name = :company";
        $params['company'] = $company;
    }
    
    // 1. Get total count
    $countSql = "SELECT COUNT(*) as total " . $sql;
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute($params);
    $totalCount = (int)$stmtCount->fetch()['total'];
    
    // 2. Fetch records
    $dataSql = "SELECT * " . $sql . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmtData = $db->prepare($dataSql);
    
    // Bind limit & offset as integers because PDO executes bindParam/bindValue as strings by default
    $stmtData->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($search !== '') $stmtData->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
    if ($source !== '') $stmtData->bindValue(':source', $source, PDO::PARAM_STR);
    if ($company !== '') $stmtData->bindValue(':company', $company, PDO::PARAM_STR);
    $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmtData->execute();
    $leads = $stmtData->fetchAll();
    
    // 3. Fetch unique companies and sources for filtering dropdowns
    $stmtCompanies = $db->prepare("SELECT DISTINCT company_name FROM lead_vault WHERE user_id = ? AND company_name IS NOT NULL AND company_name != '' ORDER BY company_name");
    $stmtCompanies->execute([$userId]);
    $companies = array_column($stmtCompanies->fetchAll(), 'company_name');
    
    $stmtSources = $db->prepare("SELECT DISTINCT source FROM lead_vault WHERE user_id = ? AND source IS NOT NULL ORDER BY source");
    $stmtSources->execute([$userId]);
    $sources = array_column($stmtSources->fetchAll(), 'source');
    
    sendJsonResponse('success', 'Leads loaded.', [
        'leads' => $leads,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalCount / $limit)
        ],
        'filters' => [
            'companies' => $companies,
            'sources' => $sources
        ]
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Error loading leads: ' . $e->getMessage(), [], 500);
}
