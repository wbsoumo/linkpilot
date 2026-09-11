<?php
// backend/api/crm/copilot_action.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../crm_sync_helper.php';
require_once __DIR__ . '/../../smtp_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';
require_once __DIR__ . '/../../wallet_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];
$db = Database::getConnection();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: [];

$action = $input['action'] ?? ($_GET['action'] ?? 'parse');

try {
    if ($action === 'parse') {
        $prompt = trim($input['prompt'] ?? '');
        if (empty($prompt)) {
            sendJsonResponse('error', 'Prompt text cannot be empty.', [], 400);
        }

        // 1. Fetch user workspace contacts for context matching
        $stmtCon = $db->prepare("SELECT id, name, email, phone, whatsapp, company_id FROM crm_contacts WHERE user_id = ? ORDER BY id DESC LIMIT 50");
        $stmtCon->execute([$userId]);
        $contacts = $stmtCon->fetchAll(PDO::FETCH_ASSOC);

        $contactsCtx = "";
        foreach ($contacts as $c) {
            $contactsCtx .= "- Contact ID {$c['id']}: {$c['name']} | Email: {$c['email']} | Phone: {$c['phone']}\n";
        }

        // 2. Fetch user profile/business details
        $stmtUser = $db->prepare("SELECT u.name, u.email, p.company_name FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $userProf = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];
        $senderName = $userProf['name'] ?? 'LinkPilot User';
        $companyName = $userProf['company_name'] ?? 'Our Company';

        $systemPrompt = "You are the LinkPilot AI Omnichannel Action Parser. Your task is to analyze natural language command prompts from $senderName (representing $companyName) and extract the intended workspace action into a strict JSON payload.

Supported action_type options:
1. 'SEND_EMAIL': Draft an email to a contact.
2. 'SEND_WHATSAPP': Draft a WhatsApp message to a contact.
3. 'CREATE_INVOICE': Draft an invoice for a contact/company.
4. 'SCHEDULE_MEETING': Schedule a meeting call and create a task.
5. 'CREATE_TASK': Create a task with due date/time and priority.
6. 'MOVE_DEAL': Update a deal stage (e.g. Lead, Qualified, Proposal, Negotiation, Closed Won, Closed Lost).

Return your response as a valid JSON object ONLY (no markdown fences around it) with this structure:
{
  \"action_type\": \"SEND_EMAIL|SEND_WHATSAPP|CREATE_INVOICE|SCHEDULE_MEETING|CREATE_TASK|MOVE_DEAL\",
  \"target_contact\": {
    \"name\": \"...\",
    \"email\": \"...\",
    \"phone\": \"...\"
  },
  \"email_draft\": {
    \"subject\": \"...\",
    \"body\": \"...\"
  },
  \"whatsapp_draft\": {
    \"message\": \"...\"
  },
  \"invoice_draft\": {
    \"client_name\": \"...\",
    \"client_email\": \"...\",
    \"amount\": 0.00,
    \"currency\": \"INR|USD|EUR|GBP\",
    \"description\": \"...\",
    \"due_date\": \"YYYY-MM-DD\"
  },
  \"task_draft\": {
    \"title\": \"...\",
    \"description\": \"...\",
    \"due_date\": \"YYYY-MM-DD\",
    \"due_time\": \"HH:MM:SS\",
    \"priority\": \"high|medium|low\"
  },
  \"deal_draft\": {
    \"deal_title\": \"...\",
    \"target_stage\": \"Lead|Qualified|Proposal|Negotiation|Closed Won|Closed Lost\"
  },
  \"summary\": \"Brief human-readable summary of the action parsed\"
}

