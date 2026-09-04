<?php
// backend/api/admin/crm_settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Authenticate Admin
$user = JWTHelper::requireAdmin();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // 1. Fetch Global Settings
        $stmtSettings = $db->query("SELECT * FROM admin_settings");
        $rawSettings = $stmtSettings->fetchAll();
        $settings = [];
        foreach ($rawSettings as $s) {
            $settings[$s['setting_key']] = $s['setting_value'];
        }

        // 2. Queue Monitoring
        $queueStats = [];
        try {
            $stmtQueue = $db->query("
                SELECT status, COUNT(*) as count 
                FROM email_processing_logs 
                GROUP BY status
            ");
            if ($stmtQueue) {
                $queueStats = $stmtQueue->fetchAll();
            }
        } catch (Exception $ex) {}

        // 3. System Telemetry & Resource Usage Metrics
        // CPU Usage
        $cpuUsage = 0;
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cpuUsage = round(($load[0] * 100) / max(1, (int)shell_exec('nproc 2>/dev/null' ?: 1)), 1);
        }

        // RAM Usage
        $ramUsedMB = 0; $ramTotalMB = 0; $ramPercent = 0;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $ramUsedMB = round(memory_get_usage(true) / 1024 / 1024, 1);
            $ramTotalMB = 8192;
            $ramPercent = round(($ramUsedMB / $ramTotalMB) * 100, 1);
        } else {
            $freeOutput = shell_exec('free -m 2>/dev/null');
            if ($freeOutput && preg_match('/Mem:\s+(\d+)\s+(\d+)/i', $freeOutput, $matches)) {
                $ramTotalMB = (int)$matches[1];
                $ramUsedMB = (int)$matches[2];
                $ramPercent = $ramTotalMB > 0 ? round(($ramUsedMB / $ramTotalMB) * 100, 1) : 0;
            } else {
                $ramUsedMB = round(memory_get_usage(true) / 1024 / 1024, 1);
                $ramTotalMB = 4096;
                $ramPercent = round(($ramUsedMB / $ramTotalMB) * 100, 1);
            }
        }

        // MySQL Response Latency (Ping)
        $startTime = microtime(true);
        $db->query("SELECT 1");
        $mysqlPingMs = round((microtime(true) - $startTime) * 1000, 2);

        // Disk / Storage Usage
        $diskFree = disk_free_space(__DIR__);
        $diskTotal = disk_total_space(__DIR__);
        $diskUsed = $diskTotal - $diskFree;
        $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        // 4. Queue Worker & Cron Status Inspector
        $queueWorkerStatus = 'stopped';
        $workerPid = null;
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $psOutput = shell_exec("ps aux | grep 'queue_worker.php' | grep -v grep 2>/dev/null");
            if ($psOutput && trim($psOutput) !== '') {
                $queueWorkerStatus = 'running';
                $parts = preg_split('/\s+/', trim($psOutput));
                $workerPid = $parts[1] ?? null;
            }
        }
        
        // Check for stalled condition (if queue items exist in 'processing' state for > 15 mins)
        $stalledCount = 0;
        try {
            $stalledCount = (int)$db->query("
                SELECT COUNT(*) FROM email_processing_logs 
                WHERE status = 'processing' AND created_at < NOW() - INTERVAL 15 MINUTE
            ")->fetchColumn();
        } catch (Exception $ex) {}
        if ($stalledCount > 0 && $queueWorkerStatus === 'running') {
            $queueWorkerStatus = 'stalled';
        }

        // 5. System Health Checklist
        $mysqlVersion = 'Unknown';
        try { $mysqlVersion = $db->query("SELECT VERSION()")->fetchColumn(); } catch (Exception $ex) {}
        $totalUsers = 0;
        try { $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch (Exception $ex) {}
        $totalLeads = 0;
        try { $totalLeads = (int)$db->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn(); } catch (Exception $ex) {}
        $totalCompanies = 0;
        try { $totalCompanies = (int)$db->query("SELECT COUNT(*) FROM crm_companies")->fetchColumn(); } catch (Exception $ex) {}
        
        $health = [
            'database_status' => 'healthy',
            'mysql_version' => $mysqlVersion,
            'mysql_ping_ms' => $mysqlPingMs,
            'cpu_usage_percent' => $cpuUsage,
            'ram_used_mb' => $ramUsedMB,
            'ram_total_mb' => $ramTotalMB,
            'ram_percent' => $ramPercent,
            'disk_used_gb' => round($diskUsed / 1073741824, 2),
            'disk_total_gb' => round($diskTotal / 1073741824, 2),
            'disk_percent' => $diskPercent,
            'queue_worker' => [
                'status' => $queueWorkerStatus,
                'pid' => $workerPid,
                'stalled_count' => $stalledCount
            ],
            'imap_extension_loaded' => function_exists('imap_open') ? 'installed' : 'missing',
            'openssl_encryption' => in_array('aes-256-cbc', openssl_get_cipher_methods()) ? 'available' : 'unavailable',
            'total_users' => $totalUsers,
            'total_leads' => $totalLeads,
            'total_companies' => $totalCompanies,
            'php_version' => phpversion()
        ];

        sendJsonResponse('success', 'Admin health settings loaded', [
            'settings' => $settings,
            'queue_stats' => $queueStats,
            'health' => $health
        ]);
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $input['action'] ?? 'save_settings';

        if ($action === 'restart_queue') {
            // Restart Queue Worker process
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                shell_exec("pkill -f 'queue_worker.php' 2>/dev/null");
                $workerScript = __DIR__ . '/../../queue_worker.php';
                shell_exec("nohup php {$workerScript} > /dev/null 2>&1 &");
            }
            sendJsonResponse('success', 'Queue Worker restarted successfully.');
        } 
        elseif ($action === 'run_cron') {
            // Run Queue Worker & Campaign Sync manually
            require_once __DIR__ . '/../../queue_worker.php';
            $processed = 0;
            if (method_exists('QueueWorker', 'processPendingEmails')) {
                $processed = QueueWorker::processPendingEmails();
            }
            sendJsonResponse('success', "Cron job executed manually! Processed {$processed} queue items.");
        } 
        elseif ($action === 'db_maintenance') {
            $type = $input['maintenance_type'] ?? '';
            if ($type === 'optimize') {
                // Run OPTIMIZE TABLE on core system tables
                $tables = ['users', 'visitor_sessions', 'visitor_activities', 'sent_emails', 'email_credit_transactions', 'recharge_orders', 'crm_leads', 'crm_companies'];
                $optimized = [];
                foreach ($tables as $tbl) {
                    try {
                        $db->exec("OPTIMIZE TABLE `{$tbl}`");
                        $optimized[] = $tbl;
                    } catch (Exception $ex) {}
                }
                sendJsonResponse('success', 'Database tables optimized successfully: ' . implode(', ', $optimized));
            } 
            elseif ($type === 'cleanup_sessions') {
                // Cleanup expired sessions older than 30 days
                $stmtDel = $db->query("DELETE FROM visitor_sessions WHERE updated_at < NOW() - INTERVAL 30 DAY");
                $deletedSessions = $stmtDel->rowCount();
                
                $stmtDelErr = $db->query("DELETE FROM platform_errors WHERE created_at < NOW() - INTERVAL 30 DAY");
                $deletedErrors = $stmtDelErr->rowCount();

                sendJsonResponse('success', "Maintenance cleanup finished! Purged {$deletedSessions} expired session logs and {$deletedErrors} error traces.");
            } else {
                sendJsonResponse('error', 'Invalid maintenance action specified.', [], 400);
            }
        } else {
            // Save Global Settings fallback
            $db->beginTransaction();
            foreach ($input as $key => $value) {
                if ($key === 'action') continue;
                $valStr = is_array($value) ? json_encode($value) : $value;
                $stmt = $db->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$key, $valStr]);
            }
            $db->commit();
            sendJsonResponse('success', 'Global settings saved successfully.');
        }
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'System health operation failed: ' . $e->getMessage(), [], 500);
}
