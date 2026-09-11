<?php
// backend/crm_sync_helper.php

require_once __DIR__ . '/wallet_helper.php';

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

                if (!checkContactLimit($userId)) {
                    continue; // Skip creating contacts if user has exceeded 100 contacts
                }

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

            // Sync inbound processed emails to CRM contacts & companies
            self::syncReceivedEmailsToCRM($userId, $db);

        } catch (Exception $e) {
            error_log("CRM Auto Sync error: " . $e->getMessage());
        }
    }

    public static function syncReceivedEmailsToCRM($userId, $db) {
        try {
            $stmt = $db->prepare("SELECT id, sender_email, subject, ai_summary, extracted_data_json FROM received_emails WHERE user_id = ? AND ai_status = 'processed'");
            $stmt->execute([$userId]);
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($emails as $email) {
                $meta = json_decode($email['extracted_data_json'], true);
                if (!$meta || empty($meta['person_name'])) {
                    continue;
                }

                $personName = trim($meta['person_name']);
                $companyName = trim($meta['company_name'] ?? '');
                $phone = trim($meta['phone_number'] ?? '');
                $emailAddress = strtolower(trim($meta['email'] ?? $email['sender_email'] ?? ''));
                $website = trim($meta['website'] ?? '');
                $location = trim($meta['location'] ?? '');
                $address = trim($meta['address'] ?? '');
                
                // 1. Resolve / Create Company
                $companyId = null;
                if ($companyName !== '') {
                    $stmtComp = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ? LIMIT 1");
                    $stmtComp->execute([$companyName, $userId]);
                    $company = $stmtComp->fetch();
                    if ($company) {
                        $companyId = $company['id'];
                    } else {
                        // Create company
                        $insComp = $db->prepare("INSERT INTO crm_companies (user_id, name, website, address, source, status, notes) VALUES (?, ?, ?, ?, 'Inbound Email', 'Active', 'Created from incoming email analysis')");
                        $insComp->execute([
                            $userId,
                            $companyName,
                            $website !== '' ? $website : null,
                            $address !== '' ? $address : null
                        ]);
                        $companyId = $db->lastInsertId();
                    }
                }

                // 2. Resolve / Create / Update Contact
                $stmtCon = $db->prepare("SELECT * FROM crm_contacts WHERE (
                    (email IS NOT NULL AND email != '' AND email = ?) OR
                    (name = ? AND user_id = ?)
                ) LIMIT 1");
                $stmtCon->execute([$emailAddress, $personName, $userId]);
                $contact = $stmtCon->fetch();

                if (!$contact) {
                    // Create Contact
                    $customFields = json_encode([
                        'source' => 'Inbound Email',
                        'email_id' => $email['id'],
                        'location' => $location
                    ]);
                    
                    $notes = "AI Inbound Summary:\n" . ($email['ai_summary'] ?? '');
                    
                    if (!checkContactLimit($userId)) {
                        continue; // Skip creating contacts if user has exceeded 100 contacts
                    }
                    
                    $insCon = $db->prepare("INSERT INTO crm_contacts (user_id, company_id, name, email, phone, notes, custom_fields) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $insCon->execute([
                        $userId,
                        $companyId,
                        $personName,
                        $emailAddress !== '' ? $emailAddress : null,
                        $phone,
                        $notes,
                        $customFields
                    ]);
                } else {
                    // Update missing fields
                    $updates = [];
                    $params = [];

                    if (empty($contact['company_id']) && !empty($companyId)) {
                        $updates[] = "company_id = ?";
                        $params[] = $companyId;
                    }
                    if (empty($contact['phone']) && !empty($phone)) {
                        $updates[] = "phone = ?";
                        $params[] = $phone;
                    }
                    if (empty($contact['notes']) && !empty($email['ai_summary'])) {
                        $updates[] = "notes = ?";
                        $params[] = "AI Inbound Summary:\n" . $email['ai_summary'];
                    }

                    if (count($updates) > 0) {
                        $params[] = $contact['id'];
                        $params[] = $userId;
                        $stmtUpdate = $db->prepare("UPDATE crm_contacts SET " . implode(", ", $updates) . " WHERE id = ? AND user_id = ?");
                        $stmtUpdate->execute($params);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Inbound Email CRM Sync error: " . $e->getMessage());
        }
    }

    /**
     * Resolves an existing contact or creates a new one by cross-matching Email or Phone/WhatsApp.
     * Merges phone and email if matched via one of them.
     */
    public static function resolveContact($userId, $email = null, $phone = null, $name = null, $db = null) {
        if (!$db) {
            $db = Database::getConnection();
        }

        $email = $email ? strtolower(trim($email)) : null;
        $phone = $phone ? trim($phone) : null;
        $cleanPhone = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
        $shortPhone = ($cleanPhone && strlen($cleanPhone) >= 10) ? substr($cleanPhone, -10) : $cleanPhone;

        $contact = null;

        // 1. Match by Email first
        if ($email) {
            $stmt = $db->prepare("SELECT * FROM crm_contacts WHERE user_id = ? AND (LOWER(email) = ? OR LOWER(alternate_email) = ?) LIMIT 1");
            $stmt->execute([$userId, $email, $email]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 2. If not found by email, match by Phone/WhatsApp
        if (!$contact && $shortPhone) {
            $stmt = $db->prepare("SELECT * FROM crm_contacts WHERE user_id = ? AND (
                RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10) = ? OR 
                RIGHT(REGEXP_REPLACE(whatsapp, '[^0-9]', ''), 10) = ?
            ) LIMIT 1");
            $stmt->execute([$userId, $shortPhone, $shortPhone]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 3. Update missing email or phone on existing contact
        if ($contact) {
            $updates = [];
            $params = [];

            if ($email && empty($contact['email'])) {
                $updates[] = "email = ?";
                $params[] = $email;
                $contact['email'] = $email;
            }
            if ($phone && empty($contact['phone'])) {
                $updates[] = "phone = ?";
                $params[] = $phone;
                $contact['phone'] = $phone;
            }
            if ($phone && empty($contact['whatsapp'])) {
                $updates[] = "whatsapp = ?";
                $params[] = $phone;
                $contact['whatsapp'] = $phone;
            }
            if ($name && (empty($contact['name']) || $contact['name'] === 'Unknown Contact')) {
                $updates[] = "name = ?";
                $params[] = trim($name);
                $contact['name'] = trim($name);
            }

            if (count($updates) > 0) {
                $params[] = $contact['id'];
                $params[] = $userId;
                $stmtUpd = $db->prepare("UPDATE crm_contacts SET " . implode(", ", $updates) . " WHERE id = ? AND user_id = ?");
                $stmtUpd->execute($params);
            }

            return $contact;
        }

        // 4. Create new contact if not found
        $displayName = $name ? trim($name) : ($email ? explode('@', $email)[0] : ($phone ? $phone : 'Unknown Lead'));
        
        $ins = $db->prepare("INSERT INTO crm_contacts (user_id, name, email, phone, whatsapp, custom_fields) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([
            $userId,
            $displayName,
            $email,
            $phone,
            $phone,
            json_encode(['source' => 'Cross-Channel Identity Resolver'])
        ]);

        $newId = $db->lastInsertId();
        $stmtNew = $db->prepare("SELECT * FROM crm_contacts WHERE id = ?");
        $stmtNew->execute([$newId]);
        return $stmtNew->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Logs an event to the unified crm_activity_timeline table.
     */
    public static function logActivity($userId, $contactId, $channel, $direction, $summary, $metadata = [], $db = null) {
        if (!$db) {
            $db = Database::getConnection();
        }

        try {
            $stmt = $db->prepare("INSERT INTO crm_activity_timeline (user_id, contact_id, channel, direction, summary, metadata_json) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $contactId ? (int)$contactId : null,
                $channel,
                $direction,
                $summary,
                !empty($metadata) ? json_encode($metadata) : null
            ]);
            return $db->lastInsertId();
        } catch (Exception $e) {
            error_log("logActivity Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds potential duplicate contacts by Email, Phone, or Name + Company.
     */
    public static function findDuplicates($userId, $email = null, $phone = null, $name = null, $companyName = null, $db = null) {
        if (!$db) $db = Database::getConnection();

        $duplicates = [];
        $email = $email ? strtolower(trim($email)) : null;
        $phone = $phone ? trim($phone) : null;
        $cleanPhone = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
        $shortPhone = ($cleanPhone && strlen($cleanPhone) >= 10) ? substr($cleanPhone, -10) : $cleanPhone;
        $name = $name ? trim($name) : null;

        if ($email) {
            $stmt = $db->prepare("SELECT * FROM crm_contacts WHERE user_id = ? AND (LOWER(email) = ? OR LOWER(alternate_email) = ?) AND is_archived = 0");
            $stmt->execute([$userId, $email, $email]);
            $duplicates = array_merge($duplicates, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($shortPhone) {
            $stmt = $db->prepare("SELECT * FROM crm_contacts WHERE user_id = ? AND (
                RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10) = ? OR 
                RIGHT(REGEXP_REPLACE(whatsapp, '[^0-9]', ''), 10) = ?
            ) AND is_archived = 0");
            $stmt->execute([$userId, $shortPhone, $shortPhone]);
            $duplicates = array_merge($duplicates, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($name && $companyName) {
            $stmt = $db->prepare("SELECT c.* FROM crm_contacts c JOIN crm_companies comp ON c.company_id = comp.id WHERE c.user_id = ? AND c.name LIKE ? AND comp.name LIKE ? AND c.is_archived = 0");
            $stmt->execute([$userId, "%$name%", "%$companyName%"]);
            $duplicates = array_merge($duplicates, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Deduplicate array by ID
        $unique = [];
        foreach ($duplicates as $d) {
            $unique[$d['id']] = $d;
        }

        return array_values($unique);
    }

    /**
     * DB-level contact search and filtering with pagination.
     */
    public static function searchContacts($userId, $filters = [], $limit = 50, $offset = 0, $db = null) {
        if (!$db) $db = Database::getConnection();

        $query = "SELECT c.*, comp.name AS company_name FROM crm_contacts c LEFT JOIN crm_companies comp ON c.company_id = comp.id WHERE c.user_id = :user_id AND c.is_archived = 0";
        $params = ['user_id' => $userId];

        if (!empty($filters['q'])) {
            $searchTerm = '%' . trim($filters['q']) . '%';
            $query .= " AND (c.name LIKE :q OR c.email LIKE :q OR c.phone LIKE :q OR comp.name LIKE :q OR c.designation LIKE :q OR c.tags LIKE :q)";
            $params['q'] = $searchTerm;
        }

        if (!empty($filters['city'])) {
            $query .= " AND (c.city LIKE :city OR c.location LIKE :city)";
            $params['city'] = '%' . trim($filters['city']) . '%';
        }

        if (!empty($filters['contact_type'])) {
            $query .= " AND c.contact_type = :contact_type";
            $params['contact_type'] = trim($filters['contact_type']);
        }

        if (!empty($filters['tag'])) {
            $query .= " AND c.tags LIKE :tag";
            $params['tag'] = '%' . trim($filters['tag']) . '%';
        }

        if (!empty($filters['not_contacted_days'])) {
            $days = (int)$filters['not_contacted_days'];
            $query .= " AND (c.last_contacted_at IS NULL OR c.last_contacted_at <= DATE_SUB(NOW(), INTERVAL :days DAY))";
            $params['days'] = $days;
        }

        $query .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($query);
        foreach ($params as $key => $val) {
            if ($key === 'days' || $key === 'limit' || $key === 'offset') {
                $stmt->bindValue($key, (int)$val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue('limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Merges secondary contact records into primary contact.
     */
    public static function mergeContacts($userId, $primaryId, $secondaryId, $db = null) {
        if (!$db) $db = Database::getConnection();

        $primaryId = (int)$primaryId;
        $secondaryId = (int)$secondaryId;

        if ($primaryId === $secondaryId) return false;

        $db->beginTransaction();
        try {
            // Re-assign timeline activities, notes, tasks, deals, and meetings
            $db->prepare("UPDATE crm_activity_timeline SET contact_id = ? WHERE user_id = ? AND contact_id = ?")->execute([$primaryId, $userId, $secondaryId]);
            $db->prepare("UPDATE crm_contact_notes SET contact_id = ? WHERE user_id = ? AND contact_id = ?")->execute([$primaryId, $userId, $secondaryId]);
            $db->prepare("UPDATE crm_tasks SET contact_id = ? WHERE user_id = ? AND contact_id = ?")->execute([$primaryId, $userId, $secondaryId]);
            $db->prepare("UPDATE crm_deals SET contact_id = ? WHERE user_id = ? AND contact_id = ?")->execute([$primaryId, $userId, $secondaryId]);
            $db->prepare("UPDATE crm_meetings SET contact_id = ? WHERE user_id = ? AND contact_id = ?")->execute([$primaryId, $userId, $secondaryId]);

            // Soft-archive secondary contact
            $db->prepare("UPDATE crm_contacts SET is_archived = 1, notes = CONCAT(IFNULL(notes,''), '\n[Merged into Contact #', ?, ']') WHERE id = ? AND user_id = ?")->execute([$primaryId, $secondaryId, $userId]);

            $db->commit();

            self::logActivity($userId, $primaryId, 'system', 'system', "Merged Contact #$secondaryId into Primary Contact #$primaryId", [], $db);
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieves sanitized contact context object for AI prompts (AI Prompt Injection Protection).
     */
    public static function getContactContext($userId, $contactId, $db = null) {
        if (!$db) $db = Database::getConnection();

        $stmt = $db->prepare("SELECT c.*, comp.name AS company_name FROM crm_contacts c LEFT JOIN crm_companies comp ON c.company_id = comp.id WHERE c.id = ? AND c.user_id = ? LIMIT 1");
        $stmt->execute([$contactId, $userId]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$c) return null;

        // Fetch recent notes
        $stmtNotes = $db->prepare("SELECT note_text, created_at FROM crm_contact_notes WHERE user_id = ? AND contact_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmtNotes->execute([$userId, $contactId]);
        $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

        // Sanitize notes to prevent prompt injection instructions
        $cleanNotes = array_map(function($n) {
            $safeText = preg_replace('/(ignore|override|system|delete|system prompt)/i', '[redacted]', $n['note_text']);
            return "- [{$n['created_at']}] $safeText";
        }, $notes);

        return [
            'contact_id' => $c['id'],
            'name' => $c['name'],
            'email' => $c['email'],
            'phone' => $c['phone'],
            'whatsapp' => $c['whatsapp'],
            'company' => $c['company_name'],
            'designation' => $c['designation'],
            'contact_type' => $c['contact_type'],
            'tags' => $c['tags'],
            'city' => $c['city'],
            'recent_notes' => implode("\n", $cleanNotes)
        ];
    }

    /**
     * Adds a note to crm_contact_notes and logs to activity timeline.
     */
    public static function addContactNote($userId, $contactId, $noteText, $authorName = 'AI Co-Pilot', $db = null) {
        if (!$db) $db = Database::getConnection();

        $stmt = $db->prepare("INSERT INTO crm_contact_notes (user_id, contact_id, note_text, author_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $contactId, $noteText, $authorName]);
        $noteId = $db->lastInsertId();

        self::logActivity($userId, $contactId, 'system', 'system', "Added Note: \"$noteText\"", ['note_id' => $noteId], $db);
        return $noteId;
    }

    /**
     * Adds a tag to contact tags list.
     */
    public static function addContactTag($userId, $contactId, $tagName, $db = null) {
        if (!$db) $db = Database::getConnection();

        $tagName = trim($tagName);
        if (empty($tagName)) return false;

        // Ensure tag exists in master tags table
        $db->prepare("INSERT IGNORE INTO crm_contact_tags (user_id, tag_name) VALUES (?, ?)")->execute([$userId, $tagName]);

        // Append tag to crm_contacts.tags if not present
        $stmtC = $db->prepare("SELECT tags FROM crm_contacts WHERE id = ? AND user_id = ? LIMIT 1");
        $stmtC->execute([$contactId, $userId]);
        $currTags = $stmtC->fetchColumn() ?: '';

        $tagArray = array_filter(array_map('trim', explode(',', $currTags)));
        if (!in_array($tagName, $tagArray)) {
            $tagArray[] = $tagName;
            $newTagStr = implode(',', $tagArray);
            $db->prepare("UPDATE crm_contacts SET tags = ? WHERE id = ? AND user_id = ?")->execute([$newTagStr, $contactId, $userId]);

            self::logActivity($userId, $contactId, 'system', 'system', "Added Tag: \"$tagName\"", ['tag' => $tagName], $db);
        }
        return true;
    }
}


