<?php
// backend/agent/OutreachDispatcher.php
// Autonomous Agent Engine 2.0: Controlled Email Dispatch, Rate Limiting, Suppression Guards,
// Maker-Checker Token Validation & Audit Logging Controller.
// Strictly adheres to approval gates, provider quotas, and anti-spam deliverability standards.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/BatchApprovalEngine.php';
require_once __DIR__ . '/DeliverabilityEngine.php';
require_once __DIR__ . '/AgentMigration5.php';

class OutreachDispatcher {

    const PROVIDER_GMAIL = 'gmail';
    const PROVIDER_SMTP  = 'smtp';
    const PROVIDER_MOCK  = 'mock';

    // Provider daily dispatch quotas
    const QUOTA_GMAIL = 500;
    const QUOTA_SMTP  = 200;
    const QUOTA_MOCK  = 1000;

    /**
     * Optional custom transport sender for test suites and staging harnesses.
     * @var callable|null
     */
    private static $customTransport = null;

    /**
     * Sets a custom transport callback for automated testing.
     * Callable signature: function(int $userId, string $to, string $subject, string $htmlBody, string $from): array
     */
    public static function setCustomTransport(?callable $transport): void {
        self::$customTransport = $transport;
    }

    /**
     * Returns remaining daily dispatch quota for a workspace provider.
     */
    public static function getRemainingDailyQuota(int $userId, string $provider = self::PROVIDER_SMTP, ?PDO $db = null): int {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return 0; }
        }
        AgentMigration5::run($db);

        $limit = ($provider === self::PROVIDER_GMAIL) ? self::QUOTA_GMAIL : self::QUOTA_SMTP;
        if ($provider === self::PROVIDER_MOCK) $limit = self::QUOTA_MOCK;

        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_agent_outreach_logs WHERE user_id = ? AND provider = ? AND status = 'sent' AND substr(sent_at, 1, 10) = ?");
        $stmt->execute([$userId, $provider, $today]);
        $sentToday = (int)$stmt->fetchColumn();

        return max(0, $limit - $sentToday);
    }

    /**
     * Checks if a recipient is on the suppression list.
     */
    public static function isSuppressed(int $userId, string $recipientEmail, ?PDO $db = null): bool {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return false; }
        }
        $email = strtolower(trim($recipientEmail));

        $stmt = $db->prepare("SELECT id FROM ai_agent_suppression_list WHERE user_id = ? AND email = ? LIMIT 1");
        $stmt->execute([$userId, $email]);
        if ($stmt->fetchColumn()) return true;

        // Also check legacy campaign unsubscribed logs
        try {
            $stmtUnsub = $db->prepare("SELECT id FROM email_campaign_logs WHERE recipient_email = ? AND is_unsubscribed = 1 LIMIT 1");
            $stmtUnsub->execute([$email]);
            if ($stmtUnsub->fetchColumn()) return true;
        } catch (Throwable $e) {}

        return false;
    }

    /**
     * Records a bounced or complained email into the suppression list.
     */
    public static function recordBounceOrComplaint(int $userId, string $email, string $reason = 'bounced', ?PDO $db = null): void {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) { return; }
        }
        AgentMigration5::run($db);

        $cleanEmail = strtolower(trim($email));
        $domain = DeliverabilityEngine::normalizeDomain(explode('@', $cleanEmail)[1] ?? '');
        $now = date('Y-m-d H:i:s');
        $id = 'supp_' . bin2hex(random_bytes(8));

        $stmt = $db->prepare("INSERT OR IGNORE INTO ai_agent_suppression_list (id, user_id, email, domain, reason, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'sqlite') {
            $stmt = $db->prepare("INSERT INTO ai_agent_suppression_list (id, user_id, email, domain, reason, created_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)");
        }
        $stmt->execute([$id, $userId, $cleanEmail, $domain, $reason, $now]);
    }

    /**
     * Dispatches an individual proposal that has been verified through a cryptographic approval token.
     * Enforces token validity, exact content hash, non-suppression, and provider quota limits.
     *
     * @param int $userId Workspace owner ID
     * @param string $approvalToken Cryptographic approval token (tok_...)
     * @param string $provider Transport to utilize ('gmail', 'smtp', or 'mock')
     * @param array $options Additional execution options
     * @param PDO|null $db Database handle
     * @return array Result dictionary
     */
    public static function dispatchApprovedItem(
        int $userId,
        string $approvalToken,
        string $provider = self::PROVIDER_MOCK,
        array $options = [],
        ?PDO $db = null
    ): array {
        if (!$db) {
            try { $db = Database::getConnection(); } catch (Throwable $e) {
                throw new Exception("Database required for outreach dispatch: " . $e->getMessage());
            }
        }
        AgentMigration5::run($db);

        // 1. Fetch item from batch approvals table
        $stmt = $db->prepare("SELECT * FROM ai_agent_batch_approvals WHERE token = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$approvalToken, $userId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("Invalid or non-existent approval token '{$approvalToken}' for workspace #{$userId}.");
        }

        // 2. Enforce Single-Use
        if (!empty($item['used_at'])) {
            throw new Exception("Approval token '{$approvalToken}' has already been used and cannot be re-dispatched.");
        }

        // 3. Enforce Approval Status
        if ($item['status'] !== BatchApprovalEngine::STATUS_APPROVED) {
            throw new Exception("Item '{$approvalToken}' cannot be dispatched because its status is '{$item['status']}' (must be approved first).");
        }

        // 4. Enforce Expiration
        if (strtotime($item['expires_at']) <= time()) {
            throw new Exception("Approval token '{$approvalToken}' has expired at {$item['expires_at']}.");
        }

        $draft = json_decode($item['draft_payload_json'], true);
        if (!$draft) {
            throw new Exception("Corrupt draft payload for approval item '{$item['id']}'.");
        }

        $recipientEmail = strtolower(trim($draft['recipient_email'] ?? $item['recipient_email']));
        $subject = trim($draft['subject'] ?? '');
        $body = trim($draft['body_html'] ?? $draft['body_text'] ?? $draft['body'] ?? '');
        $senderEmail = trim($draft['sender_email'] ?? '');

        // 5. Enforce Content Hash Match
        $expectedHash = hash('sha256', "{$recipientEmail}|{$subject}|{$body}");
        if ($expectedHash !== $item['content_hash']) {
            throw new Exception("Security violation: Approval content hash mismatch. Draft was altered after token issuance.");
        }

        // 6. Enforce Suppression Check
        if (self::isSuppressed($userId, $recipientEmail, $db)) {
            // Mark token as suppressed
            $logId = 'log_' . bin2hex(random_bytes(10));
            $now = date('Y-m-d H:i:s');
            $stmtLog = $db->prepare("INSERT INTO ai_agent_outreach_logs (id, user_id, schedule_run_id, batch_token, recipient_email, subject, provider, status, error_message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'suppressed', 'Recipient is on suppression/unsubscribe list', ?)");
            $stmtLog->execute([$logId, $userId, $item['schedule_run_id'], $approvalToken, $recipientEmail, $subject, $provider, $now]);

            return [
                'status' => 'suppressed',
                'recipient' => $recipientEmail,
                'message' => 'Recipient is suppressed; dispatch prevented.',
                'log_id' => $logId
            ];
        }

        // 7. Enforce Daily Quota Limit
        $remainingQuota = self::getRemainingDailyQuota($userId, $provider, $db);
        if ($remainingQuota <= 0) {
            throw new Exception("Daily dispatch quota exceeded for provider '{$provider}' (Remaining: 0). Dispatch paused.");
        }

        // 8. Controlled Dispatch via Selected Provider Transport
        $messageId = null;
        $sendError = null;
        $sendStatus = 'sent';

        try {
            if (is_callable(self::$customTransport)) {
                $customRes = call_user_func(self::$customTransport, $userId, $recipientEmail, $subject, $body, $senderEmail);
                $messageId = $customRes['message_id'] ?? ('msg_mock_' . bin2hex(random_bytes(8)));
            } elseif ($provider === self::PROVIDER_GMAIL && class_exists('ExternalAppsHelper')) {
                // Gmail OAuth dispatch
                $res = ExternalAppsHelper::sendGmailMessage($userId, $recipientEmail, $subject, $body);
                $messageId = $res['id'] ?? ('msg_gmail_' . bin2hex(random_bytes(8)));
            } elseif ($provider === self::PROVIDER_SMTP && class_exists('SMTPHelper')) {
                // SMTP dispatch
                $res = SMTPHelper::sendEmail($userId, $recipientEmail, $subject, $body);
                $messageId = is_string($res) ? $res : ('msg_smtp_' . bin2hex(random_bytes(8)));
            } else {
                // Deterministic mock transport
                $messageId = 'msg_sim_' . bin2hex(random_bytes(8));
            }
        } catch (Throwable $e) {
            $sendStatus = 'failed';
            $sendError = $e->getMessage();
        }

        $now = date('Y-m-d H:i:s');
        $logId = 'log_' . bin2hex(random_bytes(10));

        // 9. Record Outbound Message Audit Log
        $flags = [
            'compliance_footer' => true,
            'unsubscribe_url' => $draft['unsubscribe_url'] ?? null,
            'sender_identity' => $senderEmail,
            'content_hash' => $expectedHash
        ];

        $stmtLog = $db->prepare("
            INSERT INTO ai_agent_outreach_logs (
                id, user_id, schedule_run_id, batch_token, recipient_email, subject,
                provider, status, message_id, error_message, compliance_flags_json, sent_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtLog->execute([
            $logId,
            $userId,
            $item['schedule_run_id'],
            $approvalToken,
            $recipientEmail,
            $subject,
            $provider,
            $sendStatus,
            $messageId,
            $sendError,
            json_encode($flags),
            ($sendStatus === 'sent' ? $now : null),
            $now
        ]);

        if ($sendStatus === 'sent') {
            // Mark approval token as used
            $stmtUse = $db->prepare("UPDATE ai_agent_batch_approvals SET used_at = ? WHERE id = ?");
            $stmtUse->execute([$now, $item['id']]);

            return [
                'status' => 'sent',
                'log_id' => $logId,
                'message_id' => $messageId,
                'recipient' => $recipientEmail,
                'provider' => $provider,
                'sent_at' => $now
            ];
        } else {
            return [
                'status' => 'failed',
                'log_id' => $logId,
                'error' => $sendError,
                'recipient' => $recipientEmail,
                'provider' => $provider
            ];
        }
    }
}
