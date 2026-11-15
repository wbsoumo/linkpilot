<?php
// backend/agent/WorkflowScheduler.php
// Autonomous Agent Engine 2.0: Dynamic Workflow Scheduler & Background Execution Manager.
// Provides persistent schedule management, timezone & DST aware cron math, atomic claiming,
// stale-lock recovery, missed-run policies, and multi-tenant isolation.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/AgentMigration5.php';

class WorkflowScheduler {

    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    const POLICY_DIGEST_REVIEW = 'digest_review';
    const POLICY_AUTO_DISPATCH = 'auto_dispatch';

    const MISSED_SKIP = 'skip';
    const MISSED_RUN_ONCE = 'run_once';

    const DEFAULT_LEASE_SECONDS = 300; // 5 minutes lock duration

    /**
     * Validates a 5-part cron expression.
     */
    public static function validateCron(string $expression): bool {
        $expr = trim($expression);
        $shortcuts = [
            '@hourly' => '0 * * * *',
            '@daily' => '0 0 * * *',
            '@midnight' => '0 0 * * *',
            '@weekly' => '0 0 * * 0',
            '@monthly' => '0 0 1 * *',
        ];
        if (isset($shortcuts[strtolower($expr)])) {
            return true;
        }

        $parts = preg_split('/\s+/', $expr);
        if (count($parts) !== 5) {
            return false;
        }

        list($min, $hour, $dom, $month, $dow) = $parts;

        $patterns = [
            'min' => '/^(\*|\*\/[1-5]?[0-9]|[0-5]?[0-9](-[0-5]?[0-9])?(,[0-5]?[0-9](-[0-5]?[0-9])?)*)$/',
            'hour' => '/^(\*|\*\/[1-2]?[0-9]|[0-1]?[0-9]|2[0-3]|([0-1]?[0-9]|2[0-3])-([0-1]?[0-9]|2[0-3]))$/',
            'dom' => '/^(\*|\*\/[1-3]?[0-9]|[1-9]|[1-2][0-9]|3[0-1])$/',
            'month' => '/^(\*|\*\/[1-9]|1[0-2]|[1-9]|1[0-2])$/',
            'dow' => '/^(\*|\*\/[0-6]|[0-6](-[0-6])?(,[0-6](-[0-6])?)*)$/'
        ];

        return (bool)(
            preg_match($patterns['min'], $min) &&
            preg_match($patterns['hour'], $hour) &&
            preg_match($patterns['dom'], $dom) &&
            preg_match($patterns['month'], $month) &&
            preg_match($patterns['dow'], $dow)
        );
    }

    /**
     * Normalizes shortcuts to standard 5-part cron string.
     */
    public static function normalizeCron(string $expression): string {
        $shortcuts = [
            '@hourly' => '0 * * * *',
            '@daily' => '0 0 * * *',
            '@midnight' => '0 0 * * *',
            '@weekly' => '0 0 * * 0',
            '@monthly' => '0 0 1 * *',
        ];
        $lower = strtolower(trim($expression));
        return $shortcuts[$lower] ?? trim($expression);
    }

    /**
     * Calculates the next execution timestamp aware of user timezone and daylight-saving transitions.
     */
    public static function calculateNextRun(
        string $cronExpression,
        string $timezone = 'UTC',
        ?DateTimeInterface $from = null
    ): DateTimeImmutable {
        $expr = self::normalizeCron($cronExpression);
        if (!self::validateCron($expr)) {
            throw new InvalidArgumentException("Invalid cron expression: '{$cronExpression}'");
        }

        try {
            $tzObj = new DateTimeZone($timezone);
        } catch (Throwable $e) {
            $tzObj = new DateTimeZone('UTC');
        }

        if ($from) {
            $base = DateTimeImmutable::createFromFormat('U', (string)$from->getTimestamp())->setTimezone($tzObj);
        } else {
            $base = new DateTimeImmutable('now', $tzObj);
        }

        // Advance at least 1 minute forward with seconds zeroed
        $current = $base->setTime((int)$base->format('G'), (int)$base->format('i'), 0)->modify('+1 minute');

        list($cronMin, $cronHour, $cronDom, $cronMonth, $cronDow) = preg_split('/\s+/', $expr);

        // Scan ahead up to 366 days (525,600 minutes)
        for ($i = 0; $i < 525600; $i++) {
            $min = (int)$current->format('i');
            $hour = (int)$current->format('G');
            $dom = (int)$current->format('j');
            $month = (int)$current->format('n');
            $dow = (int)$current->format('w'); // 0 (Sun) - 6 (Sat)

            if (!self::matchesField($cronMonth, $month, 1, 12)) {
                $current = $current->setTime(0, 0, 0)->setDate((int)$current->format('Y'), (int)$current->format('n'), 1)->modify('+1 month');
                continue;
            }
            if (!self::matchesField($cronDom, $dom, 1, 31) || !self::matchesField($cronDow, $dow, 0, 6)) {
                $current = $current->setTime(0, 0, 0)->modify('+1 day');
                continue;
            }
            if (!self::matchesField($cronHour, $hour, 0, 23)) {
                $current = $current->setTime($hour, 0, 0)->modify('+1 hour');
                continue;
            }
            if (!self::matchesField($cronMin, $min, 0, 59)) {
                $current = $current->modify('+1 minute');
                continue;
            }

            // All 5 fields matched
            return $current;
        }

        throw new RuntimeException("Could not calculate next run for cron '{$cronExpression}' within 1 year.");
    }

