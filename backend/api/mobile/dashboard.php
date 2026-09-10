<?php
// backend/api/mobile/dashboard.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

header('Content-Type: application/json');

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

try {
    // 1. Fetch User Profile Summary
    $stmtUser = $db->prepare("SELECT id, name, email, role, phone_number FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch() ?: $user;

    // 2. High Priority & Today's Tasks
    $stmtTasks = $db->prepare("
        SELECT id, title, description, status, priority, due_date, due_time, company_id, contact_id
        FROM crm_tasks
        WHERE user_id = :user_id
        ORDER BY 
          CASE WHEN status != 'completed' AND (due_date < CURRENT_DATE() OR priority = 'high') THEN 1 ELSE 2 END,
          due_date ASC, 
          priority DESC
        LIMIT 10
    ");
    $stmtTasks->execute(['user_id' => $userId]);
    $allTasks = $stmtTasks->fetchAll() ?: [];

    $todayTasks = [];
    $highPriorityTasks = [];
    $overdueCount = 0;
    $todayDate = date('Y-m-d');

    foreach ($allTasks as $task) {
        if ($task['status'] !== 'completed' && !empty($task['due_date']) && $task['due_date'] < $todayDate) {
            $overdueCount++;
        }
        if ($task['priority'] === 'high' && $task['status'] !== 'completed') {
            $highPriorityTasks[] = $task;
        }
        if (!empty($task['due_date']) && $task['due_date'] === $todayDate) {
            $todayTasks[] = $task;
        }
    }

    // 3. Important Emails (Unread or Starred or High Priority)
    $importantEmails = [];
    try {
        $stmtEmails = $db->prepare("
            SELECT id, sender_name, sender_email, subject, body_text, received_date, is_read, is_starred, category, priority
            FROM received_emails
            WHERE user_id = ?
            ORDER BY is_starred DESC, is_read ASC, received_date DESC
            LIMIT 5
        ");
        $stmtEmails->execute([$userId]);
        $importantEmails = $stmtEmails->fetchAll() ?: [];
    } catch (Exception $e) {
        // Table might not exist yet or be empty
        $importantEmails = [];
    }

    // 4. WhatsApp Conversations Needing Attention
    $whatsappConversations = [];
    $unreadWaCount = 0;
    try {
        $stmtWa = $db->prepare("
            SELECT id, wa_id, name, avatar, last_message, last_message_time, unread_count, tag, reply_required
            FROM whatsapp_contacts
            WHERE user_id = ?
            ORDER BY unread_count DESC, last_message_time DESC
            LIMIT 5
        ");
        $stmtWa->execute([$userId]);
        $whatsappConversations = $stmtWa->fetchAll() ?: [];

        foreach ($whatsappConversations as $wa) {
            $unreadWaCount += (int)($wa['unread_count'] ?? 0);
        }
    } catch (Exception $e) {
        $whatsappConversations = [];
    }

    // 5. Unread Mail Count
    $unreadEmailCount = 0;
    try {
        $stmtUnreadMail = $db->prepare("SELECT COUNT(*) FROM received_emails WHERE user_id = ? AND is_read = 0");
        $stmtUnreadMail->execute([$userId]);
        $unreadEmailCount = (int)$stmtUnreadMail->fetchColumn();
    } catch (Exception $e) {}

    // 6. Connected Services Status
    $gmailConnected = false;
    try {
        $stmtSmtp = $db->prepare("SELECT COUNT(*) FROM smtp_accounts WHERE user_id = ?");
        $stmtSmtp->execute([$userId]);
        $gmailConnected = ((int)$stmtSmtp->fetchColumn()) > 0;
    } catch (Exception $e) {}

    $whatsappConnected = false;
    try {
        $stmtWaCheck = $db->prepare("SELECT COUNT(*) FROM whatsapp_contacts WHERE user_id = ?");
        $stmtWaCheck->execute([$userId]);
        $whatsappConnected = ((int)$stmtWaCheck->fetchColumn()) > 0;
    } catch (Exception $e) {}

    sendJsonResponse('success', 'Mobile dashboard data loaded', [
        'user' => $userData,
        'date' => [
            'raw' => date('Y-m-d'),
            'formatted' => date('l, F j')
        ],
        'counts' => [
            'today_tasks' => count($todayTasks),
            'high_priority_tasks' => count($highPriorityTasks),
            'overdue_tasks' => $overdueCount,
            'unread_emails' => $unreadEmailCount,
            'unread_whatsapp' => $unreadWaCount,
        ],
        'priority' => [
            'high_priority_tasks' => array_slice($highPriorityTasks, 0, 3),
            'important_emails' => array_slice($importantEmails, 0, 3),
            'whatsapp_attention' => array_slice($whatsappConversations, 0, 3)
        ],
        'today_tasks' => array_slice($todayTasks, 0, 5),
        'important_emails' => $importantEmails,
        'whatsapp_conversations' => $whatsappConversations,
        'connected_services' => [
            'gmail' => $gmailConnected,
            'whatsapp' => $whatsappConnected,
        ]
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to load dashboard data: ' . $e->getMessage(), [], 500);
}
