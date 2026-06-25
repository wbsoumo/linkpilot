<?php
// backend/api/generate/send_email.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../smtp_helper.php';
require_once __DIR__ . '/../../google_sheets_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$recipientEmail = trim($input['recipient_email'] ?? '');
$subject = trim($input['subject'] ?? '');
$body = trim($input['body'] ?? '');

if (empty($recipientEmail) || empty($subject) || empty($body)) {
    sendJsonResponse('error', 'Recipient email, subject, and body are required to send email.', [], 400);
}

if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'Invalid recipient email address.', [], 400);
}

try {
    // Send email using user SMTP settings
    $result = SMTPHelper::sendEmail($userId, $recipientEmail, $subject, $body);
    
    if ($result['status']) {
        // Find lead by recipient email
        $db = Database::getConnection();
        $stmtFind = $db->prepare("SELECT id FROM lead_vault WHERE user_id = ? AND email = ? ORDER BY id DESC LIMIT 1");
        $stmtFind->execute([$userId, $recipientEmail]);
        $lead = $stmtFind->fetch();
        
        if ($lead) {
            $leadId = $lead['id'];
            $emailFullText = "Subject: $subject\n\n$body";
            
            $stmtUpdate = $db->prepare("
                UPDATE lead_vault 
                SET current_status = 'Contacted', last_contact_date = CURRENT_TIMESTAMP, generated_email = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmtUpdate->execute([$emailFullText, $leadId]);
            
            // Trigger Google Sheets Sync
            try {
                GoogleSheetsHelper::syncLead($userId, $leadId);
            } catch (Exception $e) {
                error_log("Google Sheets Auto Sync failed in send_email.php: " . $e->getMessage());
            }
        }
        
        sendJsonResponse('success', $result['message']);
    } else {
        sendJsonResponse('error', $result['message'], [], 400);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error sending email: ' . $e->getMessage(), [], 500);
}
