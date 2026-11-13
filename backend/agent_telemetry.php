<?php
// backend/agent_telemetry.php
// Performance monitoring, logging, and observability for LinkPilot AI Agent.

class AgentTelemetry {

    /**
     * Initializes the telemetry database table.
     */
    public static function initTable(PDO $db) {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $db->exec("CREATE TABLE IF NOT EXISTS ai_agent_telemetry_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                conversation_id TEXT DEFAULT NULL,
                request_text TEXT NOT NULL,
                classification TEXT NOT NULL,
                execution_path TEXT NOT NULL,
                provider TEXT DEFAULT 'none',
                model TEXT DEFAULT 'none',
                tools_loaded TEXT DEFAULT NULL,
                tools_executed TEXT DEFAULT NULL,
                ai_latency_ms INTEGER DEFAULT 0,
                db_latency_ms INTEGER DEFAULT 0,
                total_time_ms INTEGER DEFAULT 0,
                tokens_used INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );");
            try { $db->exec("CREATE INDEX IF NOT EXISTS idx_telemetry_user ON ai_agent_telemetry_logs (user_id, created_at);"); } catch (Throwable $e) {}
            try { $db->exec("CREATE INDEX IF NOT EXISTS idx_telemetry_path ON ai_agent_telemetry_logs (execution_path);"); } catch (Throwable $e) {}
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS `ai_agent_telemetry_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `conversation_id` VARCHAR(64) DEFAULT NULL,
                `request_text` TEXT NOT NULL,
                `classification` VARCHAR(50) NOT NULL,
                `execution_path` VARCHAR(50) NOT NULL,
                `provider` VARCHAR(50) DEFAULT 'none',
                `model` VARCHAR(100) DEFAULT 'none',
                `tools_loaded` TEXT DEFAULT NULL,
                `tools_executed` TEXT DEFAULT NULL,
                `ai_latency_ms` INT DEFAULT 0,
                `db_latency_ms` INT DEFAULT 0,
                `total_time_ms` INT DEFAULT 0,
                `tokens_used` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_telemetry_user` (`user_id`, `created_at`),
                INDEX `idx_telemetry_path` (`execution_path`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
    }

    /**
     * Records a telemetry log entry.
     */
    public static function logRequest(
        int $userId,
        ?string $convId,
        string $requestText,
        string $classification,
        string $executionPath,
        string $provider = 'none',
        string $model = 'none',
        array $toolsLoaded = [],
        array $toolsExecuted = [],
        int $aiLatencyMs = 0,
        int $dbLatencyMs = 0,
        int $totalTimeMs = 0,
        int $tokensUsed = 0,
        ?PDO $db = null
    ) {
        if (!$db) return;

        try {
            self::initTable($db);
            $stmt = $db->prepare("INSERT INTO ai_agent_telemetry_logs (
                user_id, conversation_id, request_text, classification, execution_path,
                provider, model, tools_loaded, tools_executed,
                ai_latency_ms, db_latency_ms, total_time_ms, tokens_used
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $userId,
                $convId,
                mb_substr($requestText, 0, 500),
                $classification,
                $executionPath,
                $provider,
                $model,
                json_encode($toolsLoaded),
                json_encode($toolsExecuted),
                $aiLatencyMs,
                $dbLatencyMs,
                $totalTimeMs,
                $tokensUsed
            ]);
        } catch (Throwable $e) {
            error_log("Telemetry logging error: " . $e->getMessage());
        }
    }

    /**
     * Retrieves recent telemetry records.
     */
    public static function getRecentLogs(int $userId, int $limit = 25, ?PDO $db = null): array {
        if (!$db) return [];
        try {
            self::initTable($db);
            $stmt = $db->prepare("SELECT * FROM ai_agent_telemetry_logs WHERE user_id = ? ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Returns aggregate performance metrics.
     */
    public static function getSummaryMetrics(int $userId, ?PDO $db = null): array {
        if (!$db) return [];
        try {
            self::initTable($db);
            $stmt = $db->prepare("SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN execution_path = 'instant' THEN 1 ELSE 0 END) as instant_requests,
                SUM(CASE WHEN execution_path = 'simple_ai' THEN 1 ELSE 0 END) as simple_ai_requests,
                SUM(CASE WHEN execution_path = 'business_ops' THEN 1 ELSE 0 END) as business_ops_requests,
                SUM(CASE WHEN execution_path = 'complex_autonomous' THEN 1 ELSE 0 END) as complex_requests,
                AVG(total_time_ms) as avg_total_time_ms,
                AVG(CASE WHEN execution_path = 'instant' THEN total_time_ms ELSE NULL END) as avg_instant_time_ms,
                AVG(CASE WHEN execution_path != 'instant' THEN ai_latency_ms ELSE NULL END) as avg_ai_latency_ms,
                SUM(tokens_used) as total_tokens_used
            FROM ai_agent_telemetry_logs WHERE user_id = ?");
            $stmt->execute([$userId]);
            $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_requests' => (int)($metrics['total_requests'] ?? 0),
                'breakdown' => [
                    'instant' => (int)($metrics['instant_requests'] ?? 0),
                    'simple_ai' => (int)($metrics['simple_ai_requests'] ?? 0),
                    'business_ops' => (int)($metrics['business_ops_requests'] ?? 0),
                    'complex' => (int)($metrics['complex_requests'] ?? 0)
                ],
                'avg_total_time_ms' => round((float)($metrics['avg_total_time_ms'] ?? 0), 1),
                'avg_instant_time_ms' => round((float)($metrics['avg_instant_time_ms'] ?? 0), 1),
                'avg_ai_latency_ms' => round((float)($metrics['avg_ai_latency_ms'] ?? 0), 1),
                'total_tokens_used' => (int)($metrics['total_tokens_used'] ?? 0)
            ];
        } catch (Throwable $e) {
            return [];
        }
    }
}
