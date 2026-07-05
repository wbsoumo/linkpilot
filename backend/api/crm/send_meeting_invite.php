<?php
// backend/api/crm/send_meeting_invite.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../libs/PHPMailer/SMTP.php';
require_once __DIR__ . '/../../libs/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    $meetLink = isset($input['meet_link']) ? trim($input['meet_link']) : '';
    $inviteesRaw = isset($input['invitees']) ? trim($input['invitees']) : '';

    if ($taskId <= 0) {
        sendJsonResponse('error', 'Task ID is required.');
    }

    // 1. Fetch task details
    $stmtTask = $db->prepare("SELECT id, title, description, due_date, due_time FROM crm_tasks WHERE id = ? AND user_id = ?");
    $stmtTask->execute([$taskId, $userId]);
    $task = $stmtTask->fetch();

    if (!$task) {
        sendJsonResponse('error', 'Task not found or access denied.', [], 404);
    }

    // Ensure title has [Meeting] prefix
    $title = $task['title'];
    if (!str_contains($title, '[Meeting]')) {
        if (str_starts_with($title, '[')) {
            // Replace existing prefix
            $title = preg_replace('/^\[.*?\]\s*/', '[Meeting] ', $title);
        } else {
            $title = '[Meeting] ' . $title;
        }
    }

    // 2. Update task details in DB
    $stmtUpdate = $db->prepare("UPDATE crm_tasks SET meet_link = ?, title = ? WHERE id = ? AND user_id = ?");
    $stmtUpdate->execute([$meetLink, $title, $taskId, $userId]);

    // Parse invitees list
    $inviteeEmails = [];
    if (!empty($inviteesRaw)) {
        $parts = explode(',', $inviteesRaw);
        foreach ($parts as $p) {
            $e = filter_var(trim($p), FILTER_VALIDATE_EMAIL);
            if ($e) {
                $inviteeEmails[] = $e;
            }
        }
    }

    if (count($inviteeEmails) === 0) {
        sendJsonResponse('success', 'Meeting link updated successfully. No invitees provided to email.');
    }

    // 3. Generate invite.ics
    $cleanTitle = str_replace('[Meeting] ', '', $title);
    $dateStr = $task['due_date'] ?: date('Y-m-d');
    $timeStr = $task['due_time'] ?: '12:00:00';
    
    $startDateTime = str_replace('-', '', $dateStr) . 'T' . str_replace(':', '', $timeStr);
    // End time is start + 1 hour
    $endTimeVal = strtotime("$dateStr $timeStr") + 3600;
    $endDateTime = date('Ymd\THis', $endTimeVal);
    
    $uid = uniqid() . '@linkpilot.ai';
    $createdStamp = date('Ymd\THis\Z');

    $icsContent = "BEGIN:VCALENDAR\r\n";
    $icsContent .= "VERSION:2.0\r\n";
    $icsContent .= "PRODID:-//LinkPilot//CRM//EN\r\n";
    $icsContent .= "METHOD:REQUEST\r\n";
    $icsContent .= "BEGIN:VEVENT\r\n";
    $icsContent .= "UID:{$uid}\r\n";
    $icsContent .= "DTSTAMP:{$createdStamp}\r\n";
    $icsContent .= "DTSTART:{$startDateTime}\r\n";
    $icsContent .= "DTEND:{$endDateTime}\r\n";
    $icsContent .= "SUMMARY:{$cleanTitle}\r\n";
    $icsContent .= "DESCRIPTION:{$task['description']}\\n\\nJoin meeting here: {$meetLink}\r\n";
    $icsContent .= "LOCATION:{$meetLink}\r\n";
    $icsContent .= "STATUS:CONFIRMED\r\n";
    $icsContent .= "SEQUENCE:0\r\n";
    
    foreach ($inviteeEmails as $email) {
        $icsContent .= "ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:{$email}\r\n";
    }
    
    $icsContent .= "END:VEVENT\r\n";
    $icsContent .= "END:VCALENDAR\r\n";

    // 4. Fetch SMTP credentials
    $stmtSmtp = $db->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption FROM imap_smtp_configurations WHERE user_id = ?");
    $stmtSmtp->execute([$userId]);
    $smtp = $stmtSmtp->fetch();

    $mail = new PHPMailer(true);

    try {
        if ($smtp && !empty($smtp['smtp_host'])) {
            // Configure SMTP
            $mail->isSMTP();
            $mail->Host = $smtp['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['smtp_username'];
            $mail->Password = decryptData($smtp['smtp_password']);
            $mail->Port = (int)$smtp['smtp_port'];
            $mail->SMTPSecure = strtolower($smtp['smtp_encryption']) === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            // Fallback to PHP mail() SAPI SENDER
            $mail->isMail();
        }

        // Sender details
        $mail->setFrom($smtp['smtp_username'] ?? 'noreply@linkpilot.ai', 'LinkPilot AI CRM');
        $mail->Subject = "Meeting Invite: " . $cleanTitle;
        
        $mail->isHTML(true);
        $mailBody = "
            <div style='font-family: sans-serif; padding: 20px; color: #1e293b;'>
                <h2 style='color: #4f46e5;'>Meeting Scheduled</h2>
                <p>Hello,</p>
                <p>You have been invited to a meeting scheduled through LinkPilot CRM.</p>
                <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
                    <tr>
                        <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #e2e8f0; width: 120px;'>Subject:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #e2e8f0;'>{$cleanTitle}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #e2e8f0;'>Date & Time:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #e2e8f0;'>{$dateStr} @ " . substr($timeStr, 0, 5) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #e2e8f0;'>Meeting Link:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #e2e8f0;'><a href='{$meetLink}' style='color: #4f46e5; font-weight: bold;'>Join Google Meet</a></td>
                    </tr>
                </table>
                <p style='color: #64748b; font-size: 12px;'>An <b>invite.ics</b> file is attached to this email. You can add it directly to your Google Calendar, Outlook, or Apple Calendar in one click.</p>
            </div>
        ";
        $mail->Body = $mailBody;

        // Add attendees as recipients
        foreach ($inviteeEmails as $email) {
            $mail->addAddress($email);
        }

        // Attach invite.ics
        $mail->addStringAttachment($icsContent, 'invite.ics', 'base64', 'text/calendar; method=REQUEST');

        $mail->send();

        sendJsonResponse('success', 'Meeting link configured and calendar invites sent successfully with invite.ics!');
    } catch (\Exception $e) {
        // SMTP failed, attempt local mail SAPI fallback
        if ($smtp && !empty($smtp['smtp_host'])) {
            try {
                $fallbackMail = new PHPMailer(true);
                $fallbackMail->isMail();
                $fallbackMail->setFrom('noreply@linkpilot.ai', 'LinkPilot AI CRM');
                $fallbackMail->Subject = "Meeting Invite: " . $cleanTitle;
                $fallbackMail->isHTML(true);
                $fallbackMail->Body = $mailBody;
                foreach ($inviteeEmails as $email) {
                    $fallbackMail->addAddress($email);
                }
                $fallbackMail->addStringAttachment($icsContent, 'invite.ics', 'base64', 'text/calendar; method=REQUEST');
                $fallbackMail->send();
                sendJsonResponse('success', 'Meeting link updated. SMTP failed, but invite was sent successfully via fallback mailer!');
                exit;
            } catch (\Exception $fallbackEx) {
                // Both failed
            }
        }
        sendJsonResponse('success', 'Meeting link saved. Note: Calendar email invitation could not be sent (SMTP error: ' . $e->getMessage() . ').');
    }
} catch (\Exception $e) {
    sendJsonResponse('error', 'Meeting invite operation failed: ' . $e->getMessage(), [], 500);
}
