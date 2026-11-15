<?php
// backend/agent/BatchApprovalEngine.php
// Autonomous Agent Engine 2.0: Morning Review Digest & Cryptographic Batch Approval Engine.
// Enforces single-use, TTL-expired, workspace-isolated, content-hash-bound tokens for outbound proposals.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/AgentMigration5.php';

class BatchApprovalEngine {

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    const DEFAULT_TTL_HOURS = 72; // 3 days

    /**
     * Calculates cryptographic content hash bound to recipient, subject, and body.
     */
    public static function computeContentHash(string $recipient, string $subject, string $body): string {
        $normalizedRecipient = strtolower(trim($recipient));
        $normalizedSubject = trim($subject);
        $normalizedBody = trim($body);
        return hash('sha256', $normalizedRecipient . '|' . $normalizedSubject . '|' . $normalizedBody);
    }

    /**
     * Creates a batch of approval records for a morning digest.
     */
    public static function createDigestBatch(
        int $userId,
        string $scheduleRunId,
        string $workflowId,
        array $draftEmails,
        int $ttlHours = self::DEFAULT_TTL_HOURS,
        ?PDO $db = null
    ): array {
        if (!$db) $db = Database::getConnection();
        AgentMigration5::run($db);

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlHours * 3600));

        $batchTokens = [];
        $items = [];

        $stmt = $db->prepare("INSERT INTO ai_agent_batch_approvals (
            id, user_id, schedule_run_id, workflow_id, token, item_key,
            recipient_email, content_hash, status, draft_payload_json,
            expires_at, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)");

        foreach ($draftEmails as $idx => $draft) {
            $itemId = 'appr_item_' . bin2hex(random_bytes(10));
            $token = 'tok_' . bin2hex(random_bytes(24));
            $itemKey = 'lead_' . ($idx + 1);

            $recipient = trim($draft['recipient_email'] ?? ($draft['to'] ?? ''));
            $subject = trim($draft['subject'] ?? 'Business Proposal');
            $body = trim($draft['body'] ?? '');
            $contentHash = self::computeContentHash($recipient, $subject, $body);

            $payloadJson = json_encode([
                'recipient_email' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'company_name' => $draft['company_name'] ?? 'Prospective Client',
                'lead_id' => $draft['lead_id'] ?? null,
                'source_url' => $draft['source_url'] ?? null,
                'sender_identity' => $draft['sender_identity'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $stmt->execute([
                $itemId,
                $userId,
                $scheduleRunId,
                $workflowId,
                $token,
                $itemKey,
                $recipient,
                $contentHash,
                $payloadJson,
                $expiresAt,
                $now
            ]);

            $batchTokens[$itemKey] = $token;
            $items[] = [
                'id' => $itemId,
                'token' => $token,
                'item_key' => $itemKey,
                'recipient_email' => $recipient,
                'company_name' => $draft['company_name'] ?? '',
                'subject' => $subject,
                'status' => self::STATUS_PENDING,
                'expires_at' => $expiresAt
            ];
        }

        return [
            'schedule_run_id' => $scheduleRunId,
            'workflow_id' => $workflowId,
            'total_items' => count($items),
            'expires_at' => $expiresAt,
            'items' => $items,
            'tokens' => $batchTokens
        ];
    }

    /**
     * Previews an individual approval item with security check.
     */
    public static function previewItem(int $userId, string $token, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM ai_agent_batch_approvals WHERE token = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$token, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new InvalidArgumentException("Approval token not found or unauthorized for this workspace.");
        }

        $now = date('Y-m-d H:i:s');
        $isExpired = ($row['expires_at'] < $now);
        if ($isExpired && $row['status'] === self::STATUS_PENDING) {
            $db->prepare("UPDATE ai_agent_batch_approvals SET status = 'expired' WHERE id = ?")->execute([$row['id']]);
            $row['status'] = self::STATUS_EXPIRED;
        }

        $payload = json_decode($row['draft_payload_json'] ?? '{}', true) ?: [];

        return [
            'id' => $row['id'],
            'token' => $row['token'],
            'item_key' => $row['item_key'],
            'status' => $row['status'],
            'recipient_email' => $row['recipient_email'],
            'content_hash' => $row['content_hash'],
            'expires_at' => $row['expires_at'],
            'is_expired' => $isExpired,
            'draft' => $payload
        ];
    }

    /**
     * Edits the content of a draft proposal. Recomputes content hash and issues a new token.
     */
    public static function editItem(
        int $userId,
        string $token,
        string $newSubject,
        string $newBody,
        ?PDO $db = null
    ): array {
        if (!$db) $db = Database::getConnection();

        $preview = self::previewItem($userId, $token, $db);
        if ($preview['status'] !== self::STATUS_PENDING) {
            throw new RuntimeException("Cannot edit item: item status is '{$preview['status']}'.");
        }
        if ($preview['is_expired']) {
            throw new RuntimeException("Cannot edit item: approval token has expired.");
        }

        $draft = $preview['draft'];
        $recipient = $draft['recipient_email'];
        $draft['subject'] = trim($newSubject);
        $draft['body'] = trim($newBody);

        $newHash = self::computeContentHash($recipient, $draft['subject'], $draft['body']);
        $newToken = 'tok_' . bin2hex(random_bytes(24));
        $newPayloadJson = json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $db->prepare("UPDATE ai_agent_batch_approvals
            SET token = ?, content_hash = ?, draft_payload_json = ?
            WHERE id = ? AND user_id = ? AND status = 'pending'");

        $stmt->execute([
            $newToken,
            $newHash,
            $newPayloadJson,
            $preview['id'],
            $userId
        ]);

        return [
            'id' => $preview['id'],
            'old_token' => $token,
            'new_token' => $newToken,
            'content_hash' => $newHash,
            'draft' => $draft
        ];
    }

    /**
     * Approves an individual item with strict single-use, TTL, and content-hash verification.
     */
    public static function approveItem(int $userId, string $token, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM ai_agent_batch_approvals WHERE token = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$token, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new InvalidArgumentException("Approval token not found or unauthorized for this workspace.");
        }

        // Single-use check
        if ($row['status'] === self::STATUS_APPROVED || !empty($row['used_at'])) {
            throw new RuntimeException("Single-use approval token already used at {$row['used_at']}. Re-use rejected.");
        }
        if ($row['status'] === self::STATUS_REJECTED) {
            throw new RuntimeException("Item was previously rejected and cannot be approved.");
        }

        // Expiration check
        $now = date('Y-m-d H:i:s');
        if ($row['expires_at'] < $now) {
            $db->prepare("UPDATE ai_agent_batch_approvals SET status = 'expired' WHERE id = ?")->execute([$row['id']]);
            throw new RuntimeException("Approval token expired at {$row['expires_at']}. Action blocked.");
        }

        // Content integrity check: hash of stored payload must match content_hash
        $payload = json_decode($row['draft_payload_json'] ?? '{}', true) ?: [];
        $expectedHash = self::computeContentHash($payload['recipient_email'] ?? '', $payload['subject'] ?? '', $payload['body'] ?? '');
        if ($expectedHash !== $row['content_hash']) {
            throw new SecurityException("Content integrity mismatch: Email content or recipient was modified after approval was initiated.");
        }

        // Mark as approved (atomic state transition)
        $upd = $db->prepare("UPDATE ai_agent_batch_approvals
            SET status = 'approved', approved_at = ?
            WHERE id = ? AND status = 'pending' AND user_id = ?");

        $upd->execute([$now, $row['id'], $userId]);
        if ($upd->rowCount() === 0) {
            throw new RuntimeException("Failed to approve item: concurrent modification detected.");
        }

        // Audit log
        try {
            $stmtAudit = $db->prepare("INSERT INTO ai_agent_audit_logs (user_id, conversation_id, tool_id, tool_name, risk_level, input_json, approval_status) VALUES (?, ?, 'send_email', 'Send Email Proposal', 'MUTATING', ?, 'approved_by_user')");
            $stmtAudit->execute([$userId, $row['workflow_id'] ?? 'wf_sched', json_encode(['token' => $token, 'recipient' => $row['recipient_email']])]);
        } catch (Throwable $e) {}

        return [
            'status' => 'approved',
            'id' => $row['id'],
            'item_key' => $row['item_key'],
            'recipient_email' => $row['recipient_email'],
            'approved_at' => $now,
            'draft' => $payload
        ];
    }

    /**
     * Approves multiple selected items.
     */
    public static function approveSelected(int $userId, array $tokens, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();
        $approved = [];
        $failed = [];

        foreach ($tokens as $tok) {
            try {
                $res = self::approveItem($userId, $tok, $db);
                $approved[] = $res;
            } catch (Throwable $e) {
                $failed[] = ['token' => $tok, 'error' => $e->getMessage()];
            }
        }

        return [
            'approved_count' => count($approved),
            'failed_count' => count($failed),
            'approved' => $approved,
            'failed' => $failed
        ];
    }

    /**
     * Approves all pending items in a batch.
     */
    public static function approveAll(int $userId, string $scheduleRunId, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();

        $stmt = $db->prepare("SELECT token FROM ai_agent_batch_approvals WHERE schedule_run_id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$scheduleRunId, $userId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return self::approveSelected($userId, $tokens, $db);
    }

    /**
     * Rejects an individual item.
     */
    public static function rejectItem(int $userId, string $token, string $reason = 'Rejected by user', ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("UPDATE ai_agent_batch_approvals
            SET status = 'rejected', rejected_at = ?
            WHERE token = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$now, $token, $userId]);

        return [
            'status' => 'rejected',
            'token' => $token,
            'rejected_at' => $now,
            'reason' => $reason
        ];
    }

    /**
     * Discards an entire batch run.
     */
    public static function discardBatch(int $userId, string $scheduleRunId, string $reason = 'Discarded by user', ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("UPDATE ai_agent_batch_approvals
            SET status = 'rejected', rejected_at = ?
            WHERE schedule_run_id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$now, $scheduleRunId, $userId]);

        return [
            'status' => 'discarded',
            'schedule_run_id' => $scheduleRunId,
            'discarded_items' => $stmt->rowCount(),
            'reason' => $reason
        ];
    }

    /**
     * Lists pending approval items for a workspace, optionally filtered by schedule_run_id.
     */
    public static function listPendingForUser(int $userId, ?string $scheduleRunId = null, int $limit = 100, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();
        AgentMigration5::run($db);
        $limit = max(1, min(200, $limit));

        $sql = "SELECT b.*, s.title as schedule_title
                FROM ai_agent_batch_approvals b
                LEFT JOIN ai_agent_schedule_runs r ON b.schedule_run_id = r.id
                LEFT JOIN ai_agent_schedules s ON r.schedule_id = s.id
                WHERE b.user_id = ? AND b.status = 'pending'";
        $params = [$userId];

        if (!empty($scheduleRunId)) {
            $sql .= " AND b.schedule_run_id = ?";
            $params[] = $scheduleRunId;
        }

        $sql .= " ORDER BY b.created_at DESC LIMIT " . (int)$limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $now = date('Y-m-d H:i:s');
        $items = [];
        foreach ($rows as $r) {
            $isExpired = ($r['expires_at'] < $now);
            if ($isExpired) {
                // Auto-mark expired in background
                $db->prepare("UPDATE ai_agent_batch_approvals SET status = 'expired' WHERE id = ?")->execute([$r['id']]);
                continue;
            }
            $payload = json_decode($r['draft_payload_json'] ?? '{}', true) ?: [];
            $items[] = [
                'id' => $r['id'],
                'token' => $r['token'],
                'item_key' => $r['item_key'],
                'schedule_run_id' => $r['schedule_run_id'],
                'schedule_title' => $r['schedule_title'] ?? 'Autonomous Prospecting',
                'recipient_email' => $r['recipient_email'],
                'company_name' => $payload['company_name'] ?? '',
                'subject' => $payload['subject'] ?? '',
                'body' => $payload['body'] ?? '',
                'status' => $r['status'],
                'expires_at' => $r['expires_at'],
                'created_at' => $r['created_at'],
                'draft' => $payload
            ];
        }

        return $items;
    }
}
