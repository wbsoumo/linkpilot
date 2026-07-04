<?php
// backend/api/crm/duplicates.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($method === 'GET') {
        // 1. Scan for duplicate contacts (same email, or exact matching names)
        $contactsQuery = "
            SELECT c1.id AS primary_id, c1.name AS primary_name, c1.email AS primary_email, 
                   c2.id AS duplicate_id, c2.name AS duplicate_name, c2.email AS duplicate_email
            FROM crm_contacts c1
            JOIN crm_contacts c2 ON c1.user_id = c2.user_id 
                 AND c1.id < c2.id 
                 AND (c1.email = c2.email OR (c1.name = c2.name AND c1.name != ''))
            WHERE c1.user_id = ?
        ";
        $stmtC = $db->prepare($contactsQuery);
        $stmtC->execute([$userId]);
        $duplicateContacts = $stmtC->fetchAll();

        // 2. Scan for duplicate companies (same website domain, or exact matching names)
        // Extract domain helper: replace http/https/www
        $companiesQuery = "
            SELECT co1.id AS primary_id, co1.name AS primary_name, co1.website AS primary_website,
                   co2.id AS duplicate_id, co2.name AS duplicate_name, co2.website AS duplicate_website
            FROM crm_companies co1
            JOIN crm_companies co2 ON co1.user_id = co2.user_id 
                 AND co1.id < co2.id
                 AND (co1.name = co2.name OR (co1.website = co2.website AND co1.website != ''))
            WHERE co1.user_id = ?
        ";
        $stmtCo = $db->prepare($companiesQuery);
        $stmtCo->execute([$userId]);
        $duplicateCompanies = $stmtCo->fetchAll();

        sendJsonResponse('success', 'Duplicates scan complete', [
            'duplicate_contacts' => $duplicateContacts,
            'duplicate_companies' => $duplicateCompanies
        ]);
    } 
    
    elseif ($method === 'POST') {
        // Merge request
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $type = trim($input['type'] ?? ''); // 'contact' or 'company'
        $primaryId = (int)($input['primary_id'] ?? 0);
        $duplicateId = (int)($input['duplicate_id'] ?? 0);

        if ($primaryId <= 0 || $duplicateId <= 0 || $primaryId === $duplicateId) {
            sendJsonResponse('error', 'Valid primary_id and duplicate_id are required.', [], 400);
        }

        $db->beginTransaction();

        if ($type === 'contact') {
            // Verify ownership
            $stmtC1 = $db->prepare("SELECT id, name FROM crm_contacts WHERE id = ? AND user_id = ?");
            $stmtC1->execute([$primaryId, $userId]);
            $primary = $stmtC1->fetch();

            $stmtC2 = $db->prepare("SELECT id, name FROM crm_contacts WHERE id = ? AND user_id = ?");
            $stmtC2->execute([$duplicateId, $userId]);
            $duplicate = $stmtC2->fetch();

            if (!$primary || !$duplicate) {
                $db->rollBack();
                sendJsonResponse('error', 'One or both contact records do not exist or access is denied.', [], 404);
            }

            // Move linked elements from duplicate to primary
            $db->prepare("UPDATE crm_deals SET contact_id = ? WHERE contact_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_tasks SET contact_id = ? WHERE contact_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_meetings SET contact_id = ? WHERE contact_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_timeline SET contact_id = ? WHERE contact_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_notes SET contact_id = ? WHERE contact_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_documents SET contact_id = ? WHERE contact_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_leads SET contact_id = ? WHERE contact_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);

            // Delete duplicate contact
            $db->prepare("DELETE FROM crm_contacts WHERE id = ? AND user_id = ?")->execute([$duplicateId, $userId]);

            // Add merged log to primary timeline
            $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, activity_type, description) VALUES (?, ?, 'Records Merged', ?)")
               ->execute([$userId, $primaryId, "Contact record '{$duplicate['name']}' was merged into '{$primary['name']}'."]);

            $db->commit();
            sendJsonResponse('success', "Contacts merged successfully. Record '{$duplicate['name']}' merged into '{$primary['name']}'.");

        } elseif ($type === 'company') {
            // Verify ownership
            $stmtCo1 = $db->prepare("SELECT id, name FROM crm_companies WHERE id = ? AND user_id = ?");
            $stmtCo1->execute([$primaryId, $userId]);
            $primary = $stmtCo1->fetch();

            $stmtCo2 = $db->prepare("SELECT id, name FROM crm_companies WHERE id = ? AND user_id = ?");
            $stmtCo2->execute([$duplicateId, $userId]);
            $duplicate = $stmtCo2->fetch();

            if (!$primary || !$duplicate) {
                $db->rollBack();
                sendJsonResponse('error', 'One or both company records do not exist or access is denied.', [], 404);
            }

            // Move linked elements from duplicate to primary
            $db->prepare("UPDATE crm_contacts SET company_id = ? WHERE company_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_deals SET company_id = ? WHERE company_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_tasks SET company_id = ? WHERE company_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_meetings SET company_id = ? WHERE company_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_timeline SET company_id = ? WHERE company_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_notes SET company_id = ? WHERE company_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_documents SET company_id = ? WHERE company_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);
            $db->prepare("UPDATE crm_leads SET company_id = ? WHERE company_id = ? AND user_id = ?")->execute([$primaryId, $duplicateId, $userId]);

            // Delete duplicate company
            $db->prepare("DELETE FROM crm_companies WHERE id = ? AND user_id = ?")->execute([$duplicateId, $userId]);

            // Add merged log to primary timeline
            $db->prepare("INSERT INTO crm_timeline (user_id, company_id, activity_type, description) VALUES (?, ?, 'Records Merged', ?)")
               ->execute([$userId, $primaryId, "Company record '{$duplicate['name']}' was merged into '{$primary['name']}'."]);

            $db->commit();
            sendJsonResponse('success', "Companies merged successfully. Record '{$duplicate['name']}' merged into '{$primary['name']}'.");

        } else {
            $db->rollBack();
            sendJsonResponse('error', 'Invalid merge type. Must be contact or company.', [], 400);
        }
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Merge operation failed: ' . $e->getMessage(), [], 500);
}
