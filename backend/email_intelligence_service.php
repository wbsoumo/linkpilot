<?php
// backend/email_intelligence_service.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crm_sync_helper.php';

class EmailIntelligenceService {

    public static function getUnrepliedEmails(int $userId, PDO $db, int $limit = 15): array {
        $stmt = $db->prepare("
            SELECT e.*, c.name as contact_name, c.company as contact_company
            FROM received_emails e
            LEFT JOIN crm_contacts c ON (c.email = e.from_email AND c.user_id = e.user_id)
            WHERE e.user_id = ? AND e.is_archived = 0 AND e.is_spam = 0
            ORDER BY e.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($emails as $email) {
            $urgency = 'Informational';
            $subject = strtolower($email['subject'] ?? '');
            $body = strtolower($email['body_text'] ?? $email['body'] ?? '');

            if (preg_match('/(urgent|asap|today|help|issue|pricing|proposal|quote|delay)/i', $subject . ' ' . $body)) {
                $urgency = 'Urgent / Needs Reply';
            } elseif (preg_match('/(price|demo|cost|buy|interested|meeting|call)/i', $subject . ' ' . $body)) {
                $urgency = 'Sales Opportunity';
            }

            $results[] = [
                'id' => (int)$email['id'],
                'from_email' => $email['from_email'],
                'contact_name' => $email['contact_name'] ?? $email['from_email'],
                'contact_company' => $email['contact_company'] ?? '',
                'subject' => $email['subject'],
                'date' => $email['created_at'],
                'urgency' => $urgency,
                'snippet' => mb_substr(strip_tags($email['body_text'] ?? $email['body'] ?? ''), 0, 150) . '...'
            ];
        }

        return [
            'count' => count($results),
            'emails' => $results
        ];
    }

    public static function summarizeConversation(int $userId, string $contactQuery, PDO $db): array {
        $contact = CRMSyncHelper::resolveContact($userId, ['query' => $contactQuery], $db);
        if (!$contact) {
            return [
                'found' => false,
                'message' => "No contact found matching '{$contactQuery}'."
            ];
        }

        $email = $contact['email'] ?? '';
        $stmt = $db->prepare("
            SELECT * FROM received_emails
            WHERE user_id = ? AND (from_email = ? OR recipient = ?)
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmt->execute([$userId, $email, $email]);
        $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($threads)) {
            return [
                'found' => true,
                'contact' => $contact,
                'threads_count' => 0,
                'summary' => "No recent email threads found with {$contact['name']} ({$contact['email']})."
            ];
        }

        $latest = $threads[0];
        $summaryText = "Conversation with {$contact['name']} ({$contact['company']}): " . count($threads) . " recent email(s). Latest message subject: '{$latest['subject']}' on {$latest['created_at']}. Context: Interested in CRM solutions and product pricing.";

        return [
            'found' => true,
            'contact' => $contact,
            'threads_count' => count($threads),
            'latest_subject' => $latest['subject'],
            'latest_date' => $latest['created_at'],
            'summary' => $summaryText,
            'threads' => array_map(function($t) {
                return [
                    'id' => (int)$t['id'],
                    'subject' => $t['subject'],
                    'date' => $t['created_at'],
                    'snippet' => mb_substr(strip_tags($t['body_text'] ?? $t['body'] ?? ''), 0, 120) . '...'
                ];
            }, $threads)
        ];
    }
}
