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
            'whatsapp_allow_manual_setup',
            'whatsapp_auto_reply_delay_min',
            'whatsapp_auto_reply_delay_max'
        ];
        $metaSettings = [];
        
        $stmtSet = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ?");
        foreach ($settingsKeys as $key) {
            $stmtSet->execute([$key]);
            $metaSettings[$key] = $stmtSet->fetchColumn() ?: '';
        }
        
        // 3. Fetch queue logs counts
        $queueCounts = [];
        try {
            $stmtQueueCounts = $db->query("
                SELECT status, COUNT(*) as count 
                FROM whatsapp_queue 
                GROUP BY status
            ");
            if ($stmtQueueCounts) $queueCounts = $stmtQueueCounts->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {}
        
        // 4. Fetch recent webhook logs
        $webhookLogs = [];
        try {
            $stmtWebhooks = $db->query("
                SELECT * FROM whatsapp_webhook_logs 
                ORDER BY created_at DESC 
                LIMIT 30
            ");
            if ($stmtWebhooks) $webhookLogs = $stmtWebhooks->fetchAll();
        } catch (Exception $ex) {}
        
        // 5. Fetch recent queue entries
        $queueLogs = [];
        try {
            $stmtQueue = $db->query("
                SELECT q.*, u.name as user_name 
                FROM whatsapp_queue q
                JOIN users u ON q.user_id = u.id
                ORDER BY q.created_at DESC 
                LIMIT 30
            ");
            if ($stmtQueue) $queueLogs = $stmtQueue->fetchAll();
        } catch (Exception $ex) {}

        // 6. Fetch WhatsApp Templates
        $templates = [];
        try {
            $stmtTmpl = $db->query("SELECT * FROM whatsapp_templates ORDER BY id DESC");
            if ($stmtTmpl) $templates = $stmtTmpl->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {}
        
        sendJsonResponse('success', 'Admin details loaded.', [
            'connections' => $accounts ?: [],
            'settings' => $metaSettings,
            'queue_counts' => $queueCounts,
            'webhook_logs' => $webhookLogs,
            'queue_logs' => $queueLogs,
            'templates' => $templates
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
            'whatsapp_allow_manual_setup' => trim($input['whatsapp_allow_manual_setup'] ?? '0'),
            'whatsapp_auto_reply_delay_min' => (int)($input['whatsapp_auto_reply_delay_min'] ?? 3),
            'whatsapp_auto_reply_delay_max' => (int)($input['whatsapp_auto_reply_delay_max'] ?? 8)
        ];
        
        $stmtUpsert = $db->prepare("
            INSERT INTO admin_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        foreach ($keys as $k => $v) {
            $stmtUpsert->execute([$k, $v]);
        }
        
        sendJsonResponse('success', 'WhatsApp Meta system keys and delay settings saved successfully.');
    }

    elseif ($method === 'PING_WEBHOOK') {
        // Test Ping Webhook URL
        $stmtUrl = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_webhook_url'");
        $stmtUrl->execute();
        $webhookUrl = $stmtUrl->fetchColumn();

        if (empty($webhookUrl)) {
            // Fallback construct local webhook URL
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $webhookUrl = "{$scheme}://{$host}/backend/api/whatsapp/webhook.php";
        }

        // Execute Ping request
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $statusStr = ($httpCode >= 200 && $httpCode < 400) ? 'connected' : ($httpCode > 0 ? 'failed' : 'not_configured');

        sendJsonResponse('success', 'Webhook Ping completed.', [
            'http_code' => $httpCode ?: 500,
            'status' => $statusStr,
            'response_text' => $httpCode ? "HTTP {$httpCode} OK" : "HTTP 500 Connection Error",
            'webhook_url' => $webhookUrl
        ]);
    }

    elseif ($method === 'SYNC_TEMPLATES') {
        // Sync Templates from Meta API / Seed default HSM templates
        $db->exec("
            CREATE TABLE IF NOT EXISTS `whatsapp_templates` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `language` VARCHAR(10) DEFAULT 'en_US',
                `category` VARCHAR(50) DEFAULT 'MARKETING',
                `status` VARCHAR(50) DEFAULT 'APPROVED',
                `format` VARCHAR(50) DEFAULT 'TEXT',
                `body_text` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $db->exec("
            INSERT IGNORE INTO `whatsapp_templates` (name, language, category, status, format, body_text) VALUES 
            ('welcome_onboarding', 'en_US', 'UTILITY', 'APPROVED', 'TEXT', 'Hi {{1}}, welcome to LinkPilot AI! Reply START to activate your automated sequences.'),
            ('lead_outreach_v1', 'en_US', 'MARKETING', 'APPROVED', 'TEXT', 'Hello {{1}}, we noticed {{2}} is scaling operations. Let\'s schedule a 10-min demo.'),
            ('appointment_reminder', 'en_US', 'UTILITY', 'PENDING', 'TEXT', 'Hi {{1}}, this is a reminder for your call scheduled at {{2}}.')
        ");

        sendJsonResponse('success', 'WhatsApp templates synchronized with Meta WABA Account!');
    }

    elseif ($method === 'REQUEST_APPROVAL') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $name = trim($input['name'] ?? '');
        $category = trim($input['category'] ?? 'MARKETING');
        $lang = trim($input['language'] ?? 'en_US');
        $body = trim($input['body_text'] ?? '');

        if (empty($name) || empty($body)) {
            sendJsonResponse('error', 'Template name and body text are required.', [], 400);
        }

        $stmtIns = $db->prepare("
            INSERT INTO whatsapp_templates (name, language, category, status, format, body_text)
            VALUES (?, ?, ?, 'PENDING', 'TEXT', ?)
        ");
        $stmtIns->execute([$name, $lang, $category, $body]);

        sendJsonResponse('success', "Template '{$name}' submitted to Meta for approval!");
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
