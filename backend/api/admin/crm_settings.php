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

        // 2. Queue Monitoring (processed count, pending/error status)
        $stmtQueue = $db->query("
            SELECT status, COUNT(*) as count 
            FROM email_processing_logs 
            GROUP BY status
        ");
        $queueStats = $stmtQueue->fetchAll();

        // 3. Email Processing Logs (Recent 50 logs)
        $stmtLogs = $db->query("
            SELECT l.*, u.name as user_name, u.email as user_email 
            FROM email_processing_logs l
            JOIN users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
            LIMIT 50
        ");
        $logs = $stmtLogs->fetchAll();

        // 4. System Health Checklist
        $mysqlVersion = $db->query("SELECT VERSION()")->fetchColumn();
        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalLeads = (int)$db->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn();
        $totalCompanies = (int)$db->query("SELECT COUNT(*) FROM crm_companies")->fetchColumn();
        
        $health = [
            'database_status' => 'healthy',
            'mysql_version' => $mysqlVersion,
            'imap_extension_loaded' => function_exists('imap_open') ? 'installed' : 'missing',
            'openssl_encryption' => in_array('aes-256-cbc', openssl_get_cipher_methods()) ? 'available' : 'unavailable',
            'total_users' => $totalUsers,
            'total_leads' => $totalLeads,
            'total_companies' => $totalCompanies,
            'php_version' => phpversion()
        ];

        sendJsonResponse('success', 'Admin settings loaded successfully', [
            'settings' => $settings,
            'queue_stats' => $queueStats,
            'processing_logs' => $logs,
            'health' => $health
        ]);
        
    } elseif ($method === 'POST') {
        // Save Global Settings
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $db->beginTransaction();

        foreach ($input as $key => $value) {
            $valStr = is_array($value) ? json_encode($value) : $value;
            $stmt = $db->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $valStr]);
        }

        // Log admin activity
        logActivity($userId, "Admin updated global system settings.");

        $db->commit();
        sendJsonResponse('success', 'Global settings saved successfully.');
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Admin operation failed: ' . $e->getMessage(), [], 500);
}
