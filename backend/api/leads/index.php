<?php
// backend/api/leads/index.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        sendJsonResponse('error', 'Invalid JSON input', [], 400);
    }
    
    $name = trim($input['name'] ?? '');
    $companyName = trim($input['company_name'] ?? '');
    $linkedinUrl = trim($input['linkedin_url'] ?? '');
    $email = trim($input['email'] ?? '');
    $phoneNumber = trim($input['phone_number'] ?? '');
    $source = trim($input['source'] ?? 'LinkedIn Extension');
    $postUrl = trim($input['post_url'] ?? '');
    $postContent = trim($input['post_content'] ?? '');
    $postSummary = trim($input['post_summary'] ?? '');
    $currentStatus = trim($input['current_status'] ?? 'New');
    
    if (empty($name) && empty($companyName)) {
        sendJsonResponse('error', 'Name or Company Name is required to save lead.', [], 400);
    }
    
    $db = Database::getConnection();
    
    try {
        // If no summary is provided but we have post content, generate it via AI
        if (empty($postSummary) && !empty($postContent)) {
            try {
                $systemPrompt = "You are a professional assistant. Summarize the provided LinkedIn post in 1-2 sentences. Keep it clear, concise, and professional.";
                $userPrompt = "LinkedIn Post:\n\n" . $postContent;
                $aiResponse = callAI($systemPrompt, $userPrompt, $userId);
                if ($aiResponse && is_string($aiResponse)) {
                    $postSummary = trim(strip_tags($aiResponse));
                }
            } catch (Exception $e) {
                error_log("Failed to generate post summary: " . $e->getMessage());
            }
        }

        // Check if lead already exists for this user by linkedinUrl or postUrl
        $leadId = null;
        if (!empty($linkedinUrl)) {
            $stmtCheck = $db->prepare("SELECT id FROM lead_vault WHERE user_id = ? AND linkedin_url = ?");
            $stmtCheck->execute([$userId, $linkedinUrl]);
            $existing = $stmtCheck->fetch();
            if ($existing) {
                $leadId = $existing['id'];
            }
        }
        
        if (!$leadId && !empty($postUrl)) {
            $stmtCheck = $db->prepare("SELECT id FROM lead_vault WHERE user_id = ? AND post_url = ?");
            $stmtCheck->execute([$userId, $postUrl]);
            $existing = $stmtCheck->fetch();
            if ($existing) {
                $leadId = $existing['id'];
            }
        }

        if ($leadId) {
            // Update existing lead
            $stmt = $db->prepare("
                UPDATE lead_vault 
                SET name = ?, company_name = ?, email = ?, phone_number = ?, source = ?, post_url = ?, post_content = ?, post_summary = ?, current_status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$name, $companyName ?: null, $email ?: null, $phoneNumber ?: null, $source, $postUrl ?: null, $postContent, $postSummary ?: null, $currentStatus, $leadId, $userId]);
            
            sendJsonResponse('success', 'Lead updated successfully in Lead Vault.', ['lead_id' => $leadId]);
        } else {
            // Insert new lead
            $stmt = $db->prepare("
                INSERT INTO lead_vault (user_id, name, company_name, linkedin_url, email, phone_number, source, post_url, post_content, post_summary, current_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $name, $companyName ?: null, $linkedinUrl ?: null, $email ?: null, $phoneNumber ?: null, $source, $postUrl ?: null, $postContent, $postSummary ?: null, $currentStatus]);
            $newLeadId = $db->lastInsertId();
            
            sendJsonResponse('success', 'Lead saved successfully to Lead Vault.', ['lead_id' => $newLeadId]);
        }
    } catch (Exception $e) {
        sendJsonResponse('error', 'Failed to save lead: ' . $e->getMessage(), [], 500);
    }
}

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
