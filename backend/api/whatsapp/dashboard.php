<?php
// backend/api/whatsapp/dashboard.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

try {
    // 1. Fetch connected account info
    $stmtAcc = $db->prepare("SELECT business_name, display_phone_number, status, quality_rating, messaging_limit FROM whatsapp_accounts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtAcc->execute([$userId]);
    $account = $stmtAcc->fetch() ?: null;
    
    // 2. Statistics Cards counters
    // Sent Today
    $stmtSent = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'outbound' AND DATE(created_at) = CURDATE()");
    $stmtSent->execute([$userId]);
    $sentToday = (int)$stmtSent->fetchColumn();
    
    // Received Today
    $stmtRec = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'inbound' AND DATE(created_at) = CURDATE()");
    $stmtRec->execute([$userId]);
    $recToday = (int)$stmtRec->fetchColumn();
    
    // Delivered total (status is delivered or read)
    $stmtDel = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'outbound' AND (status = 'delivered' OR status = 'read')");
    $stmtDel->execute([$userId]);
    $delivered = (int)$stmtDel->fetchColumn();
    
    // Read total
    $stmtRead = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'outbound' AND status = 'read'");
    $stmtRead->execute([$userId]);
    $read = (int)$stmtRead->fetchColumn();
    
    // Failed total
    $stmtFail = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'outbound' AND status = 'failed'");
    $stmtFail->execute([$userId]);
    $failed = (int)$stmtFail->fetchColumn();
    
    // Active conversations (threads with contacts)
    $stmtActive = $db->prepare("SELECT COUNT(*) FROM whatsapp_contacts WHERE user_id = ?");
    $stmtActive->execute([$userId]);
    $activeConvs = (int)$stmtActive->fetchColumn();
    
    // Campaigns running
    $stmtCamp = $db->prepare("SELECT COUNT(*) FROM whatsapp_campaigns WHERE user_id = ? AND status IN ('scheduled', 'sending')");
    $stmtCamp->execute([$userId]);
    $campsRunning = (int)$stmtCamp->fetchColumn();
    
    // Templates approved
    $stmtTpl = $db->prepare("SELECT COUNT(*) FROM whatsapp_templates WHERE user_id = ? AND status = 'APPROVED'");
    $stmtTpl->execute([$userId]);
    $tplApproved = (int)$stmtTpl->fetchColumn();
    
    // Broadcast success rate
    $stmtBcast = $db->prepare("
        SELECT 
            SUM(sent_count) as total_sent,
            SUM(delivered_count) as total_del
        FROM whatsapp_campaigns 
        WHERE user_id = ?
    ");
    $stmtBcast->execute([$userId]);
    $bcastStats = $stmtBcast->fetch();
    $bcastTotal = (int)($bcastStats['total_sent'] ?? 0);
    $bcastDel = (int)($bcastStats['total_del'] ?? 0);
    $bcastSuccessRate = $bcastTotal > 0 ? round(($bcastDel / $bcastTotal) * 100, 1) : 100.0;
    
    // 3. Charts data: Daily Messages (Last 7 Days)
    $dailyCharts = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $label = date('D', strtotime($date));
        
        $stmtCSent = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'outbound' AND DATE(created_at) = ?");
        $stmtCSent->execute([$userId, $date]);
        $cSent = (int)$stmtCSent->fetchColumn();
        
        $stmtCRec = $db->prepare("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND direction = 'inbound' AND DATE(created_at) = ?");
        $stmtCRec->execute([$userId, $date]);
        $cRec = (int)$stmtCRec->fetchColumn();
        
        $dailyCharts[] = [
            'label' => $label,
            'sent' => $cSent,
            'received' => $cRec
        ];
    }
    
    // 4. Conversation Categories / Sentiments Distribution
    $stmtSentiment = $db->prepare("
        SELECT sentiment, COUNT(*) as count 
        FROM whatsapp_messages 
        WHERE user_id = ? AND direction = 'inbound' 
        GROUP BY sentiment
    ");
    $stmtSentiment->execute([$userId]);
    $sentimentList = $stmtSentiment->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Recent Activities
    // Latest conversations
    $stmtLatestChats = $db->prepare("
        SELECT c.wa_id, c.profile_name, m.body, m.created_at 
        FROM whatsapp_contacts c
        JOIN whatsapp_messages m ON m.wa_contact_id = c.id
        WHERE c.user_id = ?
        ORDER BY m.created_at DESC
        LIMIT 5
    ");
    $stmtLatestChats->execute([$userId]);
    $latestChats = $stmtLatestChats->fetchAll(PDO::FETCH_ASSOC);
    
    // Latest campaigns
    $stmtLatestCamps = $db->prepare("
        SELECT name, status, sent_count, total_contacts, created_at 
        FROM whatsapp_campaigns 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmtLatestCamps->execute([$userId]);
    $latestCamps = $stmtLatestCamps->fetchAll(PDO::FETCH_ASSOC);
    
    // Failed Messages
    $stmtFailedMsgs = $db->prepare("
        SELECT c.profile_name, m.body, m.error_message, m.created_at 
        FROM whatsapp_messages m
        JOIN whatsapp_contacts c ON m.wa_contact_id = c.id
        WHERE m.user_id = ? AND m.status = 'failed'
        ORDER BY m.created_at DESC
        LIMIT 5
    ");
    $stmtFailedMsgs->execute([$userId]);
    $failedMsgs = $stmtFailedMsgs->fetchAll(PDO::FETCH_ASSOC);
    
    // AI reply suggestions
    $stmtSuggestions = $db->prepare("
        SELECT c.profile_name, m.body as original_message, m.ai_suggested_reply, m.created_at 
        FROM whatsapp_messages m
        JOIN whatsapp_contacts c ON m.wa_contact_id = c.id
        WHERE m.user_id = ? AND m.ai_suggested_reply IS NOT NULL AND m.direction = 'inbound'
        ORDER BY m.created_at DESC
        LIMIT 3
    ");
    $stmtSuggestions->execute([$userId]);
    $aiSuggestions = $stmtSuggestions->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse('success', 'WhatsApp Dashboard metrics loaded.', [
        'account' => $account,
        'cards' => [
            'sent_today' => $sentToday,
            'received_today' => $recToday,
            'delivered_total' => $delivered,
            'read_total' => $read,
            'failed_total' => $failed,
            'active_conversations' => $activeConvs,
            'campaigns_running' => $campsRunning,
            'templates_approved' => $tplApproved,
            'broadcast_success_rate' => $bcastSuccessRate
        ],
        'charts' => [
            'daily_messages' => $dailyCharts,
            'sentiments' => $sentimentList
        ],
        'activities' => [
            'latest_chats' => $latestChats,
            'latest_campaigns' => $latestCamps,
            'failed_messages' => $failedMsgs,
            'ai_suggestions' => $aiSuggestions
        ]
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Failed loading dashboard analytics: ' . $e->getMessage(), [], 500);
}
