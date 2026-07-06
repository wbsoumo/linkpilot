<?php
// backend/api/crm/chat_assistant.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Validate Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Read POST payload
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$message = trim($input['message'] ?? '');
$history = $input['history'] ?? []; // Array of {role: 'user'|'assistant', content: '...'}

if (empty($message)) {
    sendJsonResponse('error', 'Message cannot be empty', [], 400);
}

try {
    // 1. Fetch Today's Meetings
    $stmtMeetings = $db->prepare("
        SELECT title, description, start_time, location 
        FROM crm_meetings 
        WHERE user_id = ? AND DATE(start_time) = CURDATE() 
        ORDER BY start_time ASC
    ");
    $stmtMeetings->execute([$userId]);
    $meetings = $stmtMeetings->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Pending/Active Tasks
    $stmtTasks = $db->prepare("
        SELECT title, description, status, due_date, priority 
        FROM crm_tasks 
        WHERE user_id = ? AND status != 'Completed' 
        ORDER BY due_date ASC 
        LIMIT 10
    ");
    $stmtTasks->execute([$userId]);
    $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Recent CRM Leads
    $stmtLeads = $db->prepare("
        SELECT name, company, email, phone, budget, stage, priority 
        FROM crm_leads 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 15
    ");
    $stmtLeads->execute([$userId]);
    $leads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Recent Non-Spam/Non-Archived Inbox Emails
    $stmtEmails = $db->prepare("
        SELECT sender_name, sender_email, subject, ai_summary, received_date, category 
        FROM received_emails 
        WHERE user_id = ? AND is_spam = 0 AND is_archived = 0 AND parent_id IS NULL 
        ORDER BY received_date DESC 
        LIMIT 10
    ");
    $stmtEmails->execute([$userId]);
    $emails = $stmtEmails->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch Recent Invoices
    $stmtInvoices = $db->prepare("
        SELECT sender_name, sender_email, subject, received_date, extracted_data_json 
        FROM received_emails 
        WHERE user_id = ? AND category = 'Invoice' 
        ORDER BY received_date DESC 
        LIMIT 15
    ");
    $stmtInvoices->execute([$userId]);
    $invoices = $stmtInvoices->fetchAll(PDO::FETCH_ASSOC);

    $invoicesCtx = [];
    foreach ($invoices as $inv) {
        $meta = json_decode($inv['extracted_data_json'], true) ?: [];
        $amount = (float)($meta['budget'] ?? 0.00);
        $dueDate = !empty($meta['deadline']) ? $meta['deadline'] : 'Not specified';
        $company = !empty($meta['company_name']) ? $meta['company_name'] : $inv['sender_name'];
        $invoicesCtx[] = "- *Company*: " . $company . " | *Amount*: ₹" . number_format($amount, 2) . " | *Due Date*: " . $dueDate . " | *Subject*: " . $inv['subject'] . " | *Received*: " . $inv['received_date'] . " | *Summary*: " . ($meta['requirement'] ?? 'None');
    }

    // Construct Context Prompt
    $systemPrompt = "You are the LinkPilot AI CRM Chat Assistant. You are here to help the user manage their CRM account, meetings, tasks, invoices, and cold outreach.
Below is the real-time context of the user's LinkPilot CRM account (User Name: " . $user['name'] . ", User Email: " . $user['email'] . "):

---
## MEETINGS SCHEDULED FOR TODAY (" . date('d M Y') . "):
" . (count($meetings) === 0 ? "No meetings scheduled for today." : implode("\n", array_map(function($m) {
    return "- *Title*: " . $m['title'] . " | *Time*: " . $m['start_time'] . " | *Details*: " . $m['description'] . " | *Location/Link*: " . ($m['location'] ?: 'None');
}, $meetings))) . "

---
## OUTSTANDING / PENDING TASKS:
" . (count($tasks) === 0 ? "No outstanding tasks." : implode("\n", array_map(function($t) {
    return "- *Task*: " . $t['title'] . " | *Due*: " . $t['due_date'] . " | *Priority*: " . $t['priority'] . " | *Status*: " . $t['status'];
}, $tasks))) . "

---
## RECENT PIPELINE LEADS:
" . (count($leads) === 0 ? "No leads in pipeline." : implode("\n", array_map(function($l) {
    return "- *Lead Name*: " . $l['name'] . " | *Company*: " . $l['company'] . " | *Budget*: ₹" . number_format($l['budget']) . " | *Stage*: " . $l['stage'] . " | *Priority*: " . $l['priority'] . " | *Email*: " . $l['email'] . " | *Phone*: " . $l['phone'];
}, $leads))) . "

---
## RECENT INBOX EMAILS:
" . (count($emails) === 0 ? "No recent emails." : implode("\n", array_map(function($e) {
    return "- *From*: " . $e['sender_name'] . " <" . $e['sender_email'] . "> | *Subject*: " . $e['subject'] . " | *Category*: " . $e['category'] . " | *AI Summary*: " . $e['ai_summary'] . " | *Received*: " . $e['received_date'];
}, $emails))) . "

---
## INVOICES RECEIVED:
" . (count($invoicesCtx) === 0 ? "No invoices found in database." : implode("\n", $invoicesCtx)) . "

---
Based on this context, answer the user's question point-by-point. Be concise, extremely accurate, and professional. 

Formatting instructions:
1. Use markdown formatting (bolding, bullet points, headers, or tables) to make answers clean.
2. If the user asks about invoices, check the invoices list above and format the details in a markdown table with headers:
   | Sender/Company | Subject | Amount | Due Date | Summary/Details |
   |---|---|---|---|---|
3. Always present currency values using Rupee sign (₹) or the currency of the invoice.
4. If the user asks something outside this data (e.g. general knowledge or personal info not in CRM), answer politely that you only have access to their CRM workspace context.";

    // Compile Conversation History into the Prompt
    $promptBody = "";
    if (count($history) > 0) {
        $promptBody .= "Here is our conversation history:\n";
        foreach ($history as $h) {
            $roleName = ($h['role'] === 'user') ? 'User' : 'Assistant';
            $promptBody .= "$roleName: " . $h['content'] . "\n";
        }
        $promptBody .= "\n";
    }
    $promptBody .= "Current User Query: $message";

    // Call AI Engine
    $aiResult = callAI($systemPrompt, $promptBody, $userId);
    
    sendJsonResponse('success', 'AI response generated', [
        'reply' => $aiResult['text']
    ]);

} catch (Throwable $e) {
    sendJsonResponse('error', 'AI Assistant failed: ' . $e->getMessage(), [], 200);
}
