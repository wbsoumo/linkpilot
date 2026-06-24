<?php
// backend/api/admin/dashboard.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Admin privileges
$adminUser = JWTHelper::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$db = Database::getConnection();

try {
    // 1. Total Users
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // 2. Active Users (active in the last 30 days)
    $activeUsers = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
    
    // 3. AI Request Count
    $totalAIRequests = (int)$db->query("SELECT COUNT(*) FROM ai_generations")->fetchColumn();
    
    // 4. Emails Sent
    $totalEmailsSent = (int)$db->query("SELECT COUNT(*) FROM sent_emails WHERE status = 'sent'")->fetchColumn();
    
    // 5. Comments Generated
    $totalComments = (int)$db->query("SELECT COUNT(*) FROM comment_generations")->fetchColumn();
    
    // 6. WhatsApp Generated
    $totalWhatsApp = (int)$db->query("SELECT COUNT(*) FROM whatsapp_generations")->fetchColumn();
    
    // 7. OpenRouter Usage (Tokens & cost estimation)
    $totalTokens = (int)$db->query("SELECT SUM(tokens_used) FROM ai_generations")->fetchColumn();
    
    // Assume average cost of $0.0001 per 1K tokens for model 'gemini-2.0-flash-lite'
    $estimatedCost = ($totalTokens / 1000) * 0.0001;
    
    // 8. User registrations trend last 30 days
    $stmtReg = $db->query("
        SELECT DATE(created_at) as reg_date, COUNT(*) as reg_count 
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY reg_date ASC
    ");
    $registrations = $stmtReg->fetchAll();
    
    // 9. Recent 5 System activity logs with user names
    $stmtLogs = $db->query("
        SELECT l.action, l.created_at, u.name as user_name 
        FROM activity_logs l
        JOIN users u ON l.user_id = u.id
        ORDER BY l.id DESC
        LIMIT 10
    ");
    $systemLogs = $stmtLogs->fetchAll();
    
    sendJsonResponse('success', 'Admin analytics loaded.', [
        'statistics' => [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'ai_requests' => $totalAIRequests,
            'emails_sent' => $totalEmailsSent,
            'comments_generated' => $totalComments,
            'whatsapp_generated' => $totalWhatsApp,
            'tokens_used' => $totalTokens,
            'estimated_cost_usd' => round($estimatedCost, 4)
        ],
        'registrations_trend' => $registrations,
        'recent_logs' => $systemLogs
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Admin dashboard failed: ' . $e->getMessage(), [], 500);
}
