<?php
// backend/api/analytics/dashboard.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$db = Database::getConnection();

try {
    // 1. Fetch Overview Statistics
    $stmtStats = $db->prepare("SELECT * FROM user_statistics WHERE user_id = ?");
    $stmtStats->execute([$userId]);
    $stats = $stmtStats->fetch();
    
    // If stats don't exist yet, insert default
    if (!$stats) {
        $stmtInsert = $db->prepare("INSERT INTO user_statistics (user_id) VALUES (?)");
        $stmtInsert->execute([$userId]);
        
        $stats = [
            'total_requests' => 0,
            'emails_generated' => 0,
            'emails_sent' => 0,
            'whatsapp_generated' => 0,
            'comments_generated' => 0
        ];
    }
    
    // 2. Fetch 5 Most Recent Activities
    $stmtRecent = $db->prepare("SELECT action, created_at FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $stmtRecent->execute([$userId]);
    $recentActivities = $stmtRecent->fetchAll();
    
    // 3. Fetch 30-day activity trends
    $stmtTrends = $db->prepare("
        SELECT 
            DATE(created_at) as gen_date,
            SUM(CASE WHEN type = 'email' THEN 1 ELSE 0 END) as email_count,
            SUM(CASE WHEN type = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp_count,
            SUM(CASE WHEN type = 'comment' THEN 1 ELSE 0 END) as comment_count,
            COUNT(*) as total_count
        FROM ai_generations
        WHERE user_id = :user_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY gen_date ASC
    ");
    $stmtTrends->execute(['user_id' => $userId]);
    $rawTrends = $stmtTrends->fetchAll();
    
    // Structure the last 30 days daily data (populating days with 0 requests to keep chart scale clean)
    $trends = [];
    for ($i = 29; $i >= 0; $i--) {
        $dateStr = date('Y-m-d', strtotime("-{$i} days"));
        $trends[$dateStr] = [
            'date' => date('d M', strtotime($dateStr)),
            'emails' => 0,
            'whatsapp' => 0,
            'comments' => 0,
            'total' => 0
        ];
    }
    
    foreach ($rawTrends as $row) {
        $dateStr = $row['gen_date'];
        if (isset($trends[$dateStr])) {
            $trends[$dateStr]['emails'] = (int)$row['email_count'];
            $trends[$dateStr]['whatsapp'] = (int)$row['whatsapp_count'];
            $trends[$dateStr]['comments'] = (int)$row['comment_count'];
            $trends[$dateStr]['total'] = (int)$row['total_count'];
        }
    }
    
    sendJsonResponse('success', 'Analytics data loaded.', [
        'statistics' => [
            'total_requests' => (int)$stats['total_requests'],
            'emails_generated' => (int)$stats['emails_generated'],
            'emails_sent' => (int)$stats['emails_sent'],
            'whatsapp_generated' => (int)$stats['whatsapp_generated'],
            'comments_generated' => (int)$stats['comments_generated']
        ],
        'recent_activities' => $recentActivities,
        'chart_trends' => array_values($trends)
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error loading analytics: ' . $e->getMessage(), [], 500);
}