    private static function matchesField(string $pattern, int $value, int $min, int $max): bool {
        if ($pattern === '*') return true;

        if (strpos($pattern, '*/') === 0) {
            $step = (int)substr($pattern, 2);
            return ($step > 0) && ($value % $step === 0);
        }

        if (strpos($pattern, ',') !== false) {
            $items = explode(',', $pattern);
            foreach ($items as $item) {
                if (self::matchesField($item, $value, $min, $max)) return true;
            }
            return false;
        }

        if (strpos($pattern, '-') !== false) {
            list($start, $end) = explode('-', $pattern, 2);
            return ($value >= (int)$start && $value <= (int)$end);
        }

        return ((int)$pattern === $value);
    }

    /**
     * Creates a new persistent recurring schedule.
     */
    public static function createSchedule(
        int $userId,
        string $title,
        string $cronExpression,
        string $timezone,
        array $parameters,
        ?array $dagTemplate = null,
        string $approvalPolicy = self::POLICY_DIGEST_REVIEW,
        string $missedRunPolicy = self::MISSED_SKIP,
        ?PDO $db = null
    ): array {
        if (!$db) $db = Database::getConnection();
        AgentMigration5::run($db);

        $cronExpr = self::normalizeCron($cronExpression);
        if (!self::validateCron($cronExpr)) {
            throw new InvalidArgumentException("Invalid cron expression '{$cronExpression}'");
        }

        // Validate timezone
        try {
            new DateTimeZone($timezone);
            $validTz = $timezone;
        } catch (Throwable $e) {
            $validTz = 'UTC';
        }

        $nextRun = self::calculateNextRun($cronExpr, $validTz);
        $nextRunFormatted = $nextRun->format('Y-m-d H:i:s');

        $scheduleId = 'sched_' . bin2hex(random_bytes(10));
        $paramsJson = json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $dagJson = $dagTemplate ? json_encode($dagTemplate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $stmt = $db->prepare("INSERT INTO ai_agent_schedules (
            id, user_id, title, cron_expression, timezone, task_type,
            parameters_json, dag_template_json, approval_policy, status,
            missed_run_policy, next_run_at, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, 'prospecting_and_outreach', ?, ?, ?, 'active', ?, ?, ?, ?)");

        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            $scheduleId,
            $userId,
            $title,
            $cronExpr,
            $validTz,
            $paramsJson,
            $dagJson,
            $approvalPolicy,
            $missedRunPolicy,
            $nextRunFormatted,
            $now,
            $now
        ]);

