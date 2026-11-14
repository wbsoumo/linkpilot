<?php
// backend/ai_tool_registry.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crm_sync_helper.php';
require_once __DIR__ . '/communication_provider_helper.php';
require_once __DIR__ . '/workflow_runner.php';
require_once __DIR__ . '/lead_scoring_engine.php';
require_once __DIR__ . '/email_intelligence_service.php';
require_once __DIR__ . '/sales_copilot_engine.php';
require_once __DIR__ . '/smtp_helper.php';
require_once __DIR__ . '/external_apps_helper.php';
require_once __DIR__ . '/web_search_helper.php';

class AIToolRegistry {
    private static $tools = [];

    public static function init() {
        if (!empty(self::$tools)) return;

        // ==========================================
        // 1. CRM & CONTACTS MODULE
        // ==========================================

        self::registerTool([
            'id' => 'search_contacts',
            'name' => 'Search Contacts',
            'description' => 'Search and filter CRM contacts by query string (name, email, phone, company), tag, city, or days since last contacted.',
            'category' => 'crm',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Text search query matching contact name, email, phone, or company name.'],
                    'tag' => ['type' => 'string', 'description' => 'Filter contacts by tag name.'],
                    'city' => ['type' => 'string', 'description' => 'Filter contacts by city.'],
                    'company' => ['type' => 'string', 'description' => 'Filter contacts by company name.'],
                    'days_since_contacted' => ['type' => 'integer', 'description' => 'Find contacts not contacted in the specified number of days.'],
                    'limit' => ['type' => 'integer', 'description' => 'Maximum number of contacts to return (default 25, max 100).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $limit = min(100, max(1, (int)($params['limit'] ?? 25)));
                return CRMSyncHelper::searchContacts($userId, $params, $limit, 0, $db);
            }
        ]);

        self::registerTool([
            'id' => 'get_contact',
            'name' => 'Get Contact Details',
            'description' => 'Retrieve complete CRM profile of a single contact including notes, deals, timeline, tags, and tasks by ID, email, or phone.',
            'category' => 'crm',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'contact_id' => ['type' => 'integer', 'description' => 'Internal CRM contact ID.'],
                    'email' => ['type' => 'string', 'description' => 'Contact email address.'],
                    'phone' => ['type' => 'string', 'description' => 'Contact phone number.'],
                    'name' => ['type' => 'string', 'description' => 'Contact full name.']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $contactId = (int)($params['contact_id'] ?? 0);
                if ($contactId > 0) {
                    $stmt = $db->prepare("SELECT c.*, co.name AS company_name FROM crm_contacts c LEFT JOIN crm_companies co ON c.company_id = co.id WHERE c.id = ? AND c.user_id = ?");
                    $stmt->execute([$contactId, $userId]);
                    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$contact) throw new Exception("Contact #{$contactId} not found.");

                    // Attached notes
                    $stmtNotes = $db->prepare("SELECT * FROM crm_contact_notes WHERE contact_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 10");
                    $stmtNotes->execute([$contactId, $userId]);
                    $contact['notes'] = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

                    // Attached deals
                    $stmtDeals = $db->prepare("SELECT id, title, stage, expected_revenue, closing_date FROM crm_deals WHERE contact_id = ? AND user_id = ?");
                    $stmtDeals->execute([$contactId, $userId]);
                    $contact['deals'] = $stmtDeals->fetchAll(PDO::FETCH_ASSOC);

                    // Attached tasks
                    $stmtTasks = $db->prepare("SELECT id, title, due_date, status, priority FROM crm_tasks WHERE contact_id = ? AND user_id = ? ORDER BY due_date ASC");
                    $stmtTasks->execute([$contactId, $userId]);
                    $contact['tasks'] = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

                    return $contact;
                }

                return CRMSyncHelper::resolveContact($userId, $params['email'] ?? null, $params['phone'] ?? null, $params['name'] ?? null, $db);
            }
        ]);

        self::registerTool([
            'id' => 'create_contact',
            'name' => 'Create Contact',
            'description' => 'Create a new CRM contact record with name, email, phone, company, designation, city, and notes.',
            'category' => 'crm',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Full name of the contact.'],
                    'email' => ['type' => 'string', 'description' => 'Email address.'],
                    'phone' => ['type' => 'string', 'description' => 'Phone or WhatsApp number with country code.'],
                    'company_name' => ['type' => 'string', 'description' => 'Name of the company/organization.'],
                    'designation' => ['type' => 'string', 'description' => 'Job title or role.'],
                    'city' => ['type' => 'string', 'description' => 'City location.'],
                    'notes' => ['type' => 'string', 'description' => 'Initial notes or remarks.'],
                    'tags' => ['type' => 'string', 'description' => 'Comma-separated tags (e.g. "VIP, Warm Lead").']
                ],
                'required' => ['name']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $name = trim($params['name'] ?? '');
                if (empty($name)) throw new Exception("Contact name is required.");

                $email = strtolower(trim($params['email'] ?? ''));
                $phone = trim($params['phone'] ?? '');
                $companyName = trim($params['company_name'] ?? '');
                $designation = trim($params['designation'] ?? '');
                $city = trim($params['city'] ?? '');
                $notes = trim($params['notes'] ?? '');
                $tags = trim($params['tags'] ?? '');

                // Resolve company
                $companyId = null;
                if (!empty($companyName)) {
                    $stmtC = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ? LIMIT 1");
                    $stmtC->execute([$companyName, $userId]);
                    $company = $stmtC->fetch(PDO::FETCH_ASSOC);
                    if ($company) {
                        $companyId = $company['id'];
                    } else {
                        $stmtInsC = $db->prepare("INSERT INTO crm_companies (user_id, name, status) VALUES (?, ?, 'Active')");
                        $stmtInsC->execute([$userId, $companyName]);
                        $companyId = $db->lastInsertId();
                    }
                }

                $stmt = $db->prepare("INSERT INTO crm_contacts (user_id, company_id, name, email, phone, designation, city, notes, tags, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $userId,
                    $companyId,
                    $name,
                    !empty($email) ? $email : null,
                    !empty($phone) ? $phone : null,
                    $designation,
                    $city,
                    $notes,
                    $tags
                ]);
                $contactId = $db->lastInsertId();

                // Timeline entry
                $stmtTime = $db->prepare("INSERT INTO crm_activity_timeline (user_id, contact_id, channel, direction, subject_or_title, content, status) VALUES (?, ?, 'system', 'system', 'Contact Created via AI Agent', ?, 'completed')");
                $stmtTime->execute([$userId, $contactId, "Contact '$name' was created by LinkPilot AI Agent."]);

                return [
                    'contact_id' => (int)$contactId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'company_id' => $companyId,
                    'message' => "Contact '{$name}' created successfully in CRM."
                ];
            }
        ]);

        self::registerTool([
            'id' => 'update_contact',
            'name' => 'Update Contact',
            'description' => 'Update specific fields of an existing CRM contact.',
            'category' => 'crm',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'contact_id' => ['type' => 'integer', 'description' => 'ID of the contact to update.'],
                    'name' => ['type' => 'string', 'description' => 'Updated name.'],
                    'email' => ['type' => 'string', 'description' => 'Updated email.'],
                    'phone' => ['type' => 'string', 'description' => 'Updated phone.'],
                    'designation' => ['type' => 'string', 'description' => 'Updated job title.'],
                    'notes' => ['type' => 'string', 'description' => 'Updated notes.'],
                    'tags' => ['type' => 'string', 'description' => 'Updated tags string.']
                ],
                'required' => ['contact_id']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $contactId = (int)($params['contact_id'] ?? 0);
                if ($contactId <= 0) throw new Exception("Valid contact_id is required.");

                $updates = [];
                $values = [];
                $fields = ['name', 'email', 'phone', 'designation', 'notes', 'tags'];
                foreach ($fields as $f) {
                    if (isset($params[$f]) && $params[$f] !== '') {
                        $updates[] = "`$f` = ?";
                        $values[] = trim($params[$f]);
                    }
                }

                if (empty($updates)) {
                    return ['contact_id' => $contactId, 'message' => 'No fields to update.'];
                }

                $values[] = $contactId;
                $values[] = $userId;
                $sql = "UPDATE crm_contacts SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($values);

                return [
                    'contact_id' => $contactId,
                    'updated_fields' => array_keys($params),
                    'message' => "Contact #{$contactId} updated successfully."
                ];
            }
        ]);

        self::registerTool([
            'id' => 'archive_contact',
            'name' => 'Archive Contact',
            'description' => 'Archive or delete a CRM contact from active lists.',
            'category' => 'crm',
            'risk_level' => 'DESTRUCTIVE',
            'approval_required' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'contact_id' => ['type' => 'integer', 'description' => 'Contact ID to archive.']
                ],
                'required' => ['contact_id']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $contactId = (int)($params['contact_id'] ?? 0);
                if ($contactId <= 0) throw new Exception("Contact ID required.");

                $stmt = $db->prepare("UPDATE crm_contacts SET is_archived = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$contactId, $userId]);

                return [
                    'contact_id' => $contactId,
                    'is_archived' => true,
                    'message' => "Contact #{$contactId} has been safely archived."
                ];
            }
        ]);

        self::registerTool([
            'id' => 'add_contact_note',
            'name' => 'Add Contact Note',
            'description' => 'Append a new note or meeting takeaway to a contact timeline.',
            'category' => 'crm',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'contact_id' => ['type' => 'integer', 'description' => 'Target contact ID.'],
                    'note_text' => ['type' => 'string', 'description' => 'Text of the note.'],
                    'author_name' => ['type' => 'string', 'description' => 'Author attribution (defaults to "LinkPilot Agent").']
                ],
                'required' => ['contact_id', 'note_text']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $contactId = (int)($params['contact_id'] ?? 1);
                $note = trim($params['note_text'] ?? ($params['note'] ?? ''));
                if (empty($note)) {
                    $note = "Automated AI Agent follow-up note recorded on " . date('Y-m-d H:i:s');
                }
                return CRMSyncHelper::addContactNote($userId, $contactId, $note, $params['author_name'] ?? 'LinkPilot Agent', $db);
            }
        ]);

        self::registerTool([
            'id' => 'add_contact_tag',
            'name' => 'Add Contact Tag',
            'description' => 'Assign a tag or label (e.g. VIP, Qualified, Follow-up) to a contact.',
            'category' => 'crm',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'contact_id' => ['type' => 'integer', 'description' => 'Contact ID.'],
                    'tag_name' => ['type' => 'string', 'description' => 'Tag label to assign.'],
                    'color_code' => ['type' => 'string', 'description' => 'Hex color code (e.g. #3b82f6).']
                ],
                'required' => ['contact_id', 'tag_name']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $contactId = (int)($params['contact_id'] ?? 1);
                $tag = trim($params['tag_name'] ?? ($params['tag'] ?? 'AI Follow-up'));
                return CRMSyncHelper::addContactTag($userId, $contactId, $tag, $params['color_code'] ?? '#3b82f6', $db);
            }
        ]);

        self::registerTool([
            'id' => 'search_leads',
            'name' => 'Search Pipeline Leads',
            'description' => 'Fetch pipeline leads filtered by query, stage, priority, or budget.',
            'category' => 'crm',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search query matching lead name, company, or services.'],
                    'stage' => ['type' => 'string', 'description' => 'Filter by stage (e.g. New, Contacted, In Progress, Closed).'],
                    'priority' => ['type' => 'string', 'description' => 'Filter by priority (low, medium, high).'],
                    'limit' => ['type' => 'integer', 'description' => 'Max leads to return (default 25).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $limit = min(50, max(1, (int)($params['limit'] ?? 25)));
                $query = "SELECT l.*, co.name AS company_name, c.name AS contact_name FROM crm_leads l LEFT JOIN crm_companies co ON l.company_id = co.id LEFT JOIN crm_contacts c ON l.contact_id = c.id WHERE l.user_id = :user_id";
                $binds = [':user_id' => $userId];

                if (!empty($params['query'])) {
                    $query .= " AND (l.name LIKE :q OR l.company LIKE :q OR l.email LIKE :q)";
                    $binds[':q'] = '%' . trim($params['query']) . '%';
                }
                if (!empty($params['stage'])) {
                    $query .= " AND l.stage = :stage";
                    $binds[':stage'] = trim($params['stage']);
                }
                if (!empty($params['priority'])) {
                    $query .= " AND l.priority = :priority";
                    $binds[':priority'] = trim($params['priority']);
                }

                $query .= " ORDER BY l.id DESC LIMIT $limit";
                $stmt = $db->prepare($query);
                $stmt->execute($binds);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'create_lead',
            'name' => 'Create Lead',
            'description' => 'Add a new sales lead to the CRM with name, company, email, phone, budget, and stage.',
            'category' => 'crm',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Lead contact name.'],
                    'company' => ['type' => 'string', 'description' => 'Lead company name.'],
                    'email' => ['type' => 'string', 'description' => 'Lead email.'],
                    'phone' => ['type' => 'string', 'description' => 'Lead phone number.'],
                    'services_required' => ['type' => 'string', 'description' => 'Requirement or service description.'],
                    'budget' => ['type' => 'number', 'description' => 'Estimated budget amount.'],
                    'stage' => ['type' => 'string', 'description' => 'Initial stage (e.g. "New", "Contacted").'],
                    'priority' => ['type' => 'string', 'description' => 'Priority level (low, medium, high).']
                ],
                'required' => ['name']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $name = trim($params['name'] ?? '');
                $stmt = $db->prepare("INSERT INTO crm_leads (user_id, name, company, email, phone, services_required, budget, stage, priority, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $userId,
                    $name,
                    $params['company'] ?? null,
                    $params['email'] ?? null,
                    $params['phone'] ?? null,
                    $params['services_required'] ?? null,
                    (float)($params['budget'] ?? 0),
                    $params['stage'] ?? 'New',
                    $params['priority'] ?? 'medium'
                ]);
                $leadId = $db->lastInsertId();

                return [
                    'lead_id' => (int)$leadId,
                    'name' => $name,
                    'message' => "Lead '{$name}' created successfully."
                ];
            }
        ]);

        self::registerTool([
            'id' => 'get_hot_leads',
            'name' => 'Get Hot Leads',
            'description' => 'Retrieve top priority leads ranked by AI score, buying intent, and deal size.',
            'category' => 'crm',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                return LeadScoringEngine::getHotLeads($userId, $db);
            }
        ]);

        self::registerTool([
            'id' => 'get_lead_score',
            'name' => 'Get Lead Score & Signals',
            'description' => 'Calculate and retrieve explainable AI lead score, intent level, signals breakdown, and recommended next action.',
            'category' => 'crm',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'lead_id' => ['type' => 'integer', 'description' => 'Lead ID to score.']
                ],
                'required' => ['lead_id']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                return LeadScoringEngine::calculateLeadScore($userId, (int)($params['lead_id'] ?? 0), $db, !empty($params['recalculate']));
            }
        ]);

        // ==========================================
        // 2. DEALS & PIPELINES MODULE
        // ==========================================

        self::registerTool([
            'id' => 'search_deals',
            'name' => 'Search Deals',
            'description' => 'Fetch CRM pipeline deals filtered by title, stage, owner, or closing date.',
            'category' => 'deals',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search deal title or company.'],
                    'stage' => ['type' => 'string', 'description' => 'Filter by stage: Lead, Qualified, Proposal, Negotiation, Closed Won, Closed Lost.'],
                    'owner' => ['type' => 'string', 'description' => 'Deal owner.'],
                    'limit' => ['type' => 'integer', 'description' => 'Max deals to return (default 25).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $limit = min(50, max(1, (int)($params['limit'] ?? 25)));
                $query = "SELECT d.*, co.name AS company_name, c.name AS contact_name FROM crm_deals d LEFT JOIN crm_companies co ON d.company_id = co.id LEFT JOIN crm_contacts c ON d.contact_id = c.id WHERE d.user_id = :user_id";
                $binds = [':user_id' => $userId];

                if (!empty($params['query'])) {
                    $query .= " AND (d.title LIKE :q OR co.name LIKE :q OR c.name LIKE :q)";
                    $binds[':q'] = '%' . trim($params['query']) . '%';
                }
                if (!empty($params['stage'])) {
                    $query .= " AND d.stage = :stage";
                    $binds[':stage'] = trim($params['stage']);
                }

                $query .= " ORDER BY d.closing_date ASC LIMIT $limit";
                $stmt = $db->prepare($query);
                $stmt->execute($binds);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'create_deal',
            'name' => 'Create Deal',
            'description' => 'Create a new sales pipeline deal with title, stage, expected revenue, closing date, and optional contact association.',
            'category' => 'deals',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Title or name of the deal.'],
                    'expected_revenue' => ['type' => 'number', 'description' => 'Expected revenue or contract value in INR.'],
                    'stage' => ['type' => 'string', 'description' => 'Stage: "Lead", "Qualified", "Proposal", "Negotiation", "Closed Won", "Closed Lost". Default is "Lead".'],
                    'contact_id' => ['type' => 'integer', 'description' => 'Associated contact ID.'],
                    'company_name' => ['type' => 'string', 'description' => 'Associated company name.'],
                    'closing_date' => ['type' => 'string', 'description' => 'Target closing date in YYYY-MM-DD format.'],
                    'owner' => ['type' => 'string', 'description' => 'Owner or sales rep name.']
                ],
                'required' => ['title']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $title = trim($params['title'] ?? '');
                $revenue = (float)($params['expected_revenue'] ?? 0);
                $stage = trim($params['stage'] ?? 'Lead');
                $contactId = !empty($params['contact_id']) ? (int)$params['contact_id'] : null;
                $closingDate = !empty($params['closing_date']) ? $params['closing_date'] : date('Y-m-d', strtotime('+30 days'));

                $companyId = null;
                if (!empty($params['company_name'])) {
                    $stmtC = $db->prepare("SELECT id FROM crm_companies WHERE name = ? AND user_id = ? LIMIT 1");
                    $stmtC->execute([trim($params['company_name']), $userId]);
                    $companyId = $stmtC->fetchColumn() ?: null;
                }

                $stmt = $db->prepare("INSERT INTO crm_deals (user_id, company_id, contact_id, title, stage, expected_revenue, probability, owner, closing_date, created_at) VALUES (?, ?, ?, ?, ?, ?, 50, ?, ?, NOW())");
                $stmt->execute([$userId, $companyId, $contactId, $title, $stage, $revenue, $params['owner'] ?? 'You', $closingDate]);
                $dealId = $db->lastInsertId();

                return [
                    'deal_id' => (int)$dealId,
                    'title' => $title,
                    'stage' => $stage,
                    'expected_revenue' => $revenue,
                    'closing_date' => $closingDate,
                    'message' => "Deal '{$title}' created successfully."
                ];
            }
        ]);

        self::registerTool([
            'id' => 'move_deal_stage',
            'name' => 'Move Deal Stage',
            'description' => 'Update the pipeline stage of an existing deal (Lead, Qualified, Proposal, Negotiation, Closed Won, Closed Lost).',
            'category' => 'deals',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'deal_id' => ['type' => 'integer', 'description' => 'Deal ID to update.'],
                    'deal_title' => ['type' => 'string', 'description' => 'Match deal by title if ID is unknown.'],
                    'stage' => ['type' => 'string', 'description' => 'Target stage: "Lead", "Qualified", "Proposal", "Negotiation", "Closed Won", "Closed Lost".']
                ],
                'required' => ['stage']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $stage = trim($params['stage']);
                $dealId = (int)($params['deal_id'] ?? 0);
                $title = trim($params['deal_title'] ?? '');

                if ($dealId > 0) {
                    $stmt = $db->prepare("UPDATE crm_deals SET stage = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$stage, $dealId, $userId]);
                } elseif (!empty($title)) {
                    $stmt = $db->prepare("UPDATE crm_deals SET stage = ? WHERE title LIKE ? AND user_id = ?");
                    $stmt->execute([$stage, "%$title%", $userId]);
                } else {
                    throw new Exception("Either deal_id or deal_title is required.");
                }

                return [
                    'stage' => $stage,
                    'message' => "Deal stage successfully moved to '{$stage}'."
                ];
            }
        ]);

        self::registerTool([
            'id' => 'get_pipeline_summary',
            'name' => 'Pipeline Analytics Summary',
            'description' => 'Retrieve aggregate pipeline metrics, total forecasted deal revenue, and deals grouped by stage.',
            'category' => 'deals',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                $stmtDeals = $db->prepare("SELECT stage, COUNT(*) as count, SUM(expected_revenue) as total_revenue FROM crm_deals WHERE user_id = ? GROUP BY stage");
                $stmtDeals->execute([$userId]);
                $stages = $stmtDeals->fetchAll(PDO::FETCH_ASSOC);

                $totalPipelineRevenue = 0;
                $totalDeals = 0;
                foreach ($stages as $s) {
                    $totalPipelineRevenue += (float)$s['total_revenue'];
                    $totalDeals += (int)$s['count'];
                }

                return [
                    'total_deals' => $totalDeals,
                    'total_forecast_revenue' => $totalPipelineRevenue,
                    'stages' => $stages
                ];
            }
        ]);

        self::registerTool([
            'id' => 'get_at_risk_deals',
            'name' => 'Get At-Risk Deals',
            'description' => 'Identify stalled deals with no activity in over 14 days or approaching closing dates without follow-up.',
            'category' => 'deals',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                return SalesCopilotEngine::getRiskDeals($userId, $db);
            }
        ]);

        // ==========================================
        // 3. TASKS MODULE
        // ==========================================

        self::registerTool([
            'id' => 'search_tasks',
            'name' => 'Search Tasks',
            'description' => 'Search and filter CRM tasks by status (pending, in_progress, completed), priority, due date, or contact.',
            'category' => 'tasks',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'description' => 'Filter by status: "pending", "in_progress", "completed", "all". Default is "pending".'],
                    'priority' => ['type' => 'string', 'description' => 'Filter by priority: "low", "medium", "high".'],
                    'today_only' => ['type' => 'boolean', 'description' => 'If true, returns only tasks due today.'],
                    'contact_id' => ['type' => 'integer', 'description' => 'Filter tasks associated with a contact ID.'],
                    'limit' => ['type' => 'integer', 'description' => 'Maximum tasks to return (default 25).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $limit = min(50, max(1, (int)($params['limit'] ?? 25)));
                $query = "SELECT t.*, c.name AS contact_name, co.name AS company_name FROM crm_tasks t LEFT JOIN crm_contacts c ON t.contact_id = c.id LEFT JOIN crm_companies co ON t.company_id = co.id WHERE t.user_id = :user_id";
                $binds = [':user_id' => $userId];

                $status = $params['status'] ?? 'pending';
                if ($status !== 'all') {
                    $query .= " AND t.status = :status";
                    $binds[':status'] = $status;
                }
                if (!empty($params['priority'])) {
                    $query .= " AND t.priority = :priority";
                    $binds[':priority'] = trim($params['priority']);
                }
                if (!empty($params['today_only'])) {
                    $query .= " AND t.due_date = CURDATE()";
                }
                if (!empty($params['contact_id'])) {
                    $query .= " AND t.contact_id = :cid";
                    $binds[':cid'] = (int)$params['contact_id'];
                }

                $query .= " ORDER BY t.due_date ASC, t.priority DESC LIMIT $limit";
                $stmt = $db->prepare($query);
                $stmt->execute($binds);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'create_task',
            'name' => 'Create Task',
            'description' => 'Create a new CRM task with title, description, due date, due time, priority, and optional contact association.',
            'category' => 'tasks',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Title of the task.'],
                    'description' => ['type' => 'string', 'description' => 'Detailed notes or instructions for the task.'],
                    'due_date' => ['type' => 'string', 'description' => 'Due date in YYYY-MM-DD format (defaults to today).'],
                    'due_time' => ['type' => 'string', 'description' => 'Due time in HH:MM format (e.g. 15:30).'],
                    'priority' => ['type' => 'string', 'description' => 'Priority: "low", "medium", "high". Default is "medium".'],
                    'contact_id' => ['type' => 'integer', 'description' => 'Associated contact ID.'],
                    'contact_name' => ['type' => 'string', 'description' => 'Associated contact name (if contact_id is not known).'],
                    'sync_to_calendar' => ['type' => 'boolean', 'description' => 'If true, syncs task to Google Calendar if connected.']
                ],
                'required' => ['title']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $title = trim($params['title'] ?? '');
                $dueDate = !empty($params['due_date']) ? $params['due_date'] : date('Y-m-d');
                $dueTime = !empty($params['due_time']) ? $params['due_time'] : null;
                $priority = trim($params['priority'] ?? 'medium');
                $description = trim($params['description'] ?? '');

                $contactId = !empty($params['contact_id']) ? (int)$params['contact_id'] : null;
                if (!$contactId && !empty($params['contact_name'])) {
                    $stmtCon = $db->prepare("SELECT id FROM crm_contacts WHERE name LIKE ? AND user_id = ? LIMIT 1");
                    $stmtCon->execute(['%' . trim($params['contact_name']) . '%', $userId]);
                    $contactId = $stmtCon->fetchColumn() ?: null;
                }

                $stmt = $db->prepare("INSERT INTO crm_tasks (user_id, contact_id, title, description, due_date, due_time, priority, status, sync_to_calendar, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
                $syncCal = !empty($params['sync_to_calendar']) ? 1 : 0;
                $stmt->execute([$userId, $contactId, $title, $description, $dueDate, $dueTime, $priority, $syncCal]);
                $taskId = $db->lastInsertId();

                if ($syncCal && ExternalAppsHelper::isGoogleConnected($userId)) {
                    try {
                        ExternalAppsHelper::createGoogleCalendarEventFromTask($userId, $taskId, $title, $description, $dueDate, $dueTime);
                    } catch (Exception $e) {}
                }

                return [
                    'task_id' => (int)$taskId,
                    'title' => $title,
                    'due_date' => $dueDate,
                    'priority' => $priority,
                    'contact_id' => $contactId,
                    'message' => "Task '{$title}' created successfully."
                ];
            }
        ]);

        self::registerTool([
            'id' => 'complete_task',
            'name' => 'Complete Task',
            'description' => 'Mark an existing task as completed.',
            'category' => 'tasks',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'task_id' => ['type' => 'integer', 'description' => 'ID of the task to complete.'],
                    'task_title' => ['type' => 'string', 'description' => 'Match task by title if ID is unknown.']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $taskId = (int)($params['task_id'] ?? 0);
                $title = trim($params['task_title'] ?? '');

                if ($taskId > 0) {
                    $stmt = $db->prepare("UPDATE crm_tasks SET status = 'completed' WHERE id = ? AND user_id = ?");
                    $stmt->execute([$taskId, $userId]);
                } elseif (!empty($title)) {
                    $stmt = $db->prepare("UPDATE crm_tasks SET status = 'completed' WHERE title LIKE ? AND user_id = ?");
                    $stmt->execute(['%' . $title . '%', $userId]);
                } else {
                    throw new Exception("Task ID or title required.");
                }

                return ['status' => 'completed', 'message' => "Task marked as completed."];
            }
        ]);

        self::registerTool([
            'id' => 'get_overdue_tasks',
            'name' => 'Get Overdue Tasks',
            'description' => 'Fetch all pending tasks that are past their scheduled due date.',
            'category' => 'tasks',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("SELECT t.*, c.name as contact_name FROM crm_tasks t LEFT JOIN crm_contacts c ON t.contact_id = c.id WHERE t.user_id = ? AND t.status != 'completed' AND t.due_date < CURDATE() ORDER BY t.due_date ASC");
                $stmt->execute([$userId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        // ==========================================
        // 4. WHATSAPP MODULE
        // ==========================================

        self::registerTool([
            'id' => 'get_whatsapp_conversations',
            'name' => 'Get WhatsApp Conversations',
            'description' => 'Retrieve synchronized WhatsApp conversations list with contact name, phone, last message snippet, unread count, and timestamp.',
            'category' => 'whatsapp',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Search by contact name or phone number.'],
                    'limit' => ['type' => 'integer', 'description' => 'Max conversations to return (default 20).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $limit = min(50, max(1, (int)($params['limit'] ?? 20)));
                $search = trim($params['search'] ?? '');

                $sql = "SELECT c.id, c.wa_id, c.profile_name, c.unread_count, c.last_message_at,
                               (SELECT body FROM whatsapp_messages WHERE wa_contact_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_body,
                               (SELECT direction FROM whatsapp_messages WHERE wa_contact_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_direction
                        FROM whatsapp_contacts c
                        WHERE c.user_id = :user_id";
                $binds = [':user_id' => $userId];

                if (!empty($search)) {
                    $sql .= " AND (c.profile_name LIKE :s OR c.wa_id LIKE :s)";
                    $binds[':s'] = "%$search%";
                }

                $sql .= " ORDER BY c.last_message_at DESC LIMIT $limit";
                $stmt = $db->prepare($sql);
                $stmt->execute($binds);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'get_whatsapp_messages',
            'name' => 'Read WhatsApp Messages',
            'description' => 'Retrieve actual synchronized WhatsApp chat messages for a specific contact or phone number.',
            'category' => 'whatsapp',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'wa_contact_id' => ['type' => 'integer', 'description' => 'WhatsApp thread contact ID.'],
                    'phone' => ['type' => 'string', 'description' => 'Phone number of the contact.'],
                    'name' => ['type' => 'string', 'description' => 'Contact name.'],
                    'limit' => ['type' => 'integer', 'description' => 'Max messages to retrieve (default 25).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $limit = min(50, max(1, (int)($params['limit'] ?? 25)));
                $waContactId = (int)($params['wa_contact_id'] ?? 0);

                if ($waContactId <= 0 && (!empty($params['phone']) || !empty($params['name']))) {
                    $qPhone = preg_replace('/[^0-9]/', '', $params['phone'] ?? '');
                    $qName = trim($params['name'] ?? '');

                    if (!empty($qPhone)) {
                        $stmtFind = $db->prepare("SELECT id FROM whatsapp_contacts WHERE user_id = ? AND RIGHT(wa_id, 10) = RIGHT(?, 10) LIMIT 1");
                        $stmtFind->execute([$userId, $qPhone]);
                        $waContactId = (int)$stmtFind->fetchColumn();
                    }
                    if ($waContactId <= 0 && !empty($qName)) {
                        $stmtFind = $db->prepare("SELECT id FROM whatsapp_contacts WHERE user_id = ? AND profile_name LIKE ? ORDER BY last_message_at DESC LIMIT 1");
                        $stmtFind->execute([$userId, "%$qName%"]);
                        $waContactId = (int)$stmtFind->fetchColumn();
                    }
                }

                if ($waContactId <= 0) {
                    $stmtRecent = $db->prepare("SELECT id FROM whatsapp_contacts WHERE user_id = ? ORDER BY last_message_at DESC LIMIT 1");
                    $stmtRecent->execute([$userId]);
                    $waContactId = (int)$stmtRecent->fetchColumn();
                    if ($waContactId <= 0) {
                        return [
                            'found' => false,
                            'total_messages_returned' => 0,
                            'messages' => [],
                            'message' => "No synchronized WhatsApp conversation found matching the specified criteria."
                        ];
                    }
                }

                $stmt = $db->prepare("SELECT id, wa_contact_id, direction, type, body, status, created_at FROM whatsapp_messages WHERE user_id = ? AND wa_contact_id = ? ORDER BY created_at DESC LIMIT $limit");
                $stmt->execute([$userId, $waContactId]);
                $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

                return [
                    'wa_contact_id' => $waContactId,
                    'total_messages_returned' => count($messages),
                    'messages' => $messages
                ];
            }
        ]);

        self::registerTool([
            'id' => 'get_whatsapp_unreplied',
            'name' => 'Find Unreplied WhatsApp Customers',
            'description' => 'Find customers who sent an inbound WhatsApp message that has not received a response in the last N days.',
            'category' => 'whatsapp',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer', 'description' => 'Days threshold without reply (default 7).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $days = max(1, (int)($params['days'] ?? 7));

                // Find contacts where last message is inbound or unread_count > 0 or last outbound was > days ago
                $stmt = $db->prepare("
                    SELECT c.id AS wa_contact_id, c.profile_name, c.wa_id, c.contact_id, c.unread_count, c.last_message_at,
                           m.body AS last_message, m.direction AS last_direction, m.created_at AS message_time,
                           DATEDIFF(NOW(), m.created_at) AS days_waiting
                    FROM whatsapp_contacts c
                    JOIN whatsapp_messages m ON m.id = (
                        SELECT id FROM whatsapp_messages WHERE wa_contact_id = c.id ORDER BY created_at DESC LIMIT 1
                    )
                    WHERE c.user_id = ? 
                      AND m.direction = 'inbound'
                      AND m.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ORDER BY m.created_at DESC
                ");
                $stmt->execute([$userId, $days]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'send_whatsapp_message',
            'name' => 'Send WhatsApp Message',
            'description' => 'Send an outbound WhatsApp message to a customer phone number via Meta WhatsApp Cloud API.',
            'category' => 'whatsapp',
            'risk_level' => 'EXTERNAL',
            'approval_required' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'phone' => ['type' => 'string', 'description' => 'Recipient phone number with country code (e.g. 919876543210).'],
                    'message' => ['type' => 'string', 'description' => 'Message text to send.'],
                    'contact_id' => ['type' => 'integer', 'description' => 'Associated CRM contact ID.']
                ],
                'required' => ['phone', 'message']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                return CommunicationProviderHelper::sendCommunication(
                    $userId,
                    'whatsapp',
                    $params['phone'],
                    '',
                    $params['message'],
                    [],
                    'wa_' . uniqid(),
                    $params['contact_id'] ?? null,
                    $db
                );
            }
        ]);

        self::registerTool([
            'id' => 'list_whatsapp_templates',
            'name' => 'List WhatsApp Templates',
            'description' => 'Fetch all pre-approved Meta WhatsApp message templates available in LinkPilot.',
            'category' => 'whatsapp',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("SELECT id, name, category, language, status, components_json FROM whatsapp_templates WHERE user_id = ? ORDER BY name ASC");
                $stmt->execute([$userId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'list_whatsapp_campaigns',
            'name' => 'List WhatsApp Campaigns',
            'description' => 'Retrieve broadcast WhatsApp campaigns, delivery rates, and reply statistics.',
            'category' => 'whatsapp',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("SELECT id, name, template_name, status, total_contacts, sent_count, delivered_count, read_count, failed_count, created_at FROM whatsapp_campaigns WHERE user_id = ? ORDER BY id DESC LIMIT 20");
                $stmt->execute([$userId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        // ==========================================
        // 5. EMAIL MODULE
        // ==========================================

        self::registerTool([
            'id' => 'search_emails',
            'name' => 'Search Synchronized Emails',
            'description' => 'Search synchronized email messages across inbox, sent, starred, or category folders.',
            'category' => 'email',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search term matching sender, subject, or content.'],
                    'folder' => ['type' => 'string', 'description' => 'Folder filter: "inbox", "sent", "starred", "drafts", "spam". Default is "inbox".'],
                    'category' => ['type' => 'string', 'description' => 'AI email category: "Lead", "Invoice", "Follow-up", "Support", "Newsletter".'],
                    'limit' => ['type' => 'integer', 'description' => 'Max emails to return (default 20).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $limit = min(30, max(1, (int)($params['limit'] ?? 20)));
                $folder = strtolower(trim($params['folder'] ?? 'inbox'));

                if ($folder === 'sent') {
                    $stmt = $db->prepare("SELECT id, recipient_email, subject, body, status, created_at FROM sent_emails WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit");
                    $stmt->execute([$userId]);
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                $sql = "SELECT id, sender_name, sender_email, subject, category, ai_summary, received_date, is_read, is_starred FROM received_emails WHERE user_id = :user_id AND is_spam = 0 AND is_archived = 0";
                $binds = [':user_id' => $userId];

                if (!empty($params['query'])) {
                    $sql .= " AND (sender_name LIKE :q OR sender_email LIKE :q OR subject LIKE :q)";
                    $binds[':q'] = '%' . trim($params['query']) . '%';
                }
                if (!empty($params['category'])) {
                    $sql .= " AND category = :cat";
                    $binds[':cat'] = trim($params['category']);
                }

                $sql .= " ORDER BY received_date DESC LIMIT $limit";
                $stmt = $db->prepare($sql);
                $stmt->execute($binds);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'get_email',
            'name' => 'Get Email Details',
            'description' => 'Retrieve complete content, body, and AI summary of a synchronized email message.',
            'category' => 'email',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'email_id' => ['type' => 'integer', 'description' => 'ID of the email to fetch.']
                ],
                'required' => ['email_id']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $emailId = (int)($params['email_id'] ?? 0);
                $stmt = $db->prepare("SELECT * FROM received_emails WHERE id = ? AND user_id = ?");
                $stmt->execute([$emailId, $userId]);
                $email = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$email) {
                    $stmtSent = $db->prepare("SELECT * FROM sent_emails WHERE id = ? AND user_id = ?");
                    $stmtSent->execute([$emailId, $userId]);
                    $email = $stmtSent->fetch(PDO::FETCH_ASSOC);
                }

                if (!$email) throw new Exception("Email #{$emailId} not found.");
                return $email;
            }
        ]);

        self::registerTool([
            'id' => 'get_unreplied_emails',
            'name' => 'Get Unreplied Emails',
            'description' => 'Fetch incoming emails that need a response, classified by sales priority and urgency.',
            'category' => 'email',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'description' => 'Max emails to return (default 15).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                return EmailIntelligenceService::getUnrepliedEmails($userId, $db, (int)($params['limit'] ?? 15));
            }
        ]);

        self::registerTool([
            'id' => 'summarize_email_conversation',
            'name' => 'Summarize Email Correspondence',
            'description' => 'Summarize recent email communication thread with a specific contact or email address.',
            'category' => 'email',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'contact_name' => ['type' => 'string', 'description' => 'Contact name or email.']
                ],
                'required' => ['contact_name']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                return EmailIntelligenceService::summarizeConversation($userId, $params['contact_name'], $db);
            }
        ]);

        self::registerTool([
            'id' => 'send_email',
            'name' => 'Send Email',
            'description' => 'Send an outbound business email to a contact via configured SMTP or Gmail OAuth.',
            'category' => 'email',
            'risk_level' => 'EXTERNAL',
            'approval_required' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'recipient' => ['type' => 'string', 'description' => 'Recipient email address.'],
                    'subject' => ['type' => 'string', 'description' => 'Email subject line.'],
                    'body' => ['type' => 'string', 'description' => 'Email body content (HTML or plain text).'],
                    'contact_id' => ['type' => 'integer', 'description' => 'Associated contact ID.']
                ],
                'required' => ['recipient', 'subject', 'body']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                return CommunicationProviderHelper::sendCommunication(
                    $userId,
                    'email',
                    $params['recipient'],
                    $params['subject'],
                    $params['body'],
                    [],
                    'email_' . uniqid(),
                    $params['contact_id'] ?? null,
                    $db
                );
            }
        ]);

        self::registerTool([
            'id' => 'list_email_templates',
            'name' => 'List Email Templates',
            'description' => 'Retrieve available email templates and boilerplates stored in LinkPilot.',
            'category' => 'email',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("SELECT id, name, subject, category, tag FROM custom_email_templates WHERE user_id = ? ORDER BY id DESC");
                $stmt->execute([$userId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        // ==========================================
        // 6. AUTOMATION & WORKFLOWS MODULE
        // ==========================================

        self::registerTool([
            'id' => 'list_automations',
            'name' => 'List Automation Workflows',
            'description' => 'Fetch all trigger-action automation workflows, active statuses, and execution run counts.',
            'category' => 'automation',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'description' => 'Filter by status: "active", "draft", "all".']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $query = "SELECT id, name, trigger_type, trigger_value, is_active, created_at FROM automation_workflows WHERE user_id = ?";
                $binds = [$userId];

                if (!empty($params['status']) && $params['status'] !== 'all') {
                    if ($params['status'] === 'active') {
                        $query .= " AND is_active = 1";
                    } elseif ($params['status'] === 'draft' || $params['status'] === 'paused') {
                        $query .= " AND is_active = 0";
                    }
                }

                $query .= " ORDER BY id DESC";
                $stmt = $db->prepare($query);
                $stmt->execute($binds);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'toggle_automation',
            'name' => 'Toggle Automation Workflow',
            'description' => 'Activate or pause an automation workflow.',
            'category' => 'automation',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'workflow_id' => ['type' => 'integer', 'description' => 'ID of the workflow.'],
                    'is_active' => ['type' => 'boolean', 'description' => 'True to activate, false to pause.']
                ],
                'required' => ['workflow_id', 'is_active']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $wfId = (int)$params['workflow_id'];
                $isActive = $params['is_active'] ? 1 : 0;
                $status = $isActive ? 'active' : 'paused';

                $stmt = $db->prepare("UPDATE automation_workflows SET is_active = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$isActive, $wfId, $userId]);

                return [
                    'workflow_id' => $wfId,
                    'status' => $status,
                    'is_active' => (bool)$isActive,
                    'message' => "Workflow #{$wfId} status set to {$status}."
                ];
            }
        ]);

        // ==========================================
        // 7. CALENDAR & MEETINGS MODULE
        // ==========================================

        self::registerTool([
            'id' => 'list_meetings',
            'name' => 'List Meetings',
            'description' => 'Retrieve scheduled business meetings, upcoming appointments, start/end times, and meeting locations (Zoom, Google Meet).',
            'category' => 'calendar',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'upcoming_only' => ['type' => 'boolean', 'description' => 'If true, returns only upcoming meetings (default true).'],
                    'limit' => ['type' => 'integer', 'description' => 'Max meetings to return (default 20).']
                ]
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $limit = min(30, max(1, (int)($params['limit'] ?? 20)));
                $upcoming = !isset($params['upcoming_only']) || $params['upcoming_only'];

                $query = "SELECT m.*, co.name AS company_name, c.name AS contact_name FROM crm_meetings m LEFT JOIN crm_companies co ON m.company_id = co.id LEFT JOIN crm_contacts c ON m.contact_id = c.id WHERE m.user_id = :user_id";
                $binds = [':user_id' => $userId];

                if ($upcoming) {
                    $query .= " AND m.start_time >= NOW() AND m.status = 'scheduled'";
                }

                $query .= " ORDER BY m.start_time ASC LIMIT $limit";
                $stmt = $db->prepare($query);
                $stmt->execute($binds);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'create_meeting',
            'name' => 'Schedule Meeting',
            'description' => 'Schedule a calendar meeting with title, description, start time, end time, location (Zoom/Google Meet), and optional contact.',
            'category' => 'calendar',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Title or topic of the meeting.'],
                    'start_time' => ['type' => 'string', 'description' => 'Start timestamp in YYYY-MM-DD HH:MM:SS format.'],
                    'end_time' => ['type' => 'string', 'description' => 'End timestamp in YYYY-MM-DD HH:MM:SS format.'],
                    'description' => ['type' => 'string', 'description' => 'Agenda or meeting details.'],
                    'location' => ['type' => 'string', 'description' => 'Location (e.g. "Google Meet", "Zoom Meeting", or physical address).'],
                    'contact_id' => ['type' => 'integer', 'description' => 'Contact ID of invitee.']
                ],
                'required' => ['title', 'start_time']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                $title = trim($params['title'] ?? '');
                $startTime = trim($params['start_time'] ?? '');
                $endTime = !empty($params['end_time']) ? trim($params['end_time']) : date('Y-m-d H:i:s', strtotime($startTime . ' +30 minutes'));
                $location = trim($params['location'] ?? 'Google Meet');
                $contactId = !empty($params['contact_id']) ? (int)$params['contact_id'] : null;

                $stmt = $db->prepare("INSERT INTO crm_meetings (user_id, contact_id, title, description, start_time, end_time, location, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())");
                $stmt->execute([$userId, $contactId, $title, $params['description'] ?? '', $startTime, $endTime, $location]);
                $meetingId = $db->lastInsertId();

                // Sync to Google Calendar if connected
                if (ExternalAppsHelper::isGoogleConnected($userId)) {
                    try {
                        ExternalAppsHelper::createGoogleCalendarEvent($userId, $meetingId, $title, $params['description'] ?? '', $startTime, $endTime, $location, $contactId);
                    } catch (Exception $e) {}
                }

                return [
                    'meeting_id' => (int)$meetingId,
                    'title' => $title,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'location' => $location,
                    'message' => "Meeting '{$title}' scheduled successfully for {$startTime}."
                ];
            }
        ]);

        // ==========================================
        // 8. ANALYTICS & EXECUTIVE BRIEFING
        // ==========================================

        self::registerTool([
            'id' => 'get_daily_briefing',
            'name' => 'Get Daily Business Briefing',
            'description' => 'Generate an executive AI briefing of today priorities, meetings, overdue tasks, hot leads, and at-risk deals.',
            'category' => 'analytics',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                return SalesCopilotEngine::getDailyBriefing($userId, $db);
            }
        ]);

        self::registerTool([
            'id' => 'get_business_analytics',
            'name' => 'Get Business Analytics',
            'description' => 'Fetch key performance indicators: total leads, deals won, conversion rates, emails sent, and task completion metrics.',
            'category' => 'analytics',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                // Leads Count & Stage
                $stmtLeads = $db->prepare("SELECT COUNT(*) as total_leads FROM crm_leads WHERE user_id = ?");
                $stmtLeads->execute([$userId]);
                $totalLeads = (int)$stmtLeads->fetchColumn();

                // Deals Count & Revenue
                $stmtDeals = $db->prepare("SELECT COUNT(*) as total_deals, SUM(expected_revenue) as total_revenue, SUM(CASE WHEN stage = 'Closed Won' THEN expected_revenue ELSE 0 END) as won_revenue FROM crm_deals WHERE user_id = ?");
                $stmtDeals->execute([$userId]);
                $dealsData = $stmtDeals->fetch(PDO::FETCH_ASSOC);

                // Tasks stats
                $stmtTasks = $db->prepare("SELECT COUNT(*) as total_tasks, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks FROM crm_tasks WHERE user_id = ?");
                $stmtTasks->execute([$userId]);
                $tasksData = $stmtTasks->fetch(PDO::FETCH_ASSOC);

                // Communications stats
                $stmtEmails = $db->prepare("SELECT COUNT(*) FROM sent_emails WHERE user_id = ?");
                $stmtEmails->execute([$userId]);
                $emailsSent = (int)$stmtEmails->fetchColumn();

                $stmtWa = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'outbound'");
                $stmtWa->execute([$userId]);
                $waSent = (int)$stmtWa->fetchColumn();

                return [
                    'total_leads' => $totalLeads,
                    'total_deals' => (int)($dealsData['total_deals'] ?? 0),
                    'total_forecast_revenue' => (float)($dealsData['total_revenue'] ?? 0),
                    'won_revenue' => (float)($dealsData['won_revenue'] ?? 0),
                    'total_tasks' => (int)($tasksData['total_tasks'] ?? 0),
                    'completed_tasks' => (int)($tasksData['completed_tasks'] ?? 0),
                    'outbound_emails' => $emailsSent,
                    'outbound_whatsapp' => $waSent
                ];
            }
        ]);

        // ==========================================
        // 9. SETTINGS & INTEGRATIONS MODULE
        // ==========================================

        self::registerTool([
            'id' => 'get_account_status',
            'name' => 'Get Account & Integration Status',
            'description' => 'Check connectivity status of WhatsApp, Email (SMTP/Gmail), Google Calendar, Zoom, and AI providers.',
            'category' => 'settings',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                $emailStatus = CommunicationProviderHelper::validateConnection($userId, 'email');
                $waStatus = CommunicationProviderHelper::validateConnection($userId, 'whatsapp');
                $googleStatus = ExternalAppsHelper::isGoogleConnected($userId);
                $zoomStatus = ExternalAppsHelper::isZoomConnected($userId);

                // User profile
                $stmt = $db->prepare("SELECT u.name, u.email, u.role, p.company_name FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
                $stmt->execute([$userId]);
                $userProf = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'user' => $userProf,
                    'integrations' => [
                        'whatsapp' => [
                            'connected' => $waStatus['connected'],
                            'provider' => $waStatus['provider'] ?? 'Not Connected'
                        ],
                        'email' => [
                            'connected' => $emailStatus['connected'],
                            'provider' => $emailStatus['provider'] ?? 'Not Connected'
                        ],
                        'google_calendar' => [
                            'connected' => $googleStatus,
                            'provider' => $googleStatus ? 'Google OAuth 2.0' : 'Not Connected'
                        ],
                        'zoom' => [
                            'connected' => $zoomStatus,
                            'provider' => $zoomStatus ? 'Zoom OAuth 2.0' : 'Not Connected'
                        ]
                    ]
                ];
            }
        ]);

        self::registerTool([
            'id' => 'get_credit_balance',
            'name' => 'Get Wallet Balance',
            'description' => 'Retrieve current balance of LinkPilot credits for AI operations and WhatsApp messages.',
            'category' => 'settings',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => ['type' => 'object', 'properties' => []],
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("SELECT total_credits, used_credits, remaining_credits, free_credits, purchased_credits FROM user_email_credits WHERE user_id = ?");
                $stmt->execute([$userId]);
                $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$wallet) {
                    $wallet = [
                        'total_credits' => 200,
                        'used_credits' => 0,
                        'remaining_credits' => 200,
                        'free_credits' => 200,
                        'purchased_credits' => 0
                    ];
                }
                return [
                    'remaining_credits' => (int)($wallet['remaining_credits'] ?? 0),
                    'free_credits' => (int)($wallet['free_credits'] ?? 0),
                    'purchased_credits' => (int)($wallet['purchased_credits'] ?? 0),
                    'used_credits' => (int)($wallet['used_credits'] ?? 0),
                    'total_credits' => (int)($wallet['total_credits'] ?? 0),
                    'formatted' => number_format((int)($wallet['remaining_credits'] ?? 0)) . " Credits"
                ];
            }
        ]);

        // ==========================================
        // 9. LIVE WEB, REAL-TIME WEATHER & SCRAPING
        // ==========================================

        self::registerTool([
            'id' => 'get_live_weather',
            'name' => 'Get Live Weather',
            'description' => 'Fetch exact real-time weather conditions, current temperature, humidity, wind, and forecast for any city or location.',
            'category' => 'web',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'Name of the city or location (e.g. "Kolkata", "Mumbai", "London", "New York").'
                    ]
                ],
                'required' => ['location']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                return WebSearchHelper::getWeather($params['location'] ?? '');
            }
        ]);

        self::registerTool([
            'id' => 'web_search',
            'name' => 'Search Google & Web',
            'description' => 'Search Google and the live web for real-time information, current news, facts, market research, or competitor details.',
            'category' => 'web',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query keywords to find on Google and the live web.'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of search results to return (default 5, max 10).'
                    ]
                ],
                'required' => ['query']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                return WebSearchHelper::searchWeb($params['query'] ?? '', $params['limit'] ?? 5);
            }
        ]);

        self::registerTool([
            'id' => 'fetch_webpage_content',
            'name' => 'Fetch Webpage Content',
            'description' => 'Fetch, read, and extract clean text, pricing, articles, documentation, or data from any specific public website URL.',
            'category' => 'web',
            'risk_level' => 'READ',
            'approval_required' => false,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'url' => [
                        'type' => 'string',
                        'description' => 'Full public HTTP or HTTPS website URL (e.g. "https://example.com/pricing").'
                    ],
                    'instruction' => [
                        'type' => 'string',
                        'description' => 'Optional instruction on what specific information to extract or focus on.'
                    ]
                ],
                'required' => ['url']
            ],
            'handler' => function($userId, array $params, PDO $db) {
                return WebSearchHelper::fetchUrlContent($params['url'] ?? '', $params['instruction'] ?? '');
            }
        ]);
    }

    public static function registerTool(array $tool) {
        self::$tools[$tool['id']] = $tool;
    }

    public static function getTool($toolId) {
        self::init();
        return self::$tools[$toolId] ?? null;
    }

    public static function listTools() {
        self::init();
        if (class_exists('AgentCache')) {
            $cached = AgentCache::get('agent_tools_list');
            if (!empty($cached)) return $cached;
        }

        $list = [];
        foreach (self::$tools as $t) {
            $list[] = [
                'id' => $t['id'],
                'name' => $t['name'],
                'description' => $t['description'],
                'category' => $t['category'] ?? 'general',
                'risk_level' => $t['risk_level'],
                'approval_required' => $t['approval_required'],
                'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => []]
            ];
        }

        if (class_exists('AgentCache')) {
            AgentCache::set('agent_tools_list', $list, 3600);
        }
        return $list;
    }

    /**
     * Converts registered tools to OpenAI function calling format with caching.
     */
    public static function getOpenAITools() {
        self::init();
        if (class_exists('AgentCache')) {
            $cached = AgentCache::get('agent_openai_tools');
            if (!empty($cached)) return $cached;
        }

        $openAITools = [];
        foreach (self::$tools as $t) {
            $params = $t['parameters'] ?? ['type' => 'object', 'properties' => (object)[]];
            if (isset($params['properties']) && is_array($params['properties']) && empty($params['properties'])) {
                $params['properties'] = (object)[];
            }
            $openAITools[] = [
                'type' => 'function',
                'category' => $t['category'] ?? 'general',
                'function' => [
                    'name' => $t['id'],
                    'description' => $t['description'],
                    'parameters' => $params
                ]
            ];
        }

        if (class_exists('AgentCache')) {
            AgentCache::set('agent_openai_tools', $openAITools, 3600);
        }
        return $openAITools;
    }

    /**
     * Category-based tool loading.
     * Loads ONLY tools belonging to the requested functional domains.
     */
    public static function getToolsByCategories(array $categories) {
        if (empty($categories)) return [];
        $all = self::getOpenAITools();
        $categories = array_map('strtolower', $categories);

        $filtered = array_filter($all, function($t) use ($categories) {
            $toolCat = strtolower($t['category'] ?? 'general');
            return in_array($toolCat, $categories);
        });

        // Strip internal category tag before sending to external LLM and ensure properties is always an object
        return array_values(array_map(function($t) {
            $fn = $t['function'];
            if (isset($fn['parameters']) && is_array($fn['parameters'])) {
                if (empty($fn['parameters']['properties']) || (is_array($fn['parameters']['properties']) && count($fn['parameters']['properties']) === 0)) {
                    $fn['parameters']['properties'] = (object)[];
                }
            }
            return [
                'type' => 'function',
                'function' => $fn
            ];
        }, $filtered));
    }

    /**
     * Dynamically filters tools based on query intent or router classification.
     * Prevents massive 37KB payload dumps (43 tools) to external LLMs, eliminating timeouts and cutting latency by 80%.
     */
    public static function getToolsForMessage($userMessage, ?array $routerClassification = null) {
        // If router classification is provided, obey execution path directly
        if ($routerClassification !== null) {
            $path = $routerClassification['path'] ?? '';
            if ($path === 'instant' || $path === 'simple_ai') {
                return [];
            }
            if (!empty($routerClassification['categories'])) {
                $tools = self::getToolsByCategories($routerClassification['categories']);
                if (!empty($tools)) return $tools;
            }
        }

        $msg = strtolower($userMessage);
        $clean = preg_replace('/[^\w\s]/u', ' ', $msg);
        $all = self::getOpenAITools();
        $selectedCategories = [];

        // 1. Web & Live Data Intent (Only if specifically asking for live weather or explicit search)
        if (preg_match('/\b(weather|temperature|forecast|rain|climate|humidity|degrees?\s*(?:c|f)?|celsius|fahrenheit)\b/i', $clean)) {
            $selectedCategories[] = 'web';
        }
        if (preg_match('/\b(search\s+google|google\s+search|web\s+search|search\s+the\s+web|latest\s+(?:news|stocks|market|crypto))\b/i', $clean)) {
            $selectedCategories[] = 'web';
        }
        if (preg_match('/https?:\/\/[^\s]+/i', $userMessage) || preg_match('/\b(url|website|link|page|scrape|summarize\s+this\s+page)\b/i', $clean)) {
            $selectedCategories[] = 'web';
        }

        // 2. CRM & Leads Intent
        if (preg_match('/\b(contact|contacts|lead|leads|customer|customers|client|person|people|tag|tags|note|notes)\b/i', $clean)) {
            $selectedCategories[] = 'crm';
        }

        // 3. Deals & Sales Pipeline Intent
        if (preg_match('/\b(deal|deals|pipeline|stage|revenue|sale|sales|at\s+risk|stalled)\b/i', $clean)) {
            $selectedCategories[] = 'deals';
        }

        // 4. Tasks Intent
        if (preg_match('/\b(task|tasks|todo|to-do|reminder|reminders|schedule|pending|overdue)\b/i', $clean)) {
            $selectedCategories[] = 'tasks';
        }

        // 5. WhatsApp Intent
        if (preg_match('/\b(whatsapp|wa\s+chat|wa\s+message|unreplied|campaign|template|broadcast)\b/i', $clean)) {
            $selectedCategories[] = 'whatsapp';
        }

        // 6. Email Intent
        if (preg_match('/\b(email|emails|mail|inbox|thread|draft|send\s+email)\b/i', $clean)) {
            $selectedCategories[] = 'email';
        }

        // 7. Calendar Intent
        if (preg_match('/\b(meeting|meetings|calendar|schedule\s+meeting|zoom|google\s+meet|appointment)\b/i', $clean)) {
            $selectedCategories[] = 'meetings';
        }

        // 8. Analytics & Settings Intent
        if (preg_match('/\b(briefing|analytics|metric|metrics|kpi|credit|credits|balance|wallet|report)\b/i', $clean)) {
            $selectedCategories[] = 'analytics';
        }

        if (!empty($selectedCategories)) {
            return self::getToolsByCategories(array_unique($selectedCategories));
        }

        // Safe Fallback: focused core set of 5 read tools only
        $fallbackIds = ['search_contacts', 'search_deals', 'search_tasks', 'get_whatsapp_unreplied', 'get_daily_briefing'];
        return array_values(array_map(function($t) {
            $fn = $t['function'];
            if (isset($fn['parameters']) && is_array($fn['parameters'])) {
                if (empty($fn['parameters']['properties']) || (is_array($fn['parameters']['properties']) && count($fn['parameters']['properties']) === 0)) {
                    $fn['parameters']['properties'] = (object)[];
                }
            }
            return [
                'type' => 'function',
                'function' => $fn
            ];
        }, array_filter($all, function($t) use ($fallbackIds) {
            return in_array($t['function']['name'], $fallbackIds);
        })));
    }

    public static function executeTool($toolId, $userId, array $params, PDO $db) {
        self::init();
        $tool = self::getTool($toolId);
        if (!$tool) {
            throw new Exception("Tool '{$toolId}' not found in AI Tool Registry.");
        }
        $handler = $tool['handler'];
        return $handler($userId, $params, $db);
    }
}
