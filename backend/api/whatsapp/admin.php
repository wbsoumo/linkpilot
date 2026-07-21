<?php
// backend/api/whatsapp/admin.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAdmin();

$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'GET') {
        // 1. Fetch connected accounts
        $stmtAcc = $db->query("
            SELECT a.*, u.name as user_name, u.email as user_email 
            FROM whatsapp_accounts a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.id DESC
        ");
        $accounts = $stmtAcc->fetchAll();
        
        // 2. Fetch admin meta settings keys
        $settingsKeys = [
            'whatsapp_meta_app_id', 
            'whatsapp_meta_app_secret', 
            'whatsapp_webhook_verify_token', 
            'whatsapp_global_max_campaign_size',
            'whatsapp_meta_api_version',
            'whatsapp_webhook_url',
            'whatsapp_default_messaging_settings',
            'whatsapp_mode',
            'whatsapp_token_encryption_key',
            'whatsapp_global_api_timeout',
            'whatsapp_retry_attempts',
            'whatsapp_logging_level',
            'whatsapp_meta_config_id',
            'whatsapp_meta_system_user_token',
            'whatsapp_business_portfolio_id',
            'whatsapp_enable_embedded_signup',
            'whatsapp_allow_manual_setup'
        ];
        $metaSettings = [];
        
        $stmtSet = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ?");
        foreach ($settingsKeys as $key) {
            $stmtSet->execute([$key]);
            $metaSettings[$key] = $stmtSet->fetchColumn() ?: '';
        }
        
        // 3. Fetch queue logs counts
        $stmtQueueCounts = $db->query("
            SELECT status, COUNT(*) as count 
            FROM whatsapp_queue 
            GROUP BY status
        ");
        $queueCounts = $stmtQueueCounts->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Fetch recent webhook logs
        $stmtWebhooks = $db->query("
            SELECT * FROM whatsapp_webhook_logs 
            ORDER BY created_at DESC 
            LIMIT 30
        ");
        $webhookLogs = $stmtWebhooks->fetchAll();
        
        // 5. Fetch recent queue entries
        $stmtQueue = $db->query("
            SELECT q.*, u.name as user_name 
            FROM whatsapp_queue q
            JOIN users u ON q.user_id = u.id
            ORDER BY q.created_at DESC 
            LIMIT 30
        ");
        $queueLogs = $stmtQueue->fetchAll();
        
        sendJsonResponse('success', 'Admin details loaded.', [
            'connections' => $accounts,
            'settings' => $metaSettings,
            'queue_counts' => $queueCounts,
            'webhook_logs' => $webhookLogs,
            'queue_logs' => $queueLogs
        ]);
    }
    
    elseif ($method === 'SAVE_META_KEYS') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $keys = [
            'whatsapp_meta_app_id' => trim($input['whatsapp_meta_app_id'] ?? ''),
            'whatsapp_meta_app_secret' => trim($input['whatsapp_meta_app_secret'] ?? ''),
            'whatsapp_webhook_verify_token' => trim($input['whatsapp_webhook_verify_token'] ?? ''),
            'whatsapp_global_max_campaign_size' => (int)($input['whatsapp_global_max_campaign_size'] ?? 1000),
            'whatsapp_meta_api_version' => trim($input['whatsapp_meta_api_version'] ?? 'v20.0'),
            'whatsapp_webhook_url' => trim($input['whatsapp_webhook_url'] ?? ''),
            'whatsapp_default_messaging_settings' => trim($input['whatsapp_default_messaging_settings'] ?? ''),
            'whatsapp_mode' => trim($input['whatsapp_mode'] ?? 'live'),
            'whatsapp_token_encryption_key' => trim($input['whatsapp_token_encryption_key'] ?? ''),
            'whatsapp_global_api_timeout' => (int)($input['whatsapp_global_api_timeout'] ?? 15),
            'whatsapp_retry_attempts' => (int)($input['whatsapp_retry_attempts'] ?? 3),
            'whatsapp_logging_level' => trim($input['whatsapp_logging_level'] ?? 'debug'),
            'whatsapp_meta_config_id' => trim($input['whatsapp_meta_config_id'] ?? ''),
            'whatsapp_meta_system_user_token' => trim($input['whatsapp_meta_system_user_token'] ?? ''),
            'whatsapp_business_portfolio_id' => trim($input['whatsapp_business_portfolio_id'] ?? ''),
            'whatsapp_enable_embedded_signup' => trim($input['whatsapp_enable_embedded_signup'] ?? '0'),
            'whatsapp_allow_manual_setup' => trim($input['whatsapp_allow_manual_setup'] ?? '0')
        ];
        
        $stmtUpsert = $db->prepare("
            INSERT INTO admin_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        foreach ($keys as $k => $v) {
            $stmtUpsert->execute([$k, $v]);
        }
        
        sendJsonResponse('success', 'WhatsApp Meta system keys saved successfully.');
    }
    
    elseif ($method === 'RETRY_QUEUE') {
        // Retry failed queue items
        $db->exec("UPDATE whatsapp_queue SET status = 'pending', attempts = 0 WHERE status = 'failed'");
        
        sendJsonResponse('success', 'Failed queue dispatches scheduled for retry.');
    }
    
    elseif ($method === 'CANCEL_QUEUE') {
        // Purge pending queue items
        $db->exec("DELETE FROM whatsapp_queue WHERE status = 'pending'");
        
        sendJsonResponse('success', 'Pending queue dispatches purged successfully.');
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Admin operation failed: ' . $e->getMessage(), [], 500);
}
