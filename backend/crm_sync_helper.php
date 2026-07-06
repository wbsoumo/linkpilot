<?php
// backend/crm_sync_helper.php

class CRMSyncHelper {
    public static function syncLeadVaultToCRM($userId, $db) {
        try {
            // 1. Fetch missing companies (companies in lead_vault that do not exist in crm_companies)
            $stmtMissingComps = $db->prepare("
                SELECT DISTINCT lv.company_name, lv.source
                FROM lead_vault lv
                LEFT JOIN crm_companies c ON lv.company_name = c.name AND lv.user_id = c.user_id
                WHERE lv.user_id = ? AND lv.company_name IS NOT NULL AND lv.company_name != '' AND c.id IS NULL
            ");
            $stmtMissingComps->execute([$userId]);
            $missingComps = $stmtMissingComps->fetchAll(PDO::FETCH_ASSOC);

            foreach ($missingComps as $mc) {
                $compName = trim($mc['company_name']);
                $source = trim($mc['source'] ?? 'LinkedIn Extension');
                $insC = $db->prepare("INSERT INTO crm_companies (user_id, name, source, status, notes) VALUES (?, ?, ?, 'Active', 'Created from LinkedIn extension lead')");
                $insC->execute([$userId, $compName, $source]);
            }

            // 2. Fetch missing contacts (contacts in lead_vault that do not exist in crm_contacts)
            // Match by email if exists, otherwise by name
            $stmtMissingContacts = $db->prepare("
                SELECT lv.*
                FROM lead_vault lv
                LEFT JOIN crm_contacts c ON (
                    (lv.email IS NOT NULL AND lv.email != '' AND lv.email = c.email) OR
                    ((lv.email IS NULL OR lv.email = '') AND lv.name = c.name)
                ) AND lv.user_id = c.user_id
                WHERE lv.user_id = ? AND c.id IS NULL
            ");
            $stmtMissingContacts->execute([$userId]);
            $missingContacts = $stmtMissingContacts->fetchAll(PDO::FETCH_ASSOC);

            foreach ($missingContacts as $mc) {
                $name = trim($mc['name'] ?? '');
                $email = strtolower(trim($mc['email'] ?? ''));
                $phone = trim($mc['phone_number'] ?? '');
                $linkedin = trim($mc['linkedin_url'] ?? '');
                $notes = trim($mc['post_content'] ?? '');
                $created_at = $mc['created_at'];
                
                // Find company_id if exists
                $companyName = trim($mc['company_name'] ?? '');
                $companyId = null;
                if ($companyName !== '') {
                    $stmtC = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ? LIMIT 1");
                    $stmtC->execute([$companyName, $userId]);
                    $company = $stmtC->fetch();
                    if ($company) {
                        $companyId = $company['id'];
                    }
                }

                $customFields = json_encode([
                    'post_url' => trim($mc['post_url'] ?? ''),
                    'source' => trim($mc['source'] ?? 'LinkedIn Extension')
                ]);

                $insCon = $db->prepare("INSERT INTO crm_contacts (user_id, company_id, name, email, phone, linkedin, notes, custom_fields, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insCon->execute([
                    $userId,
                    $companyId,
                    $name !== '' ? $name : 'Unknown Contact',
                    $email !== '' ? $email : null,
                    $phone,
                    $linkedin,
                    $notes,
                    $customFields,
                    $created_at
                ]);
            }

            // 3. Fetch existing contacts that have missing details in crm_contacts but have them in lead_vault
            $stmtOutdatedContacts = $db->prepare("
                SELECT lv.*, c.id AS contact_id, c.company_id AS contact_company_id, c.phone AS contact_phone, c.linkedin AS contact_linkedin, c.notes AS contact_notes
                FROM lead_vault lv
                JOIN crm_contacts c ON (
                    (lv.email IS NOT NULL AND lv.email != '' AND lv.email = c.email) OR
                    ((lv.email IS NULL OR lv.email = '') AND lv.name = c.name)
                ) AND lv.user_id = c.user_id
                WHERE lv.user_id = ? AND (
                    (c.company_id IS NULL AND lv.company_name IS NOT NULL AND lv.company_name != '') OR
                    (c.phone IS NULL OR c.phone = '') AND (lv.phone_number IS NOT NULL AND lv.phone_number != '') OR
                    (c.linkedin IS NULL OR c.linkedin = '') AND (lv.linkedin_url IS NOT NULL AND lv.linkedin_url != '') OR
                    (c.notes IS NULL OR c.notes = '') AND (lv.post_content IS NOT NULL AND lv.post_content != '')
                )
            ");
            $stmtOutdatedContacts->execute([$userId]);
            $outdated = $stmtOutdatedContacts->fetchAll(PDO::FETCH_ASSOC);

            foreach ($outdated as $oc) {
                $contactId = $oc['contact_id'];
                
                $updates = [];
                $params = [];

                if (empty($oc['contact_company_id']) && !empty($oc['company_name'])) {
                    // Resolve company ID
                    $stmtC = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ? LIMIT 1");
                    $stmtC->execute([trim($oc['company_name']), $userId]);
                    $company = $stmtC->fetch();
                    if ($company) {
                        $updates[] = "company_id = ?";
                        $params[] = $company['id'];
                    }
                }

                if (empty($oc['contact_phone']) && !empty($oc['phone_number'])) {
                    $updates[] = "phone = ?";
                    $params[] = trim($oc['phone_number']);
                }

                if (empty($oc['contact_linkedin']) && !empty($oc['linkedin_url'])) {
                    $updates[] = "linkedin = ?";
                    $params[] = trim($oc['linkedin_url']);
                }

                if (empty($oc['contact_notes']) && !empty($oc['post_content'])) {
                    $updates[] = "notes = ?";
                    $params[] = trim($oc['post_content']);
                }

                if (count($updates) > 0) {
                    $params[] = $contactId;
                    $params[] = $userId;
                    $stmtUpdate = $db->prepare("UPDATE crm_contacts SET " . implode(", ", $updates) . " WHERE id = ? AND user_id = ?");
                    $stmtUpdate->execute($params);
                }
            }
        } catch (Exception $e) {
            error_log("CRM Auto Sync error: " . $e->getMessage());
        }
    }
}
