<?php
// backend/agent_context_engine.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crm_sync_helper.php';
require_once __DIR__ . '/smtp_helper.php';

class AgentContextEngine {

    /**
     * Builds complete environment & active workspace context for the AI Agent.
     */
    public static function buildUserContext($userId, array $requestMeta = [], ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();

        // 1. User & Company profile details
        $stmtUser = $db->prepare("SELECT u.id, u.name, u.email, u.phone_number, p.company_name, p.industry, p.timezone FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $userProf = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];

        // 2. Integration availability checks
        $smtpConfig = SMTPHelper::getSMTPConfig($userId);
        $stmtGmail = $db->prepare("SELECT setting_value FROM external_app_settings WHERE user_id = ? AND app_name = 'google_gmail' AND setting_key = 'access_token' LIMIT 1");
        $stmtGmail->execute([$userId]);
        $gmailToken = $stmtGmail->fetchColumn();

        $stmtWa = $db->prepare("SELECT id FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
        $stmtWa->execute([$userId]);
        $hasWa = (bool)$stmtWa->fetchColumn();

        $integrations = [
            'email' => [
                'connected' => !empty($smtpConfig) || !empty($gmailToken),
                'provider' => $smtpConfig ? ($smtpConfig['host'] ?? 'SMTP') : ($gmailToken ? 'Google Gmail OAuth' : 'Not Connected')
            ],
            'whatsapp' => [
                'connected' => $hasWa,
                'provider' => $hasWa ? 'Meta WhatsApp Cloud API' : 'Not Connected'
            ],
            'voice_calls' => [
                'connected' => false,
                'provider' => 'Not Supported (Phone calling capability not installed)'
            ],
            'calendar' => [
                'connected' => true,
                'provider' => 'LinkPilot Built-in CRM Calendar'
            ]
        ];

        // 3. Fetch active workspace summary
        $stmtContacts = $db->prepare("SELECT id, name, email, phone, company_id FROM crm_contacts WHERE user_id = ? AND is_archived = 0 ORDER BY id DESC LIMIT 40");
        $stmtContacts->execute([$userId]);
        $contacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

        $stmtDeals = $db->prepare("SELECT id, title, stage, expected_revenue, contact_id FROM crm_deals WHERE user_id = ? ORDER BY id DESC LIMIT 20");
        $stmtDeals->execute([$userId]);
        $deals = $stmtDeals->fetchAll(PDO::FETCH_ASSOC);

        $stmtTasks = $db->prepare("SELECT id, title, due_date, priority, status, contact_id FROM crm_tasks WHERE user_id = ? AND status != 'completed' ORDER BY due_date ASC LIMIT 15");
        $stmtTasks->execute([$userId]);
        $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        return [
            'user' => [
                'id' => $userId,
                'name' => $userProf['name'] ?? 'User',
                'email' => $userProf['email'] ?? '',
                'company' => $userProf['company_name'] ?? 'LinkPilot Workspace',
                'timezone' => $userProf['timezone'] ?? 'Asia/Kolkata'
            ],
            'integrations' => $integrations,
            'workspace' => [
                'recent_contacts' => $contacts,
                'recent_deals' => $deals,
                'pending_tasks' => $tasks
            ],
            'current_page' => $requestMeta['current_page'] ?? 'dashboard',
            'active_entity' => $requestMeta['active_entity'] ?? null
        ];
    }

    /**
     * Entity Resolution: Matches natural language names/terms to actual CRM database records.
     */
    public static function resolveEntity($userId, $query, ?PDO $db = null) {
        if (!$db) $db = Database::getConnection();
        $query = trim($query);
        if (empty($query)) return null;

        // Search Contacts
        $stmtCon = $db->prepare("SELECT id, name, email, phone, company_id FROM crm_contacts WHERE user_id = ? AND is_archived = 0 AND (name LIKE ? OR email LIKE ? OR phone LIKE ?) LIMIT 10");
        $searchTerm = "%$query%";
        $stmtCon->execute([$userId, $searchTerm, $searchTerm, $searchTerm]);
        $matchedContacts = $stmtCon->fetchAll(PDO::FETCH_ASSOC);

        // Search Deals
        $stmtDeals = $db->prepare("SELECT id, title, stage, expected_revenue, contact_id FROM crm_deals WHERE user_id = ? AND (title LIKE ? OR stage LIKE ?) LIMIT 10");
        $stmtDeals->execute([$userId, $searchTerm, $searchTerm]);
        $matchedDeals = $stmtDeals->fetchAll(PDO::FETCH_ASSOC);

        if (count($matchedContacts) > 1) {
            return [
                'resolved' => false,
                'ambiguous' => true,
                'type' => 'contact',
                'candidates' => $matchedContacts,
                'message' => "Multiple contacts found matching '$query'. Please select which person you mean."
            ];
        }

        if (count($matchedContacts) === 1) {
            return [
                'resolved' => true,
                'ambiguous' => false,
                'type' => 'contact',
                'entity' => $matchedContacts[0]
            ];
        }

        if (count($matchedDeals) === 1) {
            return [
                'resolved' => true,
                'ambiguous' => false,
                'type' => 'deal',
                'entity' => $matchedDeals[0]
            ];
        }

        return [
            'resolved' => false,
            'ambiguous' => false,
            'type' => 'none',
            'candidates' => [],
            'message' => "No matching contact or deal found for '$query'."
        ];
    }
}
