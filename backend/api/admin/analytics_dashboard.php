<?php
// backend/api/admin/analytics_dashboard.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

$db = Database::getConnection();

try {
    // 1. Calculate General Metrics
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Active users in last 7 days
    $activeUsers7d = (int)$db->query("
        SELECT COUNT(DISTINCT user_id) 
        FROM activity_logs 
        WHERE created_at >= NOW() - INTERVAL 7 DAY
    ")->fetchColumn();

    $totalLeads = (int)$db->query("SELECT COUNT(*) FROM lead_vault")->fetchColumn();
    
    // Total tokens consumed
    $totalTokens = (int)$db->query("
        SELECT SUM(tokens_used) 
        FROM ai_generations
    ")->fetchColumn();

    // Total subscription/credits revenue (Razorpay transactions)
    $totalRevenue = (float)$db->query("
        SELECT SUM(amount) 
        FROM email_credit_transactions 
        WHERE type = 'recharge' AND status = 'success'
    ")->fetchColumn();

    // Total central keys health
    $stmtKeys = $db->query("
        SELECT 
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_keys,
            SUM(CASE WHEN status != 'active' THEN 1 ELSE 0 END) AS invalid_keys
        FROM user_ai_keys
        WHERE user_id IS NULL OR user_id = 0
    ");
    $keysMetrics = $stmtKeys->fetch(PDO::FETCH_ASSOC);

    // 2. Generate 15-day activity records for Chart.js
    // Initialize 15 days array (today-14 to today)
    $dailyTimeline = [];
    for ($i = 14; $i >= 0; $i--) {
        $dateStr = date('Y-m-d', strtotime("-$i days"));
        $dailyTimeline[$dateStr] = [
            'date' => date('M d', strtotime("-$i days")),
            'pageviews' => 0,
            'emails' => 0,
            'whatsapp' => 0
        ];
    }

    // Query visitor activities
    try {
        $pvRes = $db->query("
            SELECT DATE(a.created_at) AS act_date, COUNT(*) AS cnt
            FROM visitor_activities a
            WHERE a.created_at >= NOW() - INTERVAL 14 DAY
            GROUP BY DATE(a.created_at)
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pvRes as $row) {
            if (isset($dailyTimeline[$row['act_date']])) {
                $dailyTimeline[$row['act_date']]['pageviews'] = (int)$row['cnt'];
            }
        }
    } catch (Exception $ex) {}

    // Query outbound emails
    try {
        $emRes = $db->query("
            SELECT DATE(e.created_at) AS act_date, COUNT(*) AS cnt
            FROM sent_emails e
            WHERE e.status = 'sent' AND e.created_at >= NOW() - INTERVAL 14 DAY
            GROUP BY DATE(e.created_at)
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($emRes as $row) {
            if (isset($dailyTimeline[$row['act_date']])) {
                $dailyTimeline[$row['act_date']]['emails'] = (int)$row['cnt'];
            }
        }
    } catch (Exception $ex) {}

    // Query outbound WhatsApp messages
    try {
        // Look in whatsapp_messages table
        $waRes = $db->query("
            SELECT DATE(w.created_at) AS act_date, COUNT(*) AS cnt
            FROM whatsapp_messages w
            WHERE w.direction = 'outbound' AND w.status = 'sent' AND w.created_at >= NOW() - INTERVAL 14 DAY
            GROUP BY DATE(w.created_at)
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($waRes as $row) {
            if (isset($dailyTimeline[$row['act_date']])) {
                $dailyTimeline[$row['act_date']]['whatsapp'] = (int)$row['cnt'];
            }
        }
    } catch (Exception $ex) {}

    sendJsonResponse('success', 'Dashboard metrics generated.', [
        'summary' => [
            'total_users' => $totalUsers,
            'active_users_7d' => $activeUsers7d,
            'total_leads' => $totalLeads,
            'total_tokens' => $totalTokens,
            'total_revenue' => $totalRevenue,
            'active_ai_keys' => (int)($keysMetrics['active_keys'] ?? 0),
            'invalid_ai_keys' => (int)($keysMetrics['invalid_keys'] ?? 0),
        ],
        'chart_data' => array_values($dailyTimeline)
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Dashboard compilation failed: ' . $e->getMessage(), [], 500);
}