WORKSPACE CONTACTS LIST FOR MATCHING:
" . ($contactsCtx ?: "No contacts found yet.") . "
";

        $userPrompt = "User Command Prompt: \"$prompt\"";

        $ai = callAI($systemPrompt, $userPrompt, $userId);
        $aiData = json_decode($ai['text'], true);

        if (!$aiData || empty($aiData['action_type'])) {
            sendJsonResponse('error', 'AI could not parse command. Please rephrase your request.', [], 422);
        }

        // Match or resolve contact in DB
        $target = $aiData['target_contact'] ?? [];
        $resolvedContact = CRMSyncHelper::resolveContact(
            $userId,
            !empty($target['email']) ? $target['email'] : null,
            !empty($target['phone']) ? $target['phone'] : null,
            !empty($target['name']) ? $target['name'] : null,
            $db
        );

        $aiData['matched_contact'] = $resolvedContact;

        sendJsonResponse('success', 'Command parsed successfully into actionable draft.', [
            'parsed' => $aiData
        ]);
    }

    elseif ($action === 'execute') {
        $actionType = $input['action_type'] ?? '';
        $contactId = (int)($input['contact_id'] ?? 0);
        $summary = $input['summary'] ?? 'Executed AI Co-Pilot action';

        if (empty($actionType)) {
            sendJsonResponse('error', 'Action type is required for execution.', [], 400);
        }

        $resultData = [];

        switch ($actionType) {
            case 'SEND_EMAIL':
                $emailData = $input['email_draft'] ?? [];
                $recipientEmail = strtolower(trim($emailData['recipient_email'] ?? $input['target_contact']['email'] ?? ''));
                $subject = trim($emailData['subject'] ?? 'Message from LinkPilot AI');
                $body = trim($emailData['body'] ?? '');

                if (empty($recipientEmail) || empty($body)) {
                    sendJsonResponse('error', 'Recipient email and message body are required.', [], 400);
                }

                // Check SMTP config
                $smtpConfig = SMTPHelper::getSMTPConfig($userId);
                if (!$smtpConfig) {
                    sendJsonResponse('error', 'SMTP is not configured. Please configure email settings in Setup.', [], 400);
                }

                $sent = SMTPHelper::sendEmail($userId, $recipientEmail, $subject, $body, true);
                if (!$sent['success']) {
                    sendJsonResponse('error', 'Failed to send email: ' . ($sent['message'] ?? 'SMTP Error'), [], 500);
                }

                CRMSyncHelper::logActivity($userId, $contactId, 'email', 'outbound', "Sent Email: \"$subject\"", [
                    'recipient' => $recipientEmail,
                    'subject' => $subject
                ], $db);

                $resultData = ['message' => "Email sent successfully to $recipientEmail"];
                break;

            case 'SEND_WHATSAPP':
                $waData = $input['whatsapp_draft'] ?? [];
                $recipientPhone = trim($waData['recipient_phone'] ?? $input['target_contact']['phone'] ?? '');
                $waMessage = trim($waData['message'] ?? '');

                if (empty($recipientPhone) || empty($waMessage)) {
                    sendJsonResponse('error', 'Recipient phone number and message text are required.', [], 400);
                }

                // Fetch account
                $stmtAcc = $db->prepare("SELECT phone_number_id, access_token FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
                $stmtAcc->execute([$userId]);
                $waAcc = $stmtAcc->fetch();

                if (!$waAcc) {
                    sendJsonResponse('error', 'WhatsApp Cloud API account is not connected.', [], 400);
                }

                $decrypted = decryptData($waAcc['access_token']);
                $token = ($decrypted !== false) ? $decrypted : $waAcc['access_token'];

                $sendRes = WhatsAppMetaService::sendTextMessage($userId, $waAcc['phone_number_id'], $recipientPhone, $waMessage, $token);

                CRMSyncHelper::logActivity($userId, $contactId, 'whatsapp', 'outbound', "Sent WhatsApp message: \"$waMessage\"", [
                    'recipient' => $recipientPhone
                ], $db);

                $resultData = ['message' => "WhatsApp message sent successfully to $recipientPhone"];
                break;

            case 'CREATE_TASK':
            case 'SCHEDULE_MEETING':
                $taskData = $input['task_draft'] ?? [];
                $title = trim($taskData['title'] ?? 'New Co-Pilot Task');
                $desc = trim($taskData['description'] ?? '');
                $dueDate = !empty($taskData['due_date']) ? $taskData['due_date'] : date('Y-m-d');
                $dueTime = !empty($taskData['due_time']) ? $taskData['due_time'] : null;
                $priority = trim($taskData['priority'] ?? 'medium');

                $stmtTask = $db->prepare("INSERT INTO crm_tasks (user_id, contact_id, title, description, due_date, due_time, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmtTask->execute([$userId, $contactId ?: null, $title, $desc, $dueDate, $dueTime, $priority]);
                $taskId = $db->lastInsertId();

                CRMSyncHelper::logActivity($userId, $contactId, 'task', 'system', "Created Task: \"$title\"", [
                    'task_id' => $taskId,
                    'due_date' => $dueDate
                ], $db);

                $resultData = ['message' => "Task '$title' created successfully", 'task_id' => $taskId];
                break;

            case 'CREATE_INVOICE':
                $invData = $input['invoice_draft'] ?? [];
                $clientName = trim($invData['client_name'] ?? $input['target_contact']['name'] ?? 'Client');
                $amount = (float)($invData['amount'] ?? 0.00);
                $currency = trim($invData['currency'] ?? 'INR');
                $desc = trim($invData['description'] ?? 'Services rendered');
                $dueDate = !empty($invData['due_date']) ? $invData['due_date'] : date('Y-m-d', strtotime('+7 days'));

                // Log task & activity for invoice
                $invTitle = "Invoice #INV-" . rand(1000, 9999) . " ($currency " . number_format($amount, 2) . ") for $clientName";
                $stmtInvTask = $db->prepare("INSERT INTO crm_tasks (user_id, contact_id, title, description, due_date, priority, status) VALUES (?, ?, ?, ?, ?, 'high', 'pending')");
                $stmtInvTask->execute([$userId, $contactId ?: null, "[Invoice] $invTitle", $desc, $dueDate]);
                $taskId = $db->lastInsertId();

                CRMSyncHelper::logActivity($userId, $contactId, 'ticket', 'system', "Generated Invoice Draft: \"$invTitle\"", [
                    'amount' => $amount,
                    'currency' => $currency,
                    'due_date' => $dueDate
                ], $db);

                $resultData = ['message' => "Invoice draft created successfully: $invTitle", 'task_id' => $taskId];
                break;

            case 'MOVE_DEAL':
                $dealData = $input['deal_draft'] ?? [];
                $dealTitle = trim($dealData['deal_title'] ?? '');
                $targetStage = trim($dealData['target_stage'] ?? 'Qualified');

                if (!empty($dealTitle)) {
                    $stmtUpdDeal = $db->prepare("UPDATE crm_deals SET stage = ? WHERE user_id = ? AND title LIKE ?");
                    $stmtUpdDeal->execute([$targetStage, $userId, "%$dealTitle%"]);
                } elseif ($contactId > 0) {
                    $stmtUpdDeal = $db->prepare("UPDATE crm_deals SET stage = ? WHERE user_id = ? AND contact_id = ? ORDER BY id DESC LIMIT 1");
                    $stmtUpdDeal->execute([$targetStage, $userId, $contactId]);
                }

                CRMSyncHelper::logActivity($userId, $contactId, 'system', 'system', "Moved Deal stage to '$targetStage'", [
                    'deal_title' => $dealTitle,
                    'stage' => $targetStage
                ], $db);

                $resultData = ['message' => "Deal stage updated to '$targetStage'"];
                break;

            default:
                sendJsonResponse('error', 'Unsupported action type.', [], 400);
        }

        sendJsonResponse('success', 'Co-Pilot action executed successfully.', $resultData);
    } else {
        sendJsonResponse('error', 'Invalid action mode.', [], 400);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Co-Pilot execution failed: ' . $e->getMessage(), [], 500);
}
