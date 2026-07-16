<?php
// backend/api/admin/email_logs.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

$db = Database::getConnection();

try {
    // Fetch recent emails sent via SMTP/platform API
    $stmtEmails = $db->query("
        SELECT e.id, e.user_id, e.recipient_email, e.subject, e.body, e.status, e.error_message, e.created_at,
               u.name AS user_name, u.email AS user_email
        FROM sent_emails e
        LEFT JOIN users u ON e.user_id = u.id
        ORDER BY e.id DESC
        LIMIT 200
    ");
    $emails = $stmtEmails->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse('success', 'Email logs loaded.', [
        'emails' => $emails
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to load email logs: ' . $e->getMessage(), [], 500);
}
