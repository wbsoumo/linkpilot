<?php
// backend/api/crm/deals.php

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
            $dealId = (int)$_GET['id'];
            
            $stmt = $db->prepare("SELECT d.*, co.name AS company_name, c.name AS contact_name, l.name AS lead_name FROM crm_deals d LEFT JOIN crm_companies co ON d.company_id = co.id LEFT JOIN crm_contacts c ON d.contact_id = c.id LEFT JOIN crm_leads l ON d.lead_id = l.id WHERE d.id = ? AND d.user_id = ?");
            $stmt->execute([$dealId, $userId]);
            $deal = $stmt->fetch();
            
            if (!$deal) {
                sendJsonResponse('error', 'Deal not found', [], 404);
            }
            
            // Fetch tasks
            $stmtTasks = $db->prepare("SELECT * FROM crm_tasks WHERE company_id = ? AND user_id = ? ORDER BY due_date ASC");
            $stmtTasks->execute([$deal['company_id'], $userId]);
            $deal['tasks'] = $stmtTasks->fetchAll();
            
            // Fetch timeline
            $stmtTimeline = $db->prepare("SELECT * FROM crm_timeline WHERE deal_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmtTimeline->execute([$dealId, $userId]);
            $deal['timeline'] = $stmtTimeline->fetchAll();
            
            sendJsonResponse('success', 'Deal retrieved successfully', ['deal' => $deal]);
            
        } else {
            // Check if user requested Kanban specific layout
            $layout = trim($_GET['layout'] ?? 'list');
            
            if ($layout === 'kanban') {
                // Fetch all deals and group them by stages in PHP
                $stmt = $db->prepare("SELECT d.*, co.name as company_name, c.name as contact_name FROM crm_deals d LEFT JOIN crm_companies co ON d.company_id = co.id LEFT JOIN crm_contacts c ON d.contact_id = c.id WHERE d.user_id = ? ORDER BY d.closing_date ASC");
                $stmt->execute([$userId]);
                $allDeals = $stmt->fetchAll();
                
                $stages = [
                    'Lead' => [],
                    'Qualified' => [],
                    'Proposal' => [],
                    'Negotiation' => [],
                    'Closed Won' => [],
                    'Closed Lost' => []
                ];
                
                $totals = [
                    'Lead' => 0.00,
                    'Qualified' => 0.00,
                    'Proposal' => 0.00,
                    'Negotiation' => 0.00,
                    'Closed Won' => 0.00,
                    'Closed Lost' => 0.00
                ];
                
                foreach ($allDeals as $d) {
                    $stage = $d['stage'];
                    if (!array_key_exists($stage, $stages)) {
                        $stages[$stage] = [];
                        $totals[$stage] = 0.00;
                    }
                    $stages[$stage][] = $d;
                    $totals[$stage] += (float)$d['expected_revenue'];
                }
                
                sendJsonResponse('success', 'Kanban deals fetched', [
                    'stages' => $stages,
                    'totals' => $totals
                ]);
                
            } else {
                // Regular List View
                $search = trim($_GET['search'] ?? '');
                $stage = trim($_GET['stage'] ?? '');
                
                $query = "FROM crm_deals d LEFT JOIN crm_companies co ON d.company_id = co.id LEFT JOIN crm_contacts c ON d.contact_id = c.id WHERE d.user_id = :user_id";
                $params = ['user_id' => $userId];
                
                if ($search !== '') {
                    $query .= " AND (d.title LIKE :search OR co.name LIKE :search OR c.name LIKE :search)";
                    $params['search'] = '%' . $search . '%';
                }
                if ($stage !== '') {
                    $query .= " AND d.stage = :stage";
                    $params['stage'] = $stage;
                }
                
                $countStmt = $db->prepare("SELECT COUNT(*) " . $query);
                $countStmt->execute($params);
                $totalCount = (int)$countStmt->fetchColumn();
                
                $dataStmt = $db->prepare("SELECT d.*, co.name as company_name, c.name as contact_name " . $query . " ORDER BY d.closing_date ASC");
                $dataStmt->execute($params);
                $deals = $dataStmt->fetchAll();
                
                sendJsonResponse('success', 'Deals listed successfully', [
                    'deals' => $deals,
                    'total' => $totalCount
                ]);
            }
        }
    } 
    
    elseif ($method === 'POST') {
        // Create deal
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $title = trim($input['title'] ?? '');
        if (empty($title)) {
            sendJsonResponse('error', 'Deal title is required.', [], 400);
        }
        
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
        $leadId = !empty($input['lead_id']) ? (int)$input['lead_id'] : null;
        
        $stage = trim($input['stage'] ?? 'Lead');
        $expectedRevenue = (float)($input['expected_revenue'] ?? 0.00);
        $probability = (int)($input['probability'] ?? 50);
        $owner = trim($input['owner'] ?? '');
        $closingDate = !empty($input['closing_date']) ? $input['closing_date'] : null;
        
        $stmt = $db->prepare("INSERT INTO crm_deals (user_id, company_id, contact_id, lead_id, title, stage, expected_revenue, probability, owner, closing_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $companyId, $contactId, $leadId, $title, $stage, $expectedRevenue, $probability, $owner, $closingDate
        ]);
        
        $dealId = $db->lastInsertId();
        
        // Log to timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, deal_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, ?, 'Deal Created', ?)");
        $timelineStmt->execute([$userId, $dealId, $companyId, $contactId, "Deal '$title' was created at stage '$stage' with forecast ₹$expectedRevenue."]);
        
        sendJsonResponse('success', 'Deal created successfully', ['deal_id' => $dealId]);
    }
    
    elseif ($method === 'PUT' || $method === 'UPDATE') {
        // Update deal
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $dealId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($dealId <= 0) {
            sendJsonResponse('error', 'Deal ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT id, title, stage, company_id, contact_id FROM crm_deals WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$dealId, $userId]);
        $deal = $stmtCheck->fetch();
        if (!$deal) {
            sendJsonResponse('error', 'Deal not found or access denied.', [], 404);
        }
        
        $title = trim($input['title'] ?? $deal['title']);
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : $deal['company_id'];
        $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : $deal['contact_id'];
        $leadId = !empty($input['lead_id']) ? (int)$input['lead_id'] : null;
        
        $stage = trim($input['stage'] ?? $deal['stage']);
        $expectedRevenue = (float)($input['expected_revenue'] ?? 0.00);
        $probability = (int)($input['probability'] ?? 50);
        $owner = trim($input['owner'] ?? '');
        $closingDate = !empty($input['closing_date']) ? $input['closing_date'] : null;
        
        $stmt = $db->prepare("UPDATE crm_deals SET company_id = ?, contact_id = ?, lead_id = ?, title = ?, stage = ?, expected_revenue = ?, probability = ?, owner = ?, closing_date = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $companyId, $contactId, $leadId, $title, $stage, $expectedRevenue, $probability, $owner, $closingDate, $dealId, $userId
        ]);
        
        // Log stage transitions to timeline
        if ($stage !== $deal['stage']) {
            $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, deal_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, ?, 'Deal Stage Changed', ?)");
            $timelineStmt->execute([$userId, $dealId, $companyId, $contactId, "Deal stage changed from '{$deal['stage']}' to '$stage'."]);
        } else {
            $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, deal_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, ?, 'Deal Updated', ?)");
            $timelineStmt->execute([$userId, $dealId, $companyId, $contactId, "Deal details updated."]);
        }
        
        sendJsonResponse('success', 'Deal updated successfully');
    }
    
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $dealId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        
        if ($dealId <= 0) {
            sendJsonResponse('error', 'Deal ID is required.', [], 400);
        }
        
        $stmtCheck = $db->prepare("SELECT id, title FROM crm_deals WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$dealId, $userId]);
        $deal = $stmtCheck->fetch();
        if (!$deal) {
            sendJsonResponse('error', 'Deal not found or access denied.', [], 404);
        }
        
        $stmt = $db->prepare("DELETE FROM crm_deals WHERE id = ? AND user_id = ?");
        $stmt->execute([$dealId, $userId]);
        
        sendJsonResponse('success', "Deal '{$deal['title']}' deleted successfully.");
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
