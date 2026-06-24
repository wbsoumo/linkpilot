<?php
// backend/api/leads/export.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth (supports ?token=... in URL for downloads)
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$db = Database::getConnection();

$search = trim($_GET['search'] ?? '');
$source = trim($_GET['source'] ?? '');
$company = trim($_GET['company'] ?? '');

try {
    // Base SQL
    $sql = "SELECT name, company_name, linkedin_url, email, phone_number, post_url, source, created_at FROM lead_vault WHERE user_id = :user_id";
    $params = ['user_id' => $userId];
    
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
    
    $sql .= " ORDER BY id DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="linkpilot_leads_' . date('Y-m-d') . '.csv"');
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // CSV Header row
    fputcsv($output, ['Name', 'Company Name', 'LinkedIn URL', 'Email', 'Phone Number', 'Post URL', 'Source', 'Date Added']);
    
    // CSV Data rows
    foreach ($leads as $lead) {
        fputcsv($output, [
            $lead['name'] ?? '',
            $lead['company_name'] ?? '',
            $lead['linkedin_url'] ?? '',
            $lead['email'] ?? '',
            $lead['phone_number'] ?? '',
            $lead['post_url'] ?? '',
            $lead['source'] ?? '',
            $lead['created_at'] ?? ''
        ]);
    }
    
    fclose($output);
    logActivity($userId, "Exported Lead Vault database to CSV.");
    exit;
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Error exporting CSV: ' . $e->getMessage(), [], 500);
}
