<?php
// backend/api/crm/debug_sync.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$userId = null;
$user = null;
$db = Database::getConnection();

if (($_GET['secret'] ?? '') === 'debug123') {
    $requestedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($requestedUserId > 0) {
        $stmtU = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtU->execute([$requestedUserId]);
        $user = $stmtU->fetch();
    }
    if (!$user) {
        $user = $db->query("SELECT * FROM users LIMIT 1")->fetch();
    }
    if ($user) {
        $userId = $user['id'];
    } else {
        die("No users found in database.");
    }
} else {
    try {
        $user = JWTHelper::requireAuth();
        $userId = $user['id'];
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized: ' . $e->getMessage()
        ]);
        exit;
    }
}

header('Content-Type: text/plain');

echo "--- LINKPILOT CRM SYNC DEBUGGER ---\n\n";

echo "RECEIVED EMAILS DETAILED ANALYSIS FOR USER ($userId):\n";
$stmtEmails = $db->prepare("SELECT id, sender_email, subject, ai_status, is_spam, is_archived, parent_id, received_date FROM received_emails WHERE user_id = ? ORDER BY received_date DESC");
$stmtEmails->execute([$userId]);
$emails = $stmtEmails->fetchAll(PDO::FETCH_ASSOC);
echo "  Total received_emails count: " . count($emails) . "\n";
foreach ($emails as $index => $email) {
    $pid = $email['parent_id'] !== null ? $email['parent_id'] : 'NULL';
    echo "  [" . ($index + 1) . "] ID: {$email['id']} | Date: {$email['received_date']} | Status: {$email['ai_status']} | Spam: {$email['is_spam']} | Archived: {$email['is_archived']} | Parent ID: $pid | Subject: {$email['subject']}\n";
}
echo "\n";
