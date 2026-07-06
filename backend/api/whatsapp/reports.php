<?php
// backend/api/whatsapp/reports.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

try {
    // 1. Total lifetime counts
    $stmtT = $db->prepare("
        SELECT 
            SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as total_sent,
            SUM(CASE WHEN direction = 'outbound' AND (status = 'delivered' OR status = 'read') THEN 1 ELSE 0 END) as total_delivered,
            SUM(CASE WHEN direction = 'outbound' AND status = 'read' THEN 1 ELSE 0 END) as total_read,
            SUM(CASE WHEN direction = 'outbound' AND status = 'failed' THEN 1 ELSE 0 END) as total_failed,
            SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as total_received
        FROM whatsapp_messages 
        WHERE user_id = ?
    ");
    $stmtT->execute([$userId]);
    $totals = $stmtT->fetch();
    
    $totalSent = (int)($totals['total_sent'] ?? 0);
    $totalDel = (int)($totals['total_delivered'] ?? 0);
    $totalRead = (int)($totals['total_read'] ?? 0);
    $totalFail = (int)($totals['total_failed'] ?? 0);
    $totalRec = (int)($totals['total_received'] ?? 0);
    
    // Reply rate
    $replyRate = $totalSent > 0 ? round(($totalRec / $totalSent) * 100, 1) : 0.0;
    
    // 2. Campaigns success rankings
    $stmtCamps = $db->prepare("
        SELECT name, total_contacts, sent_count, delivered_count, read_count, failed_count 
        FROM whatsapp_campaigns 
        WHERE user_id = ? AND status = 'completed'
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmtCamps->execute([$userId]);
    $campaignSuccess = $stmtCamps->fetchAll();
    
    // 3. Templates usage counters
    $stmtTpls = $db->prepare("
        SELECT template_name, COUNT(*) as count 
        FROM whatsapp_queue 
        WHERE user_id = ? AND type = 'template' 
        GROUP BY template_name 
        ORDER BY count DESC 
        LIMIT 5
    ");
    $stmtTpls->execute([$userId]);
    $templateStats = $stmtTpls->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Daily Usage over the last 30 days
    $dailyUsage = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        
        $stmtS = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'outbound' AND DATE(created_at) = ?");
        $stmtS->execute([$userId, $date]);
        $s = (int)$stmtS->fetchColumn();
        
        $stmtR = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'inbound' AND DATE(created_at) = ?");
        $stmtR->execute([$userId, $date]);
        $r = (int)$stmtR->fetchColumn();
        
        $dailyUsage[] = [
            'date' => $date,
            'sent' => $s,
            'received' => $r
        ];
    }
    
    sendJsonResponse('success', 'Reports statistics loaded.', [
        'totals' => [
            'sent' => $totalSent,
            'delivered' => $totalDel,
            'read' => $totalRead,
            'failed' => $totalFail,
            'received' => $totalRec,
            'reply_rate' => $replyRate
        ],
        'campaign_success' => $campaignSuccess,
        'template_stats' => $templateStats,
        'daily_usage' => $dailyUsage
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Failed loading reports statistics: ' . $e->getMessage(), [], 500);
}
