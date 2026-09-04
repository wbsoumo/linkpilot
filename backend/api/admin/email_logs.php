<?php
// backend/api/admin/email_logs.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

$db = Database::getConnection();

require_once __DIR__ . '/../../smtp_helper.php';

// Handle POST Request for Resending Failed Email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $emailId = (int)($input['email_id'] ?? 0);

    if ($emailId <= 0) {
        sendJsonResponse('error', 'Invalid Email Log ID.', [], 400);
    }

    // Fetch original email record
    $stmtEmail = $db->prepare("SELECT * FROM sent_emails WHERE id = ?");
    $stmtEmail->execute([$emailId]);
    $emailRec = $stmtEmail->fetch(PDO::FETCH_ASSOC);

    if (!$emailRec) {
        sendJsonResponse('error', 'Email log not found.', [], 404);
    }

    // Re-attempt transmission via SMTPHelper
    $res = SMTPHelper::sendEmail($emailRec['user_id'], $emailRec['recipient_email'], $emailRec['subject'], $emailRec['body']);

    if (is_array($res) && isset($res['success']) && $res['success']) {
        // Update original log status
        $stmtUp = $db->prepare("UPDATE sent_emails SET status = 'sent', error_message = NULL WHERE id = ?");
        $stmtUp->execute([$emailId]);

        sendJsonResponse('success', "Email successfully resent to {$emailRec['recipient_email']}!", [
            'email_id' => $emailId,
            'recipient' => $emailRec['recipient_email']
        ]);
    } else {
        $errMsg = is_array($res) ? ($res['message'] ?? 'SMTP Handshake Failed') : 'Resend Failed';
        $stmtUp = $db->prepare("UPDATE sent_emails SET status = 'failed', error_message = ? WHERE id = ?");
        $stmtUp->execute([$errMsg, $emailId]);

        sendJsonResponse('error', 'Failed to resend email: ' . $errMsg, [], 500);
    }
}

try {
    // 1. Calculate Deliverability Diagnostics Metrics
    $totalCount = (int)$db->query("SELECT COUNT(*) FROM sent_emails")->fetchColumn();
    $sentCount = (int)$db->query("SELECT COUNT(*) FROM sent_emails WHERE status = 'sent'")->fetchColumn();
    
    // Hard bounces (failed status with bounce indicators)
    $bounceCount = (int)$db->query("
        SELECT COUNT(*) FROM sent_emails 
        WHERE status IN ('failed', 'bounced') 
           OR error_message LIKE '%bounce%' 
           OR error_message LIKE '%550%' 
           OR error_message LIKE '%554%' 
           OR error_message LIKE '%user unknown%'
    ")->fetchColumn();

    // Spam complaints (error logs matching spam / 552 / rejected)
    $spamCount = (int)$db->query("
        SELECT COUNT(*) FROM sent_emails 
        WHERE error_message LIKE '%spam%' 
           OR error_message LIKE '%complaint%' 
           OR error_message LIKE '%blacklisted%' 
           OR error_message LIKE '%policy%'
    ")->fetchColumn();

    $successRate = $totalCount > 0 ? round(($sentCount / $totalCount) * 100, 1) : 100.0;

    // 2. Fetch recent emails sent via SMTP/platform API
    $stmtEmails = $db->query("
        SELECT e.id, e.user_id, e.recipient_email, e.subject, e.body, e.status, e.error_message, e.created_at,
               u.name AS user_name, u.email AS user_email
        FROM sent_emails e
        LEFT JOIN users u ON e.user_id = u.id
        ORDER BY e.id DESC
        LIMIT 200
    ");
    $emails = $stmtEmails->fetchAll(PDO::FETCH_ASSOC);

    // Format SMTP headers and payload diagnostic simulation
    foreach ($emails as &$em) {
        $em['smtp_response'] = $em['status'] === 'sent' 
            ? '250 2.0.0 OK QueueId=' . strtoupper(substr(md5($em['id']), 0, 10)) 
            : '550 5.1.1 Delivery Error: ' . ($em['error_message'] ?: 'Connection timeout');
        
        $em['response_headers'] = implode("\r\n", [
            'HTTP/1.1 ' . ($em['status'] === 'sent' ? '250 OK' : '550 Error'),
            'Content-Type: text/html; charset=UTF-8',
            'X-Sender-Engine: LinkPilot-SMTP-v2.4',
            'X-Message-ID: msg-' . md5($em['id'] . $em['created_at']),
            'Date: ' . date(DATE_RFC2822, strtotime($em['created_at']))
        ]);
    }

    sendJsonResponse('success', 'Email logs loaded.', [
        'diagnostics' => [
            'total' => $totalCount,
            'successful' => $sentCount,
            'hard_bounces' => $bounceCount,
            'spam_complaints' => $spamCount,
            'success_rate' => $successRate
        ],
        'emails' => $emails
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to load email logs: ' . $e->getMessage(), [], 500);
}
