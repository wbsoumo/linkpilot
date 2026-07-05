<?php
// backend/api/crm/email_intelligence/reply.php

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../jwt_helper.php';
require_once __DIR__ . '/../../../smtp_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $emailId = (int)($input['email_id'] ?? 0);
        if ($emailId <= 0) {
            sendJsonResponse('error', 'Received Email ID is required.', [], 400);
        }

        // Fetch original email
        $stmtEmail = $db->prepare("SELECT * FROM received_emails WHERE id = ? AND user_id = ?");
        $stmtEmail->execute([$emailId, $userId]);
        $email = $stmtEmail->fetch();
        if (!$email) {
            sendJsonResponse('error', 'Original email not found or access denied.', [], 404);
        }

        $tone = trim($input['tone'] ?? 'Professional'); // Professional, Friendly, Sales, Support, Proposal, Meeting Confirmation, Custom
        $customInstructions = trim($input['custom_instructions'] ?? '');

        // Formulate prompt for AI reply generator
        $systemPrompt = "You are a professional business communication assistant. Write a reply to the customer's email in the specified tone: '$tone'.
Make sure to keep the response concise, clear, and action-oriented. Do not include placeholder text like [My Name] - instead use the sender details provided if available, otherwise just use standard placeholders that the user can verify.
Only return the text of the email reply body, and nothing else.";

        $userPrompt = "Original Email Sender: {$email['sender_name']} <{$email['sender_email']}>\nSubject: {$email['subject']}\nEmail Content:\n{$email['body_text']}\n\n";
        
        if ($tone === 'Custom' && !empty($customInstructions)) {
            $userPrompt .= "Additional instructions for the reply: $customInstructions\n";
        } else {
            $userPrompt .= "Draft a reply matching the '$tone' style requirements.\n";
        }

        $aiResult = null;
        try {
            $aiResult = callAI($systemPrompt, $userPrompt, $userId);
        } catch (Exception $e) {
            sendJsonResponse('error', 'AI Reply Generation failed: ' . $e->getMessage(), [], 500);
        }

        sendJsonResponse('success', 'AI reply draft generated', [
            'draft' => $aiResult['text'],
            'tokens_used' => $aiResult['tokens']
        ]);
    }
    
    elseif ($method === 'SEND') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $emailId = (int)($input['email_id'] ?? 0);
        $replyBody = trim($input['reply_body'] ?? '');
        $subject = trim($input['subject'] ?? '');

        if ($emailId <= 0 || empty($replyBody) || empty($subject)) {
            sendJsonResponse('error', 'Email ID, Subject, and Reply Body are required.', [], 400);
        }

        // Fetch original email to get recipient details
        $stmtEmail = $db->prepare("SELECT * FROM received_emails WHERE id = ? AND user_id = ?");
        $stmtEmail->execute([$emailId, $userId]);
        $email = $stmtEmail->fetch();
        if (!$email) {
            sendJsonResponse('error', 'Original email not found.', [], 404);
        }

        // Process attachments
        $attachments = [];
        if (!empty($_FILES['attachments'])) {
            $files = $_FILES['attachments'];
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'mp3', 'mp4', 'm4a'];
            
            if (is_array($files['name'])) {
                // Multiple files
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpPath = $files['tmp_name'][$i];
                        $filename = $files['name'][$i];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        if (in_array($ext, $allowedExts)) {
                            $attachments[] = [
                                'path' => $tmpPath,
                                'name' => $filename
                            ];
                        } else {
                            sendJsonResponse('error', "File extension '$ext' is not allowed. Supported formats: pdf, jpg, jpeg, png, webp, mp3, mp4, m4a", [], 400);
                        }
                    }
                }
            } else {
                // Single file
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $tmpPath = $files['tmp_name'];
                    $filename = $files['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowedExts)) {
                        $attachments[] = [
                            'path' => $tmpPath,
                            'name' => $filename
                        ];
                    } else {
                        sendJsonResponse('error', "File extension '$ext' is not allowed. Supported formats: pdf, jpg, jpeg, png, webp, mp3, mp4, m4a", [], 400);
                    }
                }
            }
        }

        // Dispatch email
        $recipient = $email['sender_email'];
        $sendResult = SMTPHelper::sendEmail($userId, $recipient, $subject, $replyBody, $attachments);

        if ($sendResult['status']) {
            // Log interaction to timeline
            $db->prepare("INSERT INTO crm_timeline (user_id, company_id, contact_id, activity_type, description) VALUES (?, ?, ?, 'Email Sent', ?)")
               ->execute([
                   $userId, 
                   !empty($email['extracted_data_json']) ? json_decode($email['extracted_data_json'], true)['company_id'] ?? null : null,
                   null, // contact id
                   "Sent reply: '$subject' to '$recipient'."
               ]);

            sendJsonResponse('success', 'Email sent successfully via SMTP!');
        } else {
            sendJsonResponse('error', 'Failed to dispatch email: ' . $sendResult['message']);
        }
    } 
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Reply operation failed: ' . $e->getMessage(), [], 500);
}
