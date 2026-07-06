<?php
// backend/api/crm/email_intelligence/sync.php

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../jwt_helper.php';
require_once __DIR__ . '/../../../smtp_helper.php';
require_once __DIR__ . '/../../../imap_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($method === 'GET') {
        // Fetch Sync Status, next/last sync details, remaining counts
        $stmtSettings = $db->prepare("SELECT * FROM email_intelligence_settings WHERE user_id = ?");
        $stmtSettings->execute([$userId]);
        $settings = $stmtSettings->fetch();
        
        // Fetch processing logs, grouping by email and selecting the latest status for each to prevent duplicate rows
        $stmtLogs = $db->prepare("
            SELECT l1.* FROM email_processing_logs l1
            JOIN (
                SELECT MAX(id) as max_id 
                FROM email_processing_logs 
                WHERE user_id = ? 
                GROUP BY COALESCE(email_subject, ''), COALESCE(sender, ''), (CASE WHEN email_subject IS NULL AND sender IS NULL THEN id ELSE 0 END)
            ) l2 ON l1.id = l2.max_id
            ORDER BY l1.created_at DESC LIMIT 20
        ");
        $stmtLogs->execute([$userId]);
        $logs = $stmtLogs->fetchAll();
        
        $syncInterval = $settings['sync_interval_minutes'] ?? 60;
        $isActive = $settings['is_active'] ?? 0;
        $lastSync = $settings['last_sync_at'] ?? 'Never';
        $nextSync = $settings['next_sync_at'] ?? 'Pending';
        
        // Count queue and emails remaining
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM received_emails WHERE user_id = ?");
        $stmtCount->execute([$userId]);
        $totalEmails = (int)$stmtCount->fetchColumn();
        
        sendJsonResponse('success', 'Sync status retrieved', [
            'is_active' => (bool)$isActive,
            'sync_interval_minutes' => $syncInterval,
            'last_sync_at' => $lastSync,
            'next_sync_at' => $nextSync,
            'total_emails' => $totalEmails,
            'logs' => $logs
        ]);
        
    } elseif ($method === 'POST') {
        if ($action === 'toggle') {
            // Pause or Resume synchronization
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            $activeState = isset($input['active']) ? (int)$input['active'] : 0;
            
            $nextSyncDate = null;
            if ($activeState) {
                $stmtSet = $db->prepare("SELECT sync_interval_minutes FROM email_intelligence_settings WHERE user_id = ?");
                $stmtSet->execute([$userId]);
                $interval = (int)($stmtSet->fetchColumn() ?: 60);
                $nextSyncDate = date('Y-m-d H:i:s', strtotime("+$interval minutes"));
            }
            
            $stmt = $db->prepare("UPDATE email_intelligence_settings SET is_active = ?, next_sync_at = ? WHERE user_id = ?");
            $stmt->execute([$activeState, $nextSyncDate, $userId]);
            
            sendJsonResponse('success', $activeState ? 'Sync service resumed successfully.' : 'Sync service paused successfully.');
            
        } elseif ($action === 'sync' || empty($action)) {
            // Manual sync trigger
            require_once __DIR__ . '/../../../sync_helper.php';
            try {
                $result = SyncHelper::syncUserEmails($userId);
                sendJsonResponse('success', 'Synchronization completed.', $result);
            } catch (Throwable $e) {
                sendJsonResponse('error', 'Sync operation failed: ' . $e->getMessage(), [], 200);
            }
        }
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Sync operation failed: ' . $e->getMessage(), [], 200);
}
