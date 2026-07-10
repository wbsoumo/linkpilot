<?php
// backend/api/crm/contacts.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../wallet_helper.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

// Auto-sync Lead Vault data from LinkedIn extension into CRM Contacts & Companies
require_once __DIR__ . '/../../crm_sync_helper.php';
CRMSyncHelper::syncLeadVaultToCRM($userId, $db);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $contactId = (int)$_GET['id'];
            
            $stmt = $db->prepare("SELECT c.*, co.name AS company_name FROM crm_contacts c LEFT JOIN crm_companies co ON c.company_id = co.id WHERE c.id = ? AND c.user_id = ?");
            $stmt->execute([$contactId, $userId]);
            $contact = $stmt->fetch();
            
            if (!$contact) {
                sendJsonResponse('error', 'Contact not found', [], 404);
            }
            
            // Fetch deals
            $stmtDeals = $db->prepare("SELECT * FROM crm_deals WHERE contact_id = ? AND user_id = ?");
            $stmtDeals->execute([$contactId, $userId]);
            $contact['deals'] = $stmtDeals->fetchAll();
            
            // Fetch tasks
            $stmtTasks = $db->prepare("SELECT * FROM crm_tasks WHERE contact_id = ? AND user_id = ? ORDER BY due_date ASC");
            $stmtTasks->execute([$contactId, $userId]);
            $contact['tasks'] = $stmtTasks->fetchAll();
            
            // Fetch meetings
            $stmtMeetings = $db->prepare("SELECT * FROM crm_meetings WHERE contact_id = ? AND user_id = ? ORDER BY start_time ASC");
            $stmtMeetings->execute([$contactId, $userId]);
            $contact['meetings'] = $stmtMeetings->fetchAll();
            
            // Fetch notes
            $stmtNotes = $db->prepare("SELECT * FROM crm_notes WHERE contact_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmtNotes->execute([$contactId, $userId]);
            $contact['notes'] = $stmtNotes->fetchAll();
            
            // Fetch documents
            $stmtDocs = $db->prepare("SELECT * FROM crm_documents WHERE contact_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmtDocs->execute([$contactId, $userId]);
            $contact['documents'] = $stmtDocs->fetchAll();
            
            // Fetch timeline
            $stmtTimeline = $db->prepare("SELECT * FROM crm_timeline WHERE contact_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmtTimeline->execute([$contactId, $userId]);
            $contact['timeline'] = $stmtTimeline->fetchAll();
            
            sendJsonResponse('success', 'Contact retrieved successfully', ['contact' => $contact]);
            
        } else {
            // List contacts
            $search = trim($_GET['search'] ?? '');
            $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
            $designation = trim($_GET['designation'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $offset = ($page - 1) * $limit;
            
            $query = "FROM crm_contacts c LEFT JOIN crm_companies co ON c.company_id = co.id WHERE c.user_id = :user_id";
            $params = ['user_id' => $userId];
            
            if ($search !== '') {
                $query .= " AND (c.name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search OR c.designation LIKE :search OR co.name LIKE :search)";
                $params['search'] = '%' . $search . '%';
            }
            if ($companyId > 0) {
                $query .= " AND c.company_id = :company_id";
                $params['company_id'] = $companyId;
            }
            if ($designation !== '') {
                $query .= " AND c.designation = :designation";
                $params['designation'] = $designation;
            }
            if ($status === 'active') {
                $query .= " AND (c.email IS NOT NULL AND c.email != '')";
            } elseif ($status === 'has_email') {
                $query .= " AND (c.email IS NOT NULL AND c.email != '')";
            } elseif ($status === 'has_phone') {
                $query .= " AND (c.phone IS NOT NULL AND c.phone != '')";
            }
            
            $countStmt = $db->prepare("SELECT COUNT(*) " . $query);
            $countStmt->execute($params);
            $totalCount = (int)$countStmt->fetchColumn();
            
            $dataStmt = $db->prepare("SELECT c.*, co.name as company_name, co.website as company_website " . $query . " ORDER BY c.name ASC LIMIT :limit OFFSET :offset");
            $dataStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            if ($search !== '') $dataStmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
            if ($companyId > 0) $dataStmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            if ($designation !== '') $dataStmt->bindValue(':designation', $designation, PDO::PARAM_STR);
            $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $dataStmt->execute();
            
            $contacts = $dataStmt->fetchAll();

            // Fetch logo.dev API Key from admin settings
            $logoApiKey = '';
            $stmtLogo = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'logo_dev_api_key' LIMIT 1");
            $stmtLogo->execute();
            $logoApiKey = $stmtLogo->fetchColumn() ?: '';

            // Compute statistics
            $stmtTotal = $db->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ?");
            $stmtTotal->execute([$userId]);
            $statTotal = (int)$stmtTotal->fetchColumn();

            $stmtActive = $db->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ? AND (email IS NOT NULL AND email != '')");
            $stmtActive->execute([$userId]);
            $statActive = (int)$stmtActive->fetchColumn();

            $stmtEmail = $db->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ? AND email IS NOT NULL AND email != ''");
            $stmtEmail->execute([$userId]);
            $statEmail = (int)$stmtEmail->fetchColumn();

            $stmtPhone = $db->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ? AND phone IS NOT NULL AND phone != ''");
            $stmtPhone->execute([$userId]);
            $statPhone = (int)$stmtPhone->fetchColumn();

            $stmtDesig = $db->prepare("SELECT COUNT(DISTINCT designation) FROM crm_contacts WHERE user_id = ? AND designation IS NOT NULL AND designation != ''");
            $stmtDesig->execute([$userId]);
            $statDesig = (int)$stmtDesig->fetchColumn();

            // Fetch distinct filter options
            $stmtAllComps = $db->prepare("SELECT id, name FROM crm_companies WHERE user_id = ? ORDER BY name");
            $stmtAllComps->execute([$userId]);
            $allCompanies = $stmtAllComps->fetchAll();

            $stmtAllDesigs = $db->prepare("SELECT DISTINCT designation FROM crm_contacts WHERE user_id = ? AND designation IS NOT NULL AND designation != '' ORDER BY designation");
            $stmtAllDesigs->execute([$userId]);
            $allDesignations = $stmtAllDesigs->fetchAll(PDO::FETCH_COLUMN);
            
            sendJsonResponse('success', 'Contacts listed successfully', [
                'contacts' => $contacts,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'logo_dev_api_key' => $logoApiKey,
                'stats' => [
                    'total' => $statTotal,
                    'active' => $statActive,
                    'with_email' => $statEmail,
                    'with_phone' => $statPhone,
                    'designations' => $statDesig
                ],
                'filter_options' => [
                    'companies' => $allCompanies,
                    'designations' => $allDesignations
                ]
            ]);
        }
    } 
    
    elseif ($method === 'POST') {
        if ($action === 'import') {
            // Handle CSV import!
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                sendJsonResponse('error', 'CSV file is required.', [], 400);
            }
            
            $filePath = $_FILES['csv_file']['tmp_name'];
            $file = fopen($filePath, 'r');
            if (!$file) {
                sendJsonResponse('error', 'Could not open CSV file.', [], 400);
            }
            
            // Parse CSV headers
            $headers = fgetcsv($file);
            if (!$headers) {
                sendJsonResponse('error', 'CSV file is empty.', [], 400);
            }
            
            // Normalize headers to lowercase to match fields flexibly
            $headers = array_map(function($h) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $h)));
            }, $headers);
            
            $colIndexName = array_search('name', $headers);
            $colIndexEmail = array_search('email', $headers);
            $colIndexPhone = array_search('phone', $headers);
            $colIndexCompany = array_search('company', $headers);
            $colIndexDesignation = array_search('designation', $headers);
            $colIndexDepartment = array_search('department', $headers);
            $colIndexWhatsapp = array_search('whatsapp', $headers);
            $colIndexLinkedin = array_search('linkedin', $headers);
            $colIndexLocation = array_search('location', $headers);
            
            if ($colIndexName === false) $colIndexName = array_search('contactname', $headers);
            if ($colIndexEmail === false) $colIndexEmail = array_search('emailaddress', $headers);
            if ($colIndexCompany === false) $colIndexCompany = array_search('companyname', $headers);
            
            if ($colIndexName === false) {
                sendJsonResponse('error', 'CSV must contain a "Name" column.', [], 400);
            }
            
            $importedCount = 0;
            $duplicateCount = 0;
            $limitReached = false;
            
            $db->beginTransaction();
            
            try {
                while (($row = fgetcsv($file)) !== false) {
                    if (!checkContactLimit($userId)) {
                        $limitReached = true;
                        break;
                    }
                    
                    $name = trim($row[$colIndexName] ?? '');
                    if (empty($name)) continue;
                    
                    $email = '';
                    if ($colIndexEmail !== false && isset($row[$colIndexEmail])) {
                        $email = strtolower(trim($row[$colIndexEmail]));
                    }
                    
                    $phone = '';
                    if ($colIndexPhone !== false && isset($row[$colIndexPhone])) {
                        $phone = trim($row[$colIndexPhone]);
                    }
                    
                    $companyName = '';
                    if ($colIndexCompany !== false && isset($row[$colIndexCompany])) {
                        $companyName = trim($row[$colIndexCompany]);
                    }
                    
                    $designation = '';
                    if ($colIndexDesignation !== false && isset($row[$colIndexDesignation])) {
                        $designation = trim($row[$colIndexDesignation]);
                    }
                    
                    $department = '';
                    if ($colIndexDepartment !== false && isset($row[$colIndexDepartment])) {
                        $department = trim($row[$colIndexDepartment]);
                    }
                    
                    $whatsapp = '';
                    if ($colIndexWhatsapp !== false && isset($row[$colIndexWhatsapp])) {
                        $whatsapp = trim($row[$colIndexWhatsapp]);
                    }
                    
                    $linkedin = '';
                    if ($colIndexLinkedin !== false && isset($row[$colIndexLinkedin])) {
                        $linkedin = trim($row[$colIndexLinkedin]);
                    }
                    
                    $location = '';
                    if ($colIndexLocation !== false && isset($row[$colIndexLocation])) {
                        $location = trim($row[$colIndexLocation]);
                    }
                    
                    // Prevent duplicate contacts by email
                    if (!empty($email)) {
                        $stmtDup = $db->prepare("SELECT id FROM crm_contacts WHERE email = ? AND user_id = ?");
                        $stmtDup->execute([$email, $userId]);
                        if ($stmtDup->fetch()) {
                            $duplicateCount++;
                            continue;
                        }
                    }
                    
                    // Resolve or create company
                    $companyId = null;
                    if (!empty($companyName)) {
                        $stmtComp = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ?");
                        $stmtComp->execute([$companyName, $userId]);
                        if ($compRow = $stmtComp->fetch()) {
                            $companyId = $compRow['id'];
                        } else {
                            $insComp = $db->prepare("INSERT INTO crm_companies (user_id, name, status) VALUES (?, ?, 'Active')");
                            $insComp->execute([$userId, $companyName]);
                            $companyId = $db->lastInsertId();
                        }
                    }
                    
                    // Insert contact
                    $stmt = $db->prepare("INSERT INTO crm_contacts (user_id, company_id, name, phone, email, linkedin, whatsapp, designation, department, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $userId, $companyId, $name, $phone, $email, $linkedin, $whatsapp, $designation, $department, $location
                    ]);
                    $contactId = $db->lastInsertId();
                    
                    // Log to timeline
                    $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, company_id, activity_type, description) VALUES (?, ?, ?, 'Contact Created', ?)");
                    $timelineStmt->execute([$userId, $contactId, $companyId, "Contact '$name' was imported into CRM."]);
                    
                    $importedCount++;
                }
                
                fclose($file);
                $db->commit();
                
                $msg = "Imported $importedCount contacts successfully.";
                if ($duplicateCount > 0) {
                    $msg .= " Skipped $duplicateCount duplicates.";
                }
                if ($limitReached) {
                    $msg .= " Contact limit of 100 reached. Some rows were skipped.";
                }
                
                sendJsonResponse('success', $msg, [
                    'imported' => $importedCount,
                    'duplicates' => $duplicateCount,
                    'limit_reached' => $limitReached
                ]);
                
            } catch (Exception $ex) {
                fclose($file);
                $db->rollBack();
                throw $ex;
            }
        }

        // Create contact
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            sendJsonResponse('error', 'Contact name is required.', [], 400);
        }
        
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $phone = trim($input['phone'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $alternateEmail = strtolower(trim($input['alternate_email'] ?? ''));
        $linkedin = trim($input['linkedin'] ?? '');
        $whatsapp = trim($input['whatsapp'] ?? '');
        $designation = trim($input['designation'] ?? '');
        $department = trim($input['department'] ?? '');
        $birthday = !empty($input['birthday']) ? $input['birthday'] : null;
        $location = trim($input['location'] ?? '');
        $notes = trim($input['notes'] ?? '');
        
        $customFields = $input['custom_fields'] ?? [];
        $customFieldsJson = is_array($customFields) ? json_encode($customFields) : $customFields;
        
        // Enforce contact limits (max 100 contacts for non-admins)
        if (!checkContactLimit($userId)) {
            sendJsonResponse('error', 'Contact limit reached. Your free tier allows up to 100 contacts. Please upgrade your plan or delete existing contacts.', [], 403);
        }
        
        // Prevent duplicate contacts by email
        if (!empty($email)) {
            $stmtDup = $db->prepare("SELECT id, name FROM crm_contacts WHERE email = ? AND user_id = ?");
            $stmtDup->execute([$email, $userId]);
            if ($dup = $stmtDup->fetch()) {
                sendJsonResponse('error', "Contact with email '$email' already exists (Name: {$dup['name']}).", ['duplicate_id' => $dup['id']], 409);
            }
        }
        
        $stmt = $db->prepare("INSERT INTO crm_contacts (user_id, company_id, name, phone, email, alternate_email, linkedin, whatsapp, designation, department, birthday, location, notes, custom_fields) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, $companyId, $name, $phone, $email, $alternateEmail, $linkedin, $whatsapp, $designation, $department, $birthday, $location, $notes, $customFieldsJson
        ]);
        
        $contactId = $db->lastInsertId();
        
        // Log to timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, company_id, activity_type, description) VALUES (?, ?, ?, 'Contact Created', ?)");
        $timelineStmt->execute([$userId, $contactId, $companyId, "Contact '$name' was added to the CRM."]);
        
        if ($companyId) {
            // Also log on company timeline
            $compTimelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, activity_type, description) VALUES (?, ?, 'Contact Linked', ?)");
            $compTimelineStmt->execute([$userId, $companyId, "Contact '$name' ($designation) was linked to the company."]);
        }
        
        if (!empty($notes)) {
            $notesStmt = $db->prepare("INSERT INTO crm_notes (user_id, contact_id, content) VALUES (?, ?, ?)");
            $notesStmt->execute([$userId, $contactId, $notes]);
        }
        
        sendJsonResponse('success', 'Contact created successfully', ['contact_id' => $contactId]);
    }
    
    elseif ($method === 'PUT' || $method === 'UPDATE') {
        // Update contact
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $contactId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($contactId <= 0) {
            sendJsonResponse('error', 'Contact ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT id, name, company_id FROM crm_contacts WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$contactId, $userId]);
        $contact = $stmtCheck->fetch();
        if (!$contact) {
            sendJsonResponse('error', 'Contact not found or access denied.', [], 404);
        }
        
        $name = trim($input['name'] ?? $contact['name']);
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $phone = trim($input['phone'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $alternateEmail = strtolower(trim($input['alternate_email'] ?? ''));
        $linkedin = trim($input['linkedin'] ?? '');
        $whatsapp = trim($input['whatsapp'] ?? '');
        $designation = trim($input['designation'] ?? '');
        $department = trim($input['department'] ?? '');
        $birthday = !empty($input['birthday']) ? $input['birthday'] : null;
        $location = trim($input['location'] ?? '');
        $notes = trim($input['notes'] ?? '');
        
        $customFields = $input['custom_fields'] ?? [];
        $customFieldsJson = is_array($customFields) ? json_encode($customFields) : $customFields;
        
        $stmt = $db->prepare("UPDATE crm_contacts SET company_id = ?, name = ?, phone = ?, email = ?, alternate_email = ?, linkedin = ?, whatsapp = ?, designation = ?, department = ?, birthday = ?, location = ?, notes = ?, custom_fields = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $companyId, $name, $phone, $email, $alternateEmail, $linkedin, $whatsapp, $designation, $department, $birthday, $location, $notes, $customFieldsJson, $contactId, $userId
        ]);
        
        // Log to timeline
        $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, company_id, activity_type, description) VALUES (?, ?, ?, 'Contact Updated', ?)");
        $timelineStmt->execute([$userId, $contactId, $companyId, "Contact '$name' details were updated."]);
        
        sendJsonResponse('success', 'Contact updated successfully');
    }
    
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $contactId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        
        if ($contactId <= 0) {
            sendJsonResponse('error', 'Contact ID is required.', [], 400);
        }
        
        $stmtCheck = $db->prepare("SELECT id, name FROM crm_contacts WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$contactId, $userId]);
        $contact = $stmtCheck->fetch();
        if (!$contact) {
            sendJsonResponse('error', 'Contact not found or access denied.', [], 404);
        }
        
        $stmt = $db->prepare("DELETE FROM crm_contacts WHERE id = ? AND user_id = ?");
        $stmt->execute([$contactId, $userId]);
        
        sendJsonResponse('success', "Contact '{$contact['name']}' deleted successfully.");
    }
    
    elseif ($method === 'LINK_COMPANY') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $contactId = (int)($input['contact_id'] ?? 0);
        $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        $companyName = trim($input['company_name'] ?? '');
        
        if ($contactId <= 0) {
            sendJsonResponse('error', 'Contact ID is required.', [], 400);
        }
        
        // Verify Contact ownership
        $stmtCon = $db->prepare("SELECT id, name, company_id FROM crm_contacts WHERE id = ? AND user_id = ?");
        $stmtCon->execute([$contactId, $userId]);
        $contact = $stmtCon->fetch();
        if (!$contact) {
            sendJsonResponse('error', 'Contact not found or access denied.', [], 404);
        }
        
        if (!$companyId && !empty($companyName)) {
            // Find or create company
            $stmtComp = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ?");
            $stmtComp->execute([$companyName, $userId]);
            if ($compRow = $stmtComp->fetch()) {
                $companyId = $compRow['id'];
            } else {
                $insComp = $db->prepare("INSERT INTO crm_companies (user_id, name, status) VALUES (?, ?, 'Active')");
                $insComp->execute([$userId, $companyName]);
                $companyId = $db->lastInsertId();
                
                // Log company creation to timeline
                $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, company_id, activity_type, description) VALUES (?, ?, 'Company Created', ?)");
                $timelineStmt->execute([$userId, $companyId, "Company '$companyName' was added to the CRM."]);
            }
        }
        
        if ($companyId) {
            $db->prepare("UPDATE crm_contacts SET company_id = ? WHERE id = ? AND user_id = ?")->execute([$companyId, $contactId, $userId]);
            
            // Log to timeline
            $timelineStmt = $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, company_id, activity_type, description) VALUES (?, ?, ?, 'Contact Linked', ?)");
            $timelineStmt->execute([$userId, $contactId, $companyId, "Contact '{$contact['name']}' was linked to the company."]);
        }
        
        sendJsonResponse('success', 'Company successfully linked to Contact.', [
            'company_id' => $companyId
        ]);
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
