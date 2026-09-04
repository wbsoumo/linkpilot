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

    // 3. Financial breakdown (Daily, Weekly, Total, AI costs)
    $revenueDaily = (float)$db->query("
        SELECT COALESCE(SUM(amount), 0) 
        FROM email_credit_transactions 
        WHERE type = 'recharge' AND status = 'success' AND DATE(created_at) = CURDATE()
    ")->fetchColumn();

    $revenueWeekly = (float)$db->query("
        SELECT COALESCE(SUM(amount), 0) 
        FROM email_credit_transactions 
        WHERE type = 'recharge' AND status = 'success' AND created_at >= NOW() - INTERVAL 7 DAY
    ")->fetchColumn();

    // AI API estimated cost ($0.00015 / 1K tokens => converted approx to INR or USD)
    $aiCostUsd = ($totalTokens / 1000) * 0.00015;
    $aiCostInr = $aiCostUsd * 86.5; // USD to INR conversion
    $netRevenue = max(0, $totalRevenue - $aiCostInr);

    // 4. Server Quick Control Switches from admin_settings
    $stmtCtrl = $db->query("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key IN ('maintenance_mode', 'disable_registration', 'emergency_pause_ai')");
    $ctrlRaw = $stmtCtrl->fetchAll(PDO::FETCH_KEY_PAIR);
    $serverControls = [
        'maintenance_mode' => ($ctrlRaw['maintenance_mode'] ?? '0') === '1',
        'disable_registration' => ($ctrlRaw['disable_registration'] ?? '0') === '1',
        'emergency_pause_ai' => ($ctrlRaw['emergency_pause_ai'] ?? '0') === '1'
    ];

    // 5. Real-Time Activity Feed (User login, WA reply, Email sent, AI process)
    $stmtActivity = $db->query("
        SELECT 'login' as type, u.name as user_name, u.email as detail, l.created_at, 'User Logged In' as title
        FROM activity_logs l
        JOIN users u ON l.user_id = u.id
        WHERE l.action LIKE '%login%' OR l.action LIKE '%session%'
        UNION ALL
        SELECT 'whatsapp' as type, u.name as user_name, CONCAT('Contact #', w.wa_contact_id) as detail, w.created_at, 'WhatsApp Message Sent' as title
        FROM whatsapp_messages w
        JOIN users u ON w.user_id = u.id
        UNION ALL
        SELECT 'email' as type, u.name as user_name, e.recipient_email as detail, e.created_at, 'Outreach Email Delivered' as title
        FROM sent_emails e
        JOIN users u ON e.user_id = u.id
        UNION ALL
        SELECT 'ai' as type, u.name as user_name, CONCAT(g.type, ' (', g.tokens_used, ' tokens)') as detail, g.created_at, 'AI Reply Generated' as title
        FROM ai_generations g
        JOIN users u ON g.user_id = u.id
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $activityFeed = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse('success', 'Dashboard metrics generated.', [
        'summary' => [
            'total_users' => $totalUsers,
            'active_users_7d' => $activeUsers7d,
            'total_leads' => $totalLeads,
            'total_tokens' => $totalTokens,
            'total_revenue' => $totalRevenue,
            'revenue_daily' => $revenueDaily,
            'revenue_weekly' => $revenueWeekly,
            'ai_cost_usd' => round($aiCostUsd, 4),
            'ai_cost_inr' => round($aiCostInr, 2),
            'net_revenue' => round($netRevenue, 2),
            'active_ai_keys' => (int)($keysMetrics['active_keys'] ?? 0),
            'invalid_ai_keys' => (int)($keysMetrics['invalid_keys'] ?? 0),
        ],
        'server_controls' => $serverControls,
        'activity_feed' => $activityFeed,
        'chart_data' => array_values($dailyTimeline)
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Dashboard compilation failed: ' . $e->getMessage(), [], 500);
}
