<?php
// backend/api/crm/companies.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

// Auto-sync Lead Vault data from LinkedIn extension into CRM Contacts & Companies
require_once __DIR__ . '/../../crm_sync_helper.php';
CRMSyncHelper::syncLeadVaultToCRM($userId, $db);

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
            $owner = trim($_GET['owner'] ?? '');
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
            if ($owner !== '') {
                $query .= " AND owner = :owner";
                $params['owner'] = $owner;
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
            if ($owner !== '') $dataStmt->bindValue(':owner', $owner, PDO::PARAM_STR);
            if ($source !== '') $dataStmt->bindValue(':source', $source, PDO::PARAM_STR);
            $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $dataStmt->execute();
            
            $companies = $dataStmt->fetchAll();
            
            // Get distinct industries, statuses and owners for quick filter drop-downs
            $distinctIndustries = $db->prepare("SELECT DISTINCT industry FROM crm_companies WHERE user_id = ? AND industry IS NOT NULL AND industry != '' ORDER BY industry");
            $distinctIndustries->execute([$userId]);
            $industries = $distinctIndustries->fetchAll(PDO::FETCH_COLUMN);

            $distinctStatuses = $db->prepare("SELECT DISTINCT status FROM crm_companies WHERE user_id = ? AND status IS NOT NULL AND status != '' ORDER BY status");
            $distinctStatuses->execute([$userId]);
            $statuses = $distinctStatuses->fetchAll(PDO::FETCH_COLUMN);

            $distinctOwners = $db->prepare("SELECT DISTINCT owner FROM crm_companies WHERE user_id = ? AND owner IS NOT NULL AND owner != '' ORDER BY owner");
            $distinctOwners->execute([$userId]);
            $owners = $distinctOwners->fetchAll(PDO::FETCH_COLUMN);
            
            // Calculate detailed real-time statistics
            // 1. Total Companies
            $sTotal = $totalCount;
            
            // 2. Active Companies (meaning companies with status = 'Active')
            $stmtAct = $db->prepare("SELECT COUNT(*) FROM crm_companies WHERE user_id = ? AND status = 'Active'");
            $stmtAct->execute([$userId]);
            $sActive = (int)$stmtAct->fetchColumn();
            
            // 3. With Website (meaning website is not null and not empty)
            $stmtWeb = $db->prepare("SELECT COUNT(*) FROM crm_companies WHERE user_id = ? AND website IS NOT NULL AND website != ''");
            $stmtWeb->execute([$userId]);
            $sWithWebsite = (int)$stmtWeb->fetchColumn();
            
            // 4. Active Status (meaning companies with linked contacts)
            $stmtActStat = $db->prepare("SELECT COUNT(DISTINCT company_id) FROM crm_contacts WHERE user_id = ? AND company_id IS NOT NULL");
            $stmtActStat->execute([$userId]);
            $sActiveStatus = (int)$stmtActStat->fetchColumn();
            
            // 5. Unique industries
            $stmtInds = $db->prepare("SELECT COUNT(DISTINCT industry) FROM crm_companies WHERE user_id = ? AND industry IS NOT NULL AND industry != ''");
            $stmtInds->execute([$userId]);
            $sUniqueIndustries = (int)$stmtInds->fetchColumn();
            
            // Fetch logo.dev API Key from admin settings
            $logoApiKey = '';
            $stmtLogo = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'logo_dev_api_key' LIMIT 1");
            $stmtLogo->execute();
            $logoApiKey = $stmtLogo->fetchColumn() ?: '';
            
            sendJsonResponse('success', 'Companies listed successfully', [
                'companies' => $companies,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'logo_dev_api_key' => $logoApiKey,
                'stats' => [
                    'total' => $sTotal,
                    'active' => $sActive,
                    'with_website' => $sWithWebsite,
                    'active_status' => $sActiveStatus,
                    'industries' => $sUniqueIndustries
                ],
                'filters' => [
                    'industries' => $industries,
                    'statuses' => $statuses,
                    'owners' => $owners
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
    
    elseif ($method === 'BATCH_INSERT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $companiesInput = $input['companies'] ?? [];
        if (!is_array($companiesInput)) {
            sendJsonResponse('error', 'Invalid companies payload.', [], 400);
        }
        
        $importedCount = 0;
        $duplicateCount = 0;
        
        $db->beginTransaction();
        try {
            foreach ($companiesInput as $c) {
                $name = trim($c['name'] ?? '');
                if (empty($name)) continue;
                
                // Prevent duplicate companies by name
                $stmtDup = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ?");
                $stmtDup->execute([$name, $userId]);
                if ($stmtDup->fetch()) {
                    $duplicateCount++;
                    continue;
                }
                
                $industry = trim($c['industry'] ?? '');
                $website = trim($c['website'] ?? '');
                $owner = trim($c['owner'] ?? '');
                $status = trim($c['status'] ?? 'Active');
                
                $stmt = $db->prepare("INSERT INTO crm_companies (user_id, name, industry, website, owner, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId, $name, $industry, $website, $owner, $status
                ]);
                $companyId = $db->lastInsertId();
                
                // Log to timeline
                $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, activity_type, description) VALUES (?, ?, 'Company Created', ?)");
                $timelineStmt->execute([$userId, $companyId, "Company '$name' was imported into CRM."]);
                
                $importedCount++;
            }
            
            $db->commit();
            
            $msg = "Imported $importedCount companies successfully.";
            if ($duplicateCount > 0) {
                $msg .= " Skipped $duplicateCount duplicates.";
            }
            
            sendJsonResponse('success', $msg, [
                'imported' => $importedCount,
                'duplicates' => $duplicateCount
            ]);
        } catch (Exception $ex) {
            $db->rollBack();
            throw $ex;
        }
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
