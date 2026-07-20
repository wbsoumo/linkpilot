<?php
// backend/cron_sync.php
// Set execution timeout to 5 minutes to prevent hanging
set_time_limit(300);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sync_helper.php';

// Verify CLI context or secure token if accessed via Web (as a backup trigger)
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    // Basic secret token security if triggered via Web hook
    $token = $_GET['token'] ?? '';
    if (empty($token) || $token !== 'LP_CRON_SYNC_KEY_5a4c98') {
        http_response_code(403);
        echo "Access Denied: Invalid cron token.";
        exit;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Starting background email intelligence synchronization...\n";

try {
    $db = Database::getConnection();
    
    // Fetch all active settings where next sync is due or never synced
    $stmt = $db->prepare("SELECT user_id, last_sync_at, next_sync_at FROM email_intelligence_settings WHERE is_active = 1 AND (next_sync_at <= ? OR next_sync_at IS NULL)");
    $stmt->execute([date('Y-m-d H:i:s')]);
    $jobs = $stmt->fetchAll();
    
    $jobsCount = count($jobs);
    echo "Found $jobsCount active user synchronizations due.\n";
    
    $successCount = 0;
    foreach ($jobs as $job) {
        $userId = (int)$job['user_id'];
        echo "Processing user ID: $userId... ";
        
        try {
            $result = SyncHelper::syncUserEmails($userId);
            echo "Success! Synced " . $result['emails_synced'] . " new emails. Next sync scheduled at: " . $result['next_sync_at'] . "\n";
            $successCount++;
        } catch (Throwable $e) {
            echo "Failed: " . $e->getMessage() . "\n";
        }
    }
    
    // In addition, trigger the background AI queue worker to process any pending items
    echo "Running background AI queue processor...\n";
    require_once __DIR__ . '/queue_worker.php';
    try {
        $processed = QueueWorker::processPendingEmails();
        echo "AI processing complete. Processed $processed pending emails.\n";
        
        $processedWa = QueueWorker::processWhatsAppQueue();
        echo "WhatsApp queue complete. Dispatched $processedWa messages.\n";
        
        $processedEc = QueueWorker::processEmailCampaignQueue();
        echo "Email campaigns queue complete. Dispatched $processedEc emails.\n";
    } catch (Throwable $e) {
        echo "Queue Processor Error: " . $e->getMessage() . "\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Sync process finished. Successfully completed $successCount/$jobsCount jobs.\n";
} catch (Throwable $e) {
    echo "Critical Cron Error: " . $e->getMessage() . "\n";
}
