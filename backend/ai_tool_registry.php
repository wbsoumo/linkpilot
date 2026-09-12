<?php
// backend/ai_tool_registry.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crm_sync_helper.php';
require_once __DIR__ . '/communication_provider_helper.php';
require_once __DIR__ . '/workflow_runner.php';
require_once __DIR__ . '/lead_scoring_engine.php';
require_once __DIR__ . '/email_intelligence_service.php';
require_once __DIR__ . '/sales_copilot_engine.php';

class AIToolRegistry {
    private static $tools = [];

    public static function init() {
        if (!empty(self::$tools)) return;

        // READ Tools
        self::registerTool([
            'id' => 'get_lead_score',
            'name' => 'Get Lead Score & Signals',
            'description' => 'Calculate and retrieve explainable AI lead score, intent level, signals breakdown, and recommended next action.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return LeadScoringEngine::calculateLeadScore($userId, (int)($params['lead_id'] ?? 0), $db, !empty($params['recalculate']));
            }
        ]);

        self::registerTool([
            'id' => 'get_hot_leads',
            'name' => 'Get Hot Leads',
            'description' => 'Retrieve top priority leads ranked by AI score, buying intent, and deal size.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return LeadScoringEngine::getHotLeads($userId, $db);
            }
        ]);

        self::registerTool([
            'id' => 'get_lead_score_history',
            'name' => 'Get Score History',
            'description' => 'Fetch historical score changes and reasons for a lead.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("SELECT * FROM crm_lead_score_history WHERE user_id = ? AND lead_id = ? ORDER BY created_at DESC LIMIT 20");
                $stmt->execute([(int)$userId, (int)($params['lead_id'] ?? 0)]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        // READ Tools
        self::registerTool([
            'id' => 'search_contacts',
            'name' => 'Search Contacts',
            'description' => 'Search and filter CRM contacts by query, city, company, tag, or days since last contacted.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return CRMSyncHelper::searchContacts($userId, $params, 25, 0, $db);
            }
        ]);

        self::registerTool([
            'id' => 'get_contact',
            'name' => 'Get Contact Details',
            'description' => 'Fetch complete details, notes, tags, and timeline for a single contact.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return CRMSyncHelper::resolveContact($userId, $params['email'] ?? null, $params['phone'] ?? null, $params['name'] ?? null, $db);
            }
        ]);

        self::registerTool([
            'id' => 'search_leads',
            'name' => 'Search Pipeline Leads',
            'description' => 'Fetch pipeline leads by stage, priority, budget, or text query.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $q = '%' . trim($params['q'] ?? $params['stage'] ?? '') . '%';
                $stmt = $db->prepare("SELECT * FROM crm_leads WHERE user_id = ? AND (name LIKE ? OR company LIKE ? OR stage LIKE ?) ORDER BY id DESC LIMIT 25");
                $stmt->execute([$userId, $q, $q, $q]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'search_deals',
            'name' => 'Search Deals',
            'description' => 'Fetch CRM deals by title, owner, expected revenue, or stage.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $q = '%' . trim($params['q'] ?? $params['title'] ?? '') . '%';
                $stmt = $db->prepare("SELECT * FROM crm_deals WHERE user_id = ? AND (title LIKE ? OR stage LIKE ?) ORDER BY id DESC LIMIT 25");
                $stmt->execute([$userId, $q, $q]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'search_tasks',
            'name' => 'Search Tasks',
            'description' => 'Fetch CRM tasks by title, status, priority, or due date.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("SELECT * FROM crm_tasks WHERE user_id = ? AND status != 'completed' ORDER BY due_date ASC LIMIT 25");
                $stmt->execute([$userId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'search_automations',
            'name' => 'Search Workflows',
            'description' => 'Fetch active or draft automation workflows and run counts.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("SELECT * FROM automation_workflows WHERE user_id = ? ORDER BY id DESC LIMIT 25");
                $stmt->execute([$userId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]);

        self::registerTool([
            'id' => 'get_pipeline_summary',
            'name' => 'Pipeline Analytics Summary',
            'description' => 'Fetch actual backend sales pipeline metrics, total lead budget, and conversion stats.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $stmtLeads = $db->prepare("SELECT COUNT(*) as total_leads, SUM(budget) as total_budget, stage FROM crm_leads WHERE user_id = ? GROUP BY stage");
                $stmtLeads->execute([$userId]);
                $leadsByStage = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

                $stmtDeals = $db->prepare("SELECT COUNT(*) as total_deals, SUM(expected_revenue) as total_revenue, stage FROM crm_deals WHERE user_id = ? GROUP BY stage");
                $stmtDeals->execute([$userId]);
                $dealsByStage = $stmtDeals->fetchAll(PDO::FETCH_ASSOC);

                return [
                    'leads_by_stage' => $leadsByStage,
                    'deals_by_stage' => $dealsByStage
                ];
            }
        ]);

        self::registerTool([
            'id' => 'get_unreplied_emails',
            'name' => 'Get Unreplied Emails',
            'description' => 'Fetch emails that need a response, classified by urgency and sales intent.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return EmailIntelligenceService::getUnrepliedEmails($userId, $db, (int)($params['limit'] ?? 15));
            }
        ]);

        self::registerTool([
            'id' => 'summarize_email_conversation',
            'name' => 'Summarize Conversation',
            'description' => 'Summarize recent email communication thread with a contact.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return EmailIntelligenceService::summarizeConversation($userId, $params['contact_name'] ?? $params['query'] ?? '', $db);
            }
        ]);

        self::registerTool([
            'id' => 'get_risk_deals',
            'name' => 'Get At-Risk Deals',
            'description' => 'Identify stalled or at-risk deals with inactive days and evidence.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return SalesCopilotEngine::getRiskDeals($userId, $db);
            }
        ]);

        self::registerTool([
            'id' => 'get_daily_briefing',
            'name' => 'Get Daily Briefing',
            'description' => 'Generate an AI daily briefing of today priorities, hot leads, meetings, and overdue tasks.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return SalesCopilotEngine::getDailyBriefing($userId, $db);
            }
        ]);

        self::registerTool([
            'id' => 'global_search',
            'name' => 'Unified AI Search',
            'description' => 'Natural language search across contacts, leads, deals, tasks, and communications.',
            'risk_level' => 'READ',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return CRMSyncHelper::searchContacts($userId, ['query' => $params['query'] ?? ''], 20, 0, $db);
            }
        ]);

        // WRITE Tools
        self::registerTool([
            'id' => 'create_contact',
            'name' => 'Create Contact',
            'description' => 'Create a new CRM contact record.',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return CRMSyncHelper::resolveContact($userId, $params, $db);
            }
        ]);

        self::registerTool([
            'id' => 'update_contact',
            'name' => 'Update Contact',
            'description' => 'Update contact fields (phone, email, designation, company, owner).',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $contactId = (int)($params['contact_id'] ?? 0);
                if ($contactId <= 0) throw new Exception("Contact ID required");
                $stmt = $db->prepare("UPDATE crm_contacts SET name = COALESCE(?, name), phone = COALESCE(?, phone), email = COALESCE(?, email) WHERE id = ? AND user_id = ?");
                $stmt->execute([$params['name'] ?? null, $params['phone'] ?? null, $params['email'] ?? null, $contactId, $userId]);
                return ['updated_contact_id' => $contactId];
            }
        ]);

        self::registerTool([
            'id' => 'add_contact_tag',
            'name' => 'Add Tag',
            'description' => 'Add a color tag to a contact profile.',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return CRMSyncHelper::addContactTag($userId, (int)$params['contact_id'], $params['tag_name'], $params['color_code'] ?? '#3b82f6', $db);
            }
        ]);

        self::registerTool([
            'id' => 'add_contact_note',
            'name' => 'Add Note',
            'description' => 'Add a note entry to a contact timeline.',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                return CRMSyncHelper::addContactNote($userId, (int)$params['contact_id'], $params['note_text'], $params['author_name'] ?? 'AI Co-Pilot', $db);
            }
        ]);

        self::registerTool([
            'id' => 'create_task',
            'name' => 'Create Task',
            'description' => 'Create a task or scheduled call due date.',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("INSERT INTO crm_tasks (user_id, contact_id, title, description, due_date, due_time, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([
                    $userId,
                    $params['contact_id'] ?? null,
                    $params['title'] ?? 'New AI Task',
                    $params['description'] ?? '',
                    $params['due_date'] ?? date('Y-m-d'),
                    $params['due_time'] ?? null,
                    $params['priority'] ?? 'medium'
                ]);
                return ['task_id' => $db->lastInsertId()];
            }
        ]);

        self::registerTool([
            'id' => 'change_deal_stage',
            'name' => 'Move Deal Stage',
            'description' => 'Change stage of a pipeline deal.',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("UPDATE crm_deals SET stage = ? WHERE user_id = ? AND (id = ? OR title LIKE ?)");
                $stmt->execute([$params['stage'], $userId, $params['deal_id'] ?? 0, '%' . ($params['title'] ?? '') . '%']);
                return ['stage_updated' => $params['stage']];
            }
        ]);

        self::registerTool([
            'id' => 'create_automation',
            'name' => 'Create Automation Workflow',
            'description' => 'Build and save a new trigger-action workflow sequence.',
            'risk_level' => 'WRITE',
            'approval_required' => false,
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("INSERT INTO automation_workflows (user_id, name, description, trigger_type, nodes_json, edges_json, status, is_active) VALUES (?, ?, ?, ?, ?, ?, 'active', 1)");
                $stmt->execute([
                    $userId,
                    $params['name'] ?? 'AI Automation Workflow',
                    $params['description'] ?? 'Created via AI Command Center',
                    $params['trigger_type'] ?? 'lead.created',
                    json_encode($params['nodes'] ?? []),
                    json_encode($params['edges'] ?? [])
                ]);
                return ['workflow_id' => $db->lastInsertId()];
            }
        ]);

        // EXTERNAL Tools (Communication)
        self::registerTool([
            'id' => 'send_email',
            'name' => 'Send Email',
            'description' => 'Send external communication email to contact.',
            'risk_level' => 'EXTERNAL',
            'approval_required' => true,
            'handler' => function($userId, array $params, PDO $db) {
                return CommunicationProviderHelper::sendCommunication(
                    $userId,
                    'email',
                    $params['recipient'] ?? ($params['email'] ?? ''),
                    $params['subject'] ?? 'Message from LinkPilot AI',
                    $params['message'] ?? ($params['body'] ?? ''),
                    $params['attachments'] ?? [],
                    $params['idempotency_token'] ?? null,
                    $params['contact_id'] ?? null,
                    $db
                );
            }
        ]);

        self::registerTool([
            'id' => 'send_whatsapp',
            'name' => 'Send WhatsApp',
            'description' => 'Send external WhatsApp message to phone number.',
            'risk_level' => 'EXTERNAL',
            'approval_required' => true,
            'handler' => function($userId, array $params, PDO $db) {
                return CommunicationProviderHelper::sendCommunication(
                    $userId,
                    'whatsapp',
                    $params['recipient'] ?? ($params['phone'] ?? ''),
                    '',
                    $params['message'] ?? '',
                    $params['attachments'] ?? [],
                    $params['idempotency_token'] ?? null,
                    $params['contact_id'] ?? null,
                    $db
                );
            }
        ]);

        // DESTRUCTIVE Tools
        self::registerTool([
            'id' => 'archive_contact',
            'name' => 'Archive Contact',
            'description' => 'Archive contact record from active CRM lists.',
            'risk_level' => 'DESTRUCTIVE',
            'approval_required' => true,
            'handler' => function($userId, array $params, PDO $db) {
                $stmt = $db->prepare("UPDATE crm_contacts SET is_archived = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([(int)$params['contact_id'], $userId]);
                return ['archived_contact_id' => (int)$params['contact_id']];
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
        $list = [];
        foreach (self::$tools as $t) {
            $list[] = [
                'id' => $t['id'],
                'name' => $t['name'],
                'description' => $t['description'],
                'risk_level' => $t['risk_level'],
                'approval_required' => $t['approval_required']
            ];
        }
        return $list;
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
