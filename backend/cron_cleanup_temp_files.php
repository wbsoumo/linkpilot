<?php
// backend/cron_cleanup_temp_files.php
// LinkPilot AI: Dedicated Server-Side Cron Job for Temporary AI-Generated Files.
// Automatically deletes physical files and marks records expired strictly after 30 minutes.
// Can be invoked via CLI (Linux cron / systemd timer) or via secure HTTP trigger.
//
// Recommended Linux Crontab entry (runs every 5 minutes):
// */5 * * * * php /var/www/html/backend/cron_cleanup_temp_files.php > /dev/null 2>&1

set_time_limit(180);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/temp_file_service.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_GET['token'] ?? '';
    $validTokens = ['lp_cron_clean_9823', 'LP_CRON_SYNC_KEY_5a4c98', 'LP_DIAGNOSTIC_9823'];
    if (!in_array($token, $validTokens, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden', 'message' => 'Access Denied: Invalid cron cleanup token.']);
        exit;
    }
}

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

try {
    $db = Database::getConnection();

    // 1. Run main cleanup: unlinks expired physical files and sets is_deleted = 1
    $cleanupResult = TempFileService::cleanupExpired($db);

    // 2. Sweep old tombstone DB records older than 7 days to keep table compact
    $purgedTombstones = 0;
    try {
        $stmt = $db->prepare("DELETE FROM ai_generated_files WHERE is_deleted = 1 AND deleted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $purgedTombstones = $stmt->rowCount();
    } catch (Throwable $e) {
        // Table might be fresh
    }

    $durationMs = round((microtime(true) - $startTime) * 1000, 2);

    $summary = [
        'success' => true,
        'timestamp' => $timestamp,
        'duration_ms' => $durationMs,
        'expired_records_processed' => $cleanupResult['db_records_expired'] ?? 0,
        'disk_orphans_purged' => $cleanupResult['orphans_purged'] ?? 0,
        'old_tombstones_purged' => $purgedTombstones,
        'status' => 'Storage clean. 30-minute expiry strictly enforced.'
    ];

    if ($isCli) {
        echo "[{$timestamp}] LinkPilot AI Temp File Cleanup:\n";
        echo "  - Expired records unlinked: " . ($summary['expired_records_processed']) . "\n";
        echo "  - Disk orphans purged: " . ($summary['disk_orphans_purged']) . "\n";
        echo "  - Old tombstones purged: " . ($summary['old_tombstones_purged']) . "\n";
        echo "  - Execution time: {$durationMs}ms\n";
    } else {
        echo json_encode($summary, JSON_PRETTY_PRINT);
    }
} catch (Throwable $e) {
    if ($isCli) {
        echo "[{$timestamp}] Cleanup Error: " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => $timestamp
        ]);
    }
}