        return self::getSchedule($scheduleId, $userId, $db);
    }

    /**
     * Retrieves a single schedule scoped to user/workspace.
     */
    public static function getSchedule(string $scheduleId, int $userId, ?PDO $db = null): ?array {
        if (!$db) $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM ai_agent_schedules WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$scheduleId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['parameters'] = json_decode($row['parameters_json'] ?? '{}', true) ?: [];
        $row['dag_template'] = json_decode($row['dag_template_json'] ?? 'null', true);
        return $row;
    }

    /**
     * Lists schedules for a user workspace with optional status filter.
     */
    public static function listSchedules(int $userId, ?string $statusFilter = null, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();
        AgentMigration5::run($db);

        $sql = "SELECT id, user_id, title, cron_expression, timezone, task_type, approval_policy, status,
                       missed_run_policy, next_run_at, last_run_at, created_at, updated_at, parameters_json
                FROM ai_agent_schedules WHERE user_id = ?";
        $binds = [$userId];

        if (!empty($statusFilter)) {
            $sql .= " AND status = ?";
            $binds[] = $statusFilter;
        }

        $sql .= " ORDER BY next_run_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($binds);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['parameters'] = json_decode($r['parameters_json'] ?? '{}', true) ?: [];
            unset($r['parameters_json']);
        }
        return $rows;
    }

    /**
     * Pauses an active schedule.
     */
    public static function pauseSchedule(string $scheduleId, int $userId, ?PDO $db = null): bool {
        if (!$db) $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE ai_agent_schedules SET status = 'paused', updated_at = ? WHERE id = ? AND user_id = ? AND status = 'active'");
        $stmt->execute([date('Y-m-d H:i:s'), $scheduleId, $userId]);
        return ($stmt->rowCount() > 0);
    }

    /**
     * Resumes a paused schedule and recalculates the next execution slot.
     */
    public static function resumeSchedule(string $scheduleId, int $userId, ?PDO $db = null): bool {
        if (!$db) $db = Database::getConnection();
        $sched = self::getSchedule($scheduleId, $userId, $db);
        if (!$sched || $sched['status'] !== self::STATUS_PAUSED) {
            return false;
        }

        $nextRun = self::calculateNextRun($sched['cron_expression'], $sched['timezone']);
        $nextRunFormatted = $nextRun->format('Y-m-d H:i:s');

        $stmt = $db->prepare("UPDATE ai_agent_schedules SET status = 'active', next_run_at = ?, updated_at = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$nextRunFormatted, date('Y-m-d H:i:s'), $scheduleId, $userId]);
        return ($stmt->rowCount() > 0);
    }

    /**
     * Cancels a schedule permanently.
     */
    public static function cancelSchedule(string $scheduleId, int $userId, ?PDO $db = null): bool {
        if (!$db) $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE ai_agent_schedules SET status = 'cancelled', updated_at = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([date('Y-m-d H:i:s'), $scheduleId, $userId]);
        return ($stmt->rowCount() > 0);
    }

    /**
     * Deletes a schedule permanently.
     */
    public static function deleteSchedule(string $scheduleId, int $userId, ?PDO $db = null): bool {
        if (!$db) $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM ai_agent_schedules WHERE id = ? AND user_id = ?");
        $stmt->execute([$scheduleId, $userId]);
        return ($stmt->rowCount() > 0);
    }

    /**
     * Atomically claims due schedules for background worker execution.
     * Prevents overlapping executions via lease tokens and recovers stale locks.
     */
    public static function claimDueSchedules(int $limit = 10, int $leaseSeconds = self::DEFAULT_LEASE_SECONDS, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();
        AgentMigration5::run($db);

        $nowStr = date('Y-m-d H:i:s');
        $leaseExpStr = date('Y-m-d H:i:s', time() + $leaseSeconds);

        // Fetch candidate active schedules that are due AND (not leased OR lease has expired)
        $sql = "SELECT id, user_id, title, cron_expression, timezone, parameters_json, dag_template_json,
                       approval_policy, missed_run_policy, next_run_at, lease_token, lease_expires_at
                FROM ai_agent_schedules
                WHERE status = 'active'
                  AND next_run_at <= ?
                  AND (lease_expires_at IS NULL OR lease_expires_at < ?)
                ORDER BY next_run_at ASC
                LIMIT " . (int)$limit;

        $stmt = $db->prepare($sql);
        $stmt->execute([$nowStr, $nowStr]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $claimed = [];

        foreach ($candidates as $c) {
            $leaseToken = 'lease_' . bin2hex(random_bytes(16));
            $execId = 'run_' . bin2hex(random_bytes(10));

            // Atomic conditional update
            $claimStmt = $db->prepare("UPDATE ai_agent_schedules
                SET lease_token = ?, lease_expires_at = ?, last_execution_id = ?, updated_at = ?
                WHERE id = ?
                  AND status = 'active'
                  AND (lease_expires_at IS NULL OR lease_expires_at < ?)");

            $claimStmt->execute([
                $leaseToken,
                $leaseExpStr,
                $execId,
                $nowStr,
                $c['id'],
                $nowStr
            ]);

            if ($claimStmt->rowCount() > 0) {
                $c['lease_token'] = $leaseToken;
                $c['execution_id'] = $execId;
                $c['parameters'] = json_decode($c['parameters_json'] ?? '{}', true) ?: [];
                $c['dag_template'] = json_decode($c['dag_template_json'] ?? 'null', true);
                $claimed[] = $c;
            }
        }

        return $claimed;
    }

    /**
     * Completes a schedule run: calculates the next future run, handles missed-run policies, and releases lease.
     */
    public static function completeScheduleRun(
        string $scheduleId,
        string $leaseToken,
        ?string $errorMessage = null,
        ?PDO $db = null
    ): bool {
        if (!$db) $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM ai_agent_schedules WHERE id = ? AND lease_token = ? LIMIT 1");
        $stmt->execute([$scheduleId, $leaseToken]);
        $sched = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sched) {
            return false;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone($sched['timezone']));
        $missedPolicy = $sched['missed_run_policy'] ?? self::MISSED_SKIP;

        // Calculate next run slot
        $nextRun = self::calculateNextRun($sched['cron_expression'], $sched['timezone'], $now);
        $nextRunFormatted = $nextRun->format('Y-m-d H:i:s');
        $nowFormatted = date('Y-m-d H:i:s');

        $upd = $db->prepare("UPDATE ai_agent_schedules
            SET lease_token = NULL,
                lease_expires_at = NULL,
                last_run_at = ?,
                next_run_at = ?,
                updated_at = ?
            WHERE id = ? AND lease_token = ?");

        $upd->execute([
            $nowFormatted,
            $nextRunFormatted,
            $nowFormatted,
            $scheduleId,
            $leaseToken
        ]);

        return ($upd->rowCount() > 0);
    }
}
