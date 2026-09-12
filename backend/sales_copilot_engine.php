<?php
// backend/sales_copilot_engine.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crm_sync_helper.php';
require_once __DIR__ . '/lead_scoring_engine.php';

class SalesCopilotEngine {

    public static function getRiskDeals(int $userId, PDO $db): array {
        $stmt = $db->prepare("
            SELECT d.*, c.name as contact_name, c.company as contact_company
            FROM crm_deals d
            LEFT JOIN crm_contacts c ON (c.id = d.contact_id AND c.user_id = d.user_id)
            WHERE d.user_id = ? AND d.stage NOT IN ('Closed Won', 'Closed Lost')
            ORDER BY d.value DESC LIMIT 20
        ");
        $stmt->execute([$userId]);
        $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($deals as $deal) {
            $updatedAt = strtotime($deal['updated_at'] ?? $deal['created_at']);
            $daysInactive = (int)floor((time() - $updatedAt) / 86400);

            $reasons = [];
            $status = 'Healthy';

            if ($daysInactive > 10) {
                $status = 'At Risk';
                $reasons[] = "No CRM activity or status update recorded for {$daysInactive} days.";
            }
            if ($deal['stage'] === 'Proposal Sent' && $daysInactive > 7) {
                $status = 'At Risk';
                $reasons[] = "Proposal sent {$daysInactive} days ago with no response.";
            }

            if ($status === 'At Risk' || $daysInactive > 5) {
                $results[] = [
                    'id' => (int)$deal['id'],
                    'title' => $deal['title'],
                    'value' => (float)$deal['value'],
                    'stage' => $deal['stage'],
                    'contact_name' => $deal['contact_name'] ?? 'N/A',
                    'contact_company' => $deal['contact_company'] ?? 'N/A',
                    'days_inactive' => $daysInactive,
                    'status' => $status,
                    'reasons' => !empty($reasons) ? $reasons : ["Standard deal age check: {$daysInactive} days in stage."]
                ];
            }
        }

        return [
            'count' => count($results),
            'risk_deals' => $results
        ];
    }

    public static function getDailyBriefing(int $userId, PDO $db): array {
        $hotLeads = LeadScoringEngine::getHotLeads($userId, $db);
        $riskDeals = self::getRiskDeals($userId, $db);

        // Fetch overdue tasks
        $stmtTask = $db->prepare("
            SELECT COUNT(*) FROM crm_tasks
            WHERE user_id = ? AND status != 'completed' AND due_date < CURDATE()
        ");
        $stmtTask->execute([$userId]);
        $overdueTasksCount = (int)$stmtTask->fetchColumn();

        // Fetch today's meetings
        $stmtMtg = $db->prepare("
            SELECT COUNT(*) FROM crm_meetings
            WHERE user_id = ? AND DATE(start_time) = CURDATE()
        ");
        $stmtMtg->execute([$userId]);
        $todaysMeetingsCount = (int)$stmtMtg->fetchColumn();

        $priorities = [];
        if (!empty($hotLeads['hot_leads'][0])) {
            $topLead = $hotLeads['hot_leads'][0];
            $priorities[] = "🔥 Follow up with top lead {$topLead['name']} (Score: {$topLead['score']}/100, Intent: {$topLead['intent_level']}).";
        }
        if (!empty($riskDeals['risk_deals'][0])) {
            $topRisk = $riskDeals['risk_deals'][0];
            $priorities[] = "⚠️ Deal '{$topRisk['title']}' (\${$topRisk['value']}) is at risk — inactive for {$topRisk['days_inactive']} days.";
        }
        if ($overdueTasksCount > 0) {
            $priorities[] = "📋 You have {$overdueTasksCount} overdue task(s) requiring immediate attention.";
        }
        if ($todaysMeetingsCount > 0) {
            $priorities[] = "📅 You have {$todaysMeetingsCount} meeting(s) scheduled for today.";
        }

        return [
            'greeting' => "Good morning! Here is your LinkPilot AI Daily Briefing.",
            'todays_meetings' => $todaysMeetingsCount,
            'overdue_tasks' => $overdueTasksCount,
            'hot_leads_count' => $hotLeads['count'] ?? 0,
            'risk_deals_count' => $riskDeals['count'] ?? 0,
            'priorities' => $priorities
        ];
    }
}
