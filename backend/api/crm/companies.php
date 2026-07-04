<?php
// backend/api/crm/companies.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Support POST override for updates and deletes to handle file-upload forms or standard Ajax posts
if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            // Get single company details with linked information
            $companyId = (int)$_GET['id'];
            
            $stmt = $db->prepare("SELECT * FROM crm_companies WHERE id = ? AND user_id = ?");
            $stmt->execute([$companyId, $userId]);
            $company = $stmt->fetch();
            
            if (!$company) {
                sendJsonResponse('error', 'Company not found', [], 404);
            }
            
            // Fetch contacts
            $stmtContacts = $db->prepare("SELECT * FROM crm_contacts WHERE company_id = ? AND user_id = ?");
            $stmtContacts->execute([$companyId, $userId]);
            $company['contacts'] = $stmtContacts->fetchAll();
            
            // Fetch deals
            $stmtDeals = $db->prepare("SELECT * FROM crm_deals WHERE company_id = ? AND user_id = ?");
            $stmtDeals->execute([$companyId, $userId]);
            $company['deals'] = $stmtDeals->fetchAll();
            
            // Fetch tasks
            $stmtTasks = $db->prepare("SELECT * FROM crm_tasks WHERE company_id = ? AND user_id = ? ORDER BY due_date ASC");
            $stmtTasks->execute([$companyId, $userId]);
            $company['tasks'] = $stmtTasks->fetchAll();
            
            // Fetch meetings
            $stmtMeetings = $db->prepare("SELECT * FROM crm_meetings WHERE company_id = ? AND user_id = ? ORDER BY start_time ASC");
            $stmtMeetings->execute([$companyId, $userId]);
            $company['meetings'] = $stmtMeetings->fetchAll();
            
            // Fetch notes
            $stmtNotes = $db->prepare("SELECT * FROM crm_notes WHERE company_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmtNotes->execute([$companyId, $userId]);
            $company['notes'] = $stmtNotes->fetchAll();
            
            // Fetch documents
            $stmtDocs = $db->prepare("SELECT * FROM crm_documents WHERE company_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmtDocs->execute([$companyId, $userId]);
            $company['documents'] = $stmtDocs->fetchAll();
            
            // Fetch timeline
            $stmtTimeline = $db->prepare("SELECT * FROM crm_timeline WHERE company_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmtTimeline->execute([$companyId, $userId]);
            $company['timeline'] = $stmtTimeline->fetchAll();
            
            sendJsonResponse('success', 'Company retrieved successfully', ['company' => $company]);
            
        } else {
            // List companies with filtering, searching & pagination
            $search = trim($_GET['search'] ?? '');
            $industry = trim($_GET['industry'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $source = trim($_GET['source'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $offset = ($page - 1) * $limit;
            
            $query = "FROM crm_companies WHERE user_id = :user_id";
            $params = ['user_id' => $userId];
            
            if ($search !== '') {
                $query .= " AND (name LIKE :search OR website LIKE :search OR tags LIKE :search OR industry LIKE :search)";
                $params['search'] = '%' . $search . '%';
            }
            if ($industry !== '') {
                $query .= " AND industry = :industry";
                $params['industry'] = $industry;
            }
            if ($status !== '') {
                $query .= " AND status = :status";
                $params['status'] = $status;
            }
            if ($source !== '') {
                $query .= " AND source = :source";
                $params['source'] = $source;
            }
            
            // Get Total Count
            $countStmt = $db->prepare("SELECT COUNT(*) " . $query);
            $countStmt->execute($params);
            $totalCount = (int)$countStmt->fetchColumn();
            
            // Fetch records
            $dataStmt = $db->prepare("SELECT * " . $query . " ORDER BY name ASC LIMIT :limit OFFSET :offset");
            $dataStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            if ($search !== '') $dataStmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
            if ($industry !== '') $dataStmt->bindValue(':industry', $industry, PDO::PARAM_STR);
            if ($status !== '') $dataStmt->bindValue(':status', $status, PDO::PARAM_STR);
            if ($source !== '') $dataStmt->bindValue(':source', $source, PDO::PARAM_STR);
            $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $dataStmt->execute();
            
            $companies = $dataStmt->fetchAll();
            
            // Get distinct industries and statuses for quick filter drop-downs
            $distinctIndustries = $db->prepare("SELECT DISTINCT industry FROM crm_companies WHERE user_id = ? AND industry IS NOT NULL AND industry != '' ORDER BY industry");
            $distinctIndustries->execute([$userId]);
            $industries = $distinctIndustries->fetchAll(PDO::FETCH_COLUMN);

            $distinctStatuses = $db->prepare("SELECT DISTINCT status FROM crm_companies WHERE user_id = ? AND status IS NOT NULL AND status != '' ORDER BY status");
            $distinctStatuses->execute([$userId]);
            $statuses = $distinctStatuses->fetchAll(PDO::FETCH_COLUMN);
            
            sendJsonResponse('success', 'Companies listed successfully', [
                'companies' => $companies,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'filters' => [
                    'industries' => $industries,
                    'statuses' => $statuses
                ]
            ]);
        }
    } 
    
    elseif ($method === 'POST') {
        // Create new company
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST; // fallback for form data
        }
        
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            sendJsonResponse('error', 'Company name is required.', [], 400);
        }
        
        $industry = trim($input['industry'] ?? '');
        $website = trim($input['website'] ?? '');
        $address = trim($input['address'] ?? '');
        $gst = trim($input['gst'] ?? '');
        $employees = (int)($input['employees'] ?? 0);
        $owner = trim($input['owner'] ?? '');
        $revenue = (float)($input['revenue'] ?? 0.00);
        $source = trim($input['source'] ?? '');
        $status = trim($input['status'] ?? 'Active');
        $notes = trim($input['notes'] ?? '');
        $tags = trim($input['tags'] ?? '');
        
        $socialLinks = $input['social_links'] ?? [];
        $socialLinksJson = is_array($socialLinks) ? json_encode($socialLinks) : $socialLinks;
        
        $customFields = $input['custom_fields'] ?? [];
        $customFieldsJson = is_array($customFields) ? json_encode($customFields) : $customFields;
        
        $stmt = $db->prepare("INSERT INTO crm_companies (user_id, name, industry, website, address, gst, employees, owner, revenue, source, status, notes, tags, social_links, custom_fields) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $name, $industry, $website, $address, $gst, $employees, $owner, $revenue, $source, $status, $notes, $tags, $socialLinksJson, $customFieldsJson
        ]);
        
        $companyId = $db->lastInsertId();
        
        // Log to timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, activity_type, description) VALUES (?, ?, 'Company Created', ?)");
        $timelineStmt->execute([$userId, $companyId, "Company '$name' was added to the CRM."]);
        
        // If notes are provided, insert into notes table
        if (!empty($notes)) {
            $notesStmt = $db->prepare("INSERT INTO crm_notes (user_id, company_id, content) VALUES (?, ?, ?)");
            $notesStmt->execute([$userId, $companyId, $notes]);
        }
        
        sendJsonResponse('success', 'Company created successfully', ['company_id' => $companyId]);
    }
    
    elseif ($method === 'PUT' || $method === 'UPDATE') {
        // Update company
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $companyId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($companyId <= 0) {
            sendJsonResponse('error', 'Company ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT id, name FROM crm_companies WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$companyId, $userId]);
        $company = $stmtCheck->fetch();
        if (!$company) {
            sendJsonResponse('error', 'Company not found or access denied.', [], 404);
        }
        
        $name = trim($input['name'] ?? $company['name']);
        $industry = trim($input['industry'] ?? '');
        $website = trim($input['website'] ?? '');
        $address = trim($input['address'] ?? '');
        $gst = trim($input['gst'] ?? '');
        $employees = (int)($input['employees'] ?? 0);
        $owner = trim($input['owner'] ?? '');
        $revenue = (float)($input['revenue'] ?? 0.00);
        $source = trim($input['source'] ?? '');
        $status = trim($input['status'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $tags = trim($input['tags'] ?? '');
        
        $socialLinks = $input['social_links'] ?? [];
        $socialLinksJson = is_array($socialLinks) ? json_encode($socialLinks) : $socialLinks;
        
        $customFields = $input['custom_fields'] ?? [];
        $customFieldsJson = is_array($customFields) ? json_encode($customFields) : $customFields;
        
        $stmt = $db->prepare("UPDATE crm_companies SET name = ?, industry = ?, website = ?, address = ?, gst = ?, employees = ?, owner = ?, revenue = ?, source = ?, status = ?, notes = ?, tags = ?, social_links = ?, custom_fields = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $name, $industry, $website, $address, $gst, $employees, $owner, $revenue, $source, $status, $notes, $tags, $socialLinksJson, $customFieldsJson, $companyId, $userId
        ]);
        
        // Log to timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, activity_type, description) VALUES (?, ?, 'Company Updated', ?)");
        $timelineStmt->execute([$userId, $companyId, "Company '$name' details were updated."]);
        
        sendJsonResponse('success', 'Company updated successfully');
    }
    
    elseif ($method === 'DELETE') {
        // Delete company
        $input = json_decode(file_get_contents('php://input'), true);
        $companyId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        
        if ($companyId <= 0) {
            sendJsonResponse('error', 'Company ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT id, name FROM crm_companies WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$companyId, $userId]);
        $company = $stmtCheck->fetch();
        if (!$company) {
            sendJsonResponse('error', 'Company not found or access denied.', [], 404);
        }
        
        $stmt = $db->prepare("DELETE FROM crm_companies WHERE id = ? AND user_id = ?");
        $stmt->execute([$companyId, $userId]);
        
        sendJsonResponse('success', "Company '{$company['name']}' deleted successfully.");
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
