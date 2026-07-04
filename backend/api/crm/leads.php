<?php
// backend/api/crm/leads.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $leadId = (int)$_GET['id'];
            
            $stmt = $db->prepare("SELECT l.*, co.name AS company_name, c.name AS contact_name FROM crm_leads l LEFT JOIN crm_companies co ON l.company_id = co.id LEFT JOIN crm_contacts c ON l.contact_id = c.id WHERE l.id = ? AND l.user_id = ?");
            $stmt->execute([$leadId, $userId]);
            $lead = $stmt->fetch();
            
            if (!$lead) {
                sendJsonResponse('error', 'Lead not found', [], 404);
            }
            
            // Fetch deals
            $stmtDeals = $db->prepare("SELECT * FROM crm_deals WHERE lead_id = ? AND user_id = ?");
            $stmtDeals->execute([$leadId, $userId]);
            $lead['deals'] = $stmtDeals->fetchAll();
            
            // Fetch tasks
            $stmtTasks = $db->prepare("SELECT * FROM crm_tasks WHERE lead_id = ? AND user_id = ? ORDER BY due_date ASC");
            $stmtTasks->execute([$leadId, $userId]);
            $lead['tasks'] = $stmtTasks->fetchAll();
            
            // Fetch notes
            $stmtNotes = $db->prepare("SELECT * FROM crm_notes WHERE company_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmtNotes->execute([$lead['company_id'], $userId]);
            $lead['notes'] = $stmtNotes->fetchAll();
            
            // Fetch timeline
            $stmtTimeline = $db->prepare("SELECT * FROM crm_timeline WHERE lead_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmtTimeline->execute([$leadId, $userId]);
            $lead['timeline'] = $stmtTimeline->fetchAll();
            
            sendJsonResponse('success', 'Lead retrieved successfully', ['lead' => $lead]);
            
        } else {
            // List leads
            $search = trim($_GET['search'] ?? '');
            $stage = trim($_GET['stage'] ?? '');
            $priority = trim($_GET['priority'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 20));
            $offset = ($page - 1) * $limit;
            
            $query = "FROM crm_leads l LEFT JOIN crm_companies co ON l.company_id = co.id LEFT JOIN crm_contacts c ON l.contact_id = c.id WHERE l.user_id = :user_id";
            $params = ['user_id' => $userId];
            
            if ($search !== '') {
                $query .= " AND (l.name LIKE :search OR l.email LIKE :search OR l.company LIKE :search OR l.services_required LIKE :search OR co.name LIKE :search OR c.name LIKE :search)";
                $params['search'] = '%' . $search . '%';
            }
            if ($stage !== '') {
                $query .= " AND l.stage = :stage";
                $params['stage'] = $stage;
            }
            if ($priority !== '') {
                $query .= " AND l.priority = :priority";
                $params['priority'] = $priority;
            }
            
            // Get Total Count
            $countStmt = $db->prepare("SELECT COUNT(*) " . $query);
            $countStmt->execute($params);
            $totalCount = (int)$countStmt->fetchColumn();
            
            // Fetch records
            $dataStmt = $db->prepare("SELECT l.*, co.name AS company_name, c.name AS contact_name " . $query . " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset");
            $dataStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            if ($search !== '') $dataStmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
            if ($stage !== '') $dataStmt->bindValue(':stage', $stage, PDO::PARAM_STR);
            if ($priority !== '') $dataStmt->bindValue(':priority', $priority, PDO::PARAM_STR);
            $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $dataStmt->execute();
            
            $leads = $dataStmt->fetchAll();
            
            sendJsonResponse('success', 'Leads listed successfully', [
                'leads' => $leads,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit
            ]);
        }
    } 
    
    elseif ($method === 'POST') {
        // Create lead
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            sendJsonResponse('error', 'Lead name is required.', [], 400);
        }
        
        $email = strtolower(trim($input['email'] ?? ''));
        $phone = trim($input['phone'] ?? '');
        $company = trim($input['company'] ?? '');
        
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
        
        // Auto-create company or contact if names provided but no IDs
        if (!$companyId && !empty($company)) {
            // Find existing company
            $stmtC = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ?");
            $stmtC->execute([$company, $userId]);
            if ($compRow = $stmtC->fetch()) {
                $companyId = $compRow['id'];
            } else {
                // Auto create company
                $insC = $db->prepare("INSERT INTO crm_companies (user_id, name, status) VALUES (?, ?, 'Active')");
                $insC->execute([$userId, $company]);
                $companyId = $db->lastInsertId();
            }
        }
        
        if (!$contactId && !empty($email)) {
            // Find existing contact
            $stmtCon = $db->prepare("SELECT id FROM crm_contacts WHERE email = ? AND user_id = ?");
            $stmtCon->execute([$email, $userId]);
            if ($conRow = $stmtCon->fetch()) {
                $contactId = $conRow['id'];
            } else {
                // Auto create contact
                $insCon = $db->prepare("INSERT INTO crm_contacts (user_id, company_id, name, email, phone) VALUES (?, ?, ?, ?, ?)");
                $insCon->execute([$userId, $companyId, $name, $email, $phone]);
                $contactId = $db->lastInsertId();
            }
        }
        
        $budget = (float)($input['budget'] ?? 0.00);
        $requirements = trim($input['requirements'] ?? '');
        $servicesRequired = trim($input['services_required'] ?? '');
        $priority = trim($input['priority'] ?? 'medium');
        $expectedClosingDate = !empty($input['expected_closing_date']) ? $input['expected_closing_date'] : null;
        $assignedEmployee = trim($input['assigned_employee'] ?? '');
        $leadScore = (int)($input['lead_score'] ?? 50);
        $aiConfidence = (int)($input['ai_confidence_score'] ?? 100);
        $leadSource = trim($input['lead_source'] ?? 'Manual Entry');
        $stage = trim($input['stage'] ?? 'New');
        $tags = trim($input['tags'] ?? '');
        $notes = trim($input['notes'] ?? '');
        
        $customFields = $input['custom_fields'] ?? [];
        $customFieldsJson = is_array($customFields) ? json_encode($customFields) : $customFields;
        
        $stmt = $db->prepare("INSERT INTO crm_leads (user_id, company_id, contact_id, name, email, phone, company, budget, requirements, services_required, priority, expected_closing_date, assigned_employee, lead_score, ai_confidence_score, lead_source, stage, tags, notes, custom_fields) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $companyId, $contactId, $name, $email, $phone, $company, $budget, $requirements, $servicesRequired, $priority, $expectedClosingDate, $assignedEmployee, $leadScore, $aiConfidence, $leadSource, $stage, $tags, $notes, $customFieldsJson
        ]);
        
        $leadId = $db->lastInsertId();
        
        // Log to timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, lead_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, ?, 'Lead Created', ?)");
        $timelineStmt->execute([$userId, $leadId, $companyId, $contactId, "New Lead '$name' ($stage) was added to CRM pipeline via $leadSource."]);
        
        // Trigger default automation workflow checks if applicable
        // (We can call this in Phase 3 sync process directly)
        
        sendJsonResponse('success', 'Lead created successfully', ['lead_id' => $leadId]);
    }
    
    elseif ($method === 'PUT' || $method === 'UPDATE') {
        // Update lead
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $leadId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($leadId <= 0) {
            sendJsonResponse('error', 'Lead ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT id, name, stage FROM crm_leads WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$leadId, $userId]);
        $lead = $stmtCheck->fetch();
        if (!$lead) {
            sendJsonResponse('error', 'Lead not found or access denied.', [], 404);
        }
        
        $name = trim($input['name'] ?? $lead['name']);
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
        $email = strtolower(trim($input['email'] ?? ''));
        $phone = trim($input['phone'] ?? '');
        $company = trim($input['company'] ?? '');
        $budget = (float)($input['budget'] ?? 0.00);
        $requirements = trim($input['requirements'] ?? '');
        $servicesRequired = trim($input['services_required'] ?? '');
        $priority = trim($input['priority'] ?? 'medium');
        $expectedClosingDate = !empty($input['expected_closing_date']) ? $input['expected_closing_date'] : null;
        $assignedEmployee = trim($input['assigned_employee'] ?? '');
        $leadScore = (int)($input['lead_score'] ?? 50);
        $aiConfidence = (int)($input['ai_confidence_score'] ?? 100);
        $leadSource = trim($input['lead_source'] ?? '');
        $stage = trim($input['stage'] ?? $lead['stage']);
        $tags = trim($input['tags'] ?? '');
        $notes = trim($input['notes'] ?? '');
        
        $customFields = $input['custom_fields'] ?? [];
        $customFieldsJson = is_array($customFields) ? json_encode($customFields) : $customFields;
        
        $stmt = $db->prepare("UPDATE crm_leads SET company_id = ?, contact_id = ?, name = ?, email = ?, phone = ?, company = ?, budget = ?, requirements = ?, services_required = ?, priority = ?, expected_closing_date = ?, assigned_employee = ?, lead_score = ?, ai_confidence_score = ?, lead_source = ?, stage = ?, tags = ?, notes = ?, custom_fields = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $companyId, $contactId, $name, $email, $phone, $company, $budget, $requirements, $servicesRequired, $priority, $expectedClosingDate, $assignedEmployee, $leadScore, $aiConfidence, $leadSource, $stage, $tags, $notes, $customFieldsJson, $leadId, $userId
        ]);
        
        // Log stage transitions to timeline
        if ($stage !== $lead['stage']) {
            $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, lead_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, ?, 'Lead Stage Changed', ?)");
            $timelineStmt->execute([$userId, $leadId, $companyId, $contactId, "Lead stage transitioned from '{$lead['stage']}' to '$stage'."]);
        } else {
            $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, lead_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, ?, 'Lead Updated', ?)");
            $timelineStmt->execute([$userId, $leadId, $companyId, $contactId, "Lead details updated."]);
        }
        
        sendJsonResponse('success', 'Lead updated successfully');
    }
    
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $leadId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        
        if ($leadId <= 0) {
            sendJsonResponse('error', 'Lead ID is required.', [], 400);
        }
        
        $stmtCheck = $db->prepare("SELECT id, name FROM crm_leads WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$leadId, $userId]);
        $lead = $stmtCheck->fetch();
        if (!$lead) {
            sendJsonResponse('error', 'Lead not found or access denied.', [], 404);
        }
        
        $stmt = $db->prepare("DELETE FROM crm_leads WHERE id = ? AND user_id = ?");
        $stmt->execute([$leadId, $userId]);
        
        sendJsonResponse('success', "Lead '{$lead['name']}' deleted successfully.");
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
