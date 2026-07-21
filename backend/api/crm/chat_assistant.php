<?php
// backend/api/crm/chat_assistant.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../wallet_helper.php';
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

    // 5. Fetch Recent Invoices (matching category or keywords, excluding spam)
    $stmtInvoices = $db->prepare("
        SELECT sender_name, sender_email, subject, received_date, body_text, extracted_data_json 
        FROM received_emails 
        WHERE user_id = ? 
          AND is_spam = 0 
          AND (category = 'Invoice' OR subject LIKE '%invoice%' OR subject LIKE '%receipt%') 
        ORDER BY received_date DESC 
        LIMIT 15
    ");
    $stmtInvoices->execute([$userId]);
    $invoices = $stmtInvoices->fetchAll(PDO::FETCH_ASSOC);

    $invoicesCtx = [];
    foreach ($invoices as $inv) {
        $meta = json_decode($inv['extracted_data_json'], true) ?: [];
        
        $body = $inv['body_text'] ?: '';
        $amount = (float)($meta['budget'] ?? 0.00);
        if ($amount === 0.0) {
            // Regex match for balance due / amount / INR
            if (preg_match('/Balance\s+Due:\s*[^0-9]*([0-9.,]+)/i', $body, $m)) {
                $amount = (float)str_replace(',', '', $m[1]);
            } elseif (preg_match('/Amount(?:\s+Due)?:\s*[^0-9]*([0-9.,]+)/i', $body, $m)) {
                $amount = (float)str_replace(',', '', $m[1]);
            }
        }
        
        $dueDate = !empty($meta['deadline']) ? $meta['deadline'] : '';
        if (empty($dueDate) || $dueDate === 'Not specified') {
            if (preg_match('/Due\s+Date:\s*([^\r\n]+)/i', $body, $m)) {
                $dueDate = trim($m[1]);
            }
        }
        if (empty($dueDate)) {
            $dueDate = 'Not specified';
        }
        
        $company = !empty($meta['company_name']) ? $meta['company_name'] : $inv['sender_name'];
        $bodySnippet = cleanEmailTextForAI($body, 350);
        
        $invoicesCtx[] = "- *Company*: " . $company . " | *Amount*: ₹" . number_format($amount, 2) . " | *Due Date*: " . $dueDate . " | *Subject*: " . $inv['subject'] . " | *Received*: " . $inv['received_date'] . " | *Raw Snippet*: " . $bodySnippet;
    }

    // 6. Fetch Recent WhatsApp Threads / Contacts
    $stmtWaContacts = $db->prepare("
        SELECT profile_name, wa_id, unread_count, last_message_at 
        FROM whatsapp_contacts 
        WHERE user_id = ? 
        ORDER BY last_message_at DESC 
        LIMIT 10
    ");
    $stmtWaContacts->execute([$userId]);
    $waContacts = $stmtWaContacts->fetchAll(PDO::FETCH_ASSOC);

    $waChatsCtx = [];
    foreach ($waContacts as $wc) {
        $waChatsCtx[] = "- *Contact*: " . $wc['profile_name'] . " (+" . $wc['wa_id'] . ") | *Unread Messages*: " . $wc['unread_count'] . " | *Last Active*: " . $wc['last_message_at'];
    }

    // 7. Fetch Recent WhatsApp Messages
    $stmtWaMessages = $db->prepare("
        SELECT c.profile_name, c.wa_id, m.direction, m.body, m.type, m.created_at
        FROM whatsapp_messages m
        JOIN whatsapp_contacts c ON m.wa_contact_id = c.id
        WHERE m.user_id = ?
        ORDER BY m.created_at DESC
        LIMIT 25
    ");
    $stmtWaMessages->execute([$userId]);
    $waMessages = $stmtWaMessages->fetchAll(PDO::FETCH_ASSOC);

    $waMsgsCtx = [];
    foreach ($waMessages as $wm) {
        $sender = ($wm['direction'] === 'inbound') ? $wm['profile_name'] : 'You';
        $bodyVal = trim($wm['body'] ?? '');
        if (empty($bodyVal) && $wm['type'] !== 'text') {
            $bodyVal = "[" . ucfirst($wm['type'] ?? 'Media') . " Media]";
        }
        $waMsgsCtx[] = "- [" . $wm['created_at'] . "] " . $sender . " (+" . $wm['wa_id'] . "): " . $bodyVal;
    }

    // Construct Context Prompt
    $systemPrompt = "You are the LinkPilot AI CRM Chat Assistant. You are here to help the user manage their CRM account, meetings, tasks, invoices, cold outreach, and WhatsApp conversations.
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
## RECENT WHATSAPP CHATS & UNREAD COUNTS:
" . (count($waContacts) === 0 ? "No active WhatsApp chats found." : implode("\n", $waChatsCtx)) . "

---
## LATEST WHATSAPP CHAT TRANSCRIPTS:
" . (count($waMsgsCtx) === 0 ? "No recent WhatsApp messages found in logs." : implode("\n", $waMsgsCtx)) . "

---
Based on this context, answer the user's question point-by-point. Be concise, extremely accurate, and professional.\n\n

DATABASE WRITE / CREATE CAPABILITIES:
You can create records directly in the user's CRM database. If the user asks you to add, write, schedule, or create a task, lead, contact, or company, you must execute the database action by appending the exact JSON block at the very end of your response:

||ACTION_START||
{
  \"action\": \"create_task|create_lead|create_contact|create_company\",
  \"params\": {
     // parameter values
  }
}
||ACTION_END||

Action Schema Details:
1. create_task:
   - \"title\": string (required)
   - \"description\": string (optional)
   - \"due_date\": string in YYYY-MM-DD format (required, default to today if not specified)
   - \"priority\": \"high\"|\"medium\"|\"low\" (optional, default \"medium\")
   
2. create_lead:
   - \"name\": string (required)
   - \"company\": string (optional)
   - \"email\": string (optional)
   - \"phone\": string (optional)
   - \"budget\": number (optional)
   - \"requirements\": string (optional)
   - \"priority\": \"high\"|\"medium\"|\"low\" (optional, default \"medium\")
   - \"stage\": \"New\"|\"Contacted\" (optional, default \"New\")

3. create_contact:
   - \"name\": string (required)
   - \"email\": string (optional)
   - \"phone\": string (optional)
   - \"designation\": string (optional)
   - \"location\": string (optional)

4. create_company:
   - \"name\": string (required)
   - \"website\": string (optional)
   - \"industry\": string (optional)

Example: If the user says: \"add a task to pay HostBet invoice tomorrow\", you should output:
I've scheduled a task to pay the HostBet invoice.
||ACTION_START||
{
  \"action\": \"create_task\",
  \"params\": {
    \"title\": \"Pay HostBet Invoice\",
    \"due_date\": \"" . date('Y-m-d', strtotime('+1 day')) . "\",
    \"priority\": \"high\",
    \"description\": \"Invoice payment for HostBet\"
  }
}
||ACTION_END||

Formatting instructions:
1. Use markdown formatting (bolding, bullet points, headers, or tables) to make answers clean.
2. If the user asks about invoices, check the invoices list above and format the details in a markdown table with headers:
   | Sender/Company | Subject | Amount | Due Date | Summary/Details |
   |---|---|---|---|---|
3. Always present currency values using Rupee sign (₹) or the currency of the invoice.
4. If the user asks something outside this data (e.g. general knowledge or personal info not in CRM), answer politely that you only have access to their CRM workspace context.";

    // Fetch liked responses for reinforcement training
    try {
        $stmtLearned = $db->prepare("SELECT question, answer FROM ai_chat_feedback WHERE user_id = ? AND feedback_type = 'like' ORDER BY id DESC LIMIT 5");
        $stmtLearned->execute([$userId]);
        $learnedPairs = $stmtLearned->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($learnedPairs)) {
            $systemPrompt .= "\n\n=== AI RESPONSE TRAINING (FOLLOW THESE PAST LIKED ANSWERS FOR SIMILAR USER QUESTIONS) ===\n";
            foreach ($learnedPairs as $pair) {
                $systemPrompt .= "User Query: " . $pair['question'] . "\n";
                $systemPrompt .= "Learned AI Response: " . $pair['answer'] . "\n\n";
            }
            $systemPrompt .= "========================================================================================\n";
        }
    } catch (Exception $e) {}

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
    $replyText = $aiResult['text'];

    // Check if there is an action block to execute
    $actionPerformedMessage = "";
    if (preg_match('/\|\|ACTION_START\|\|(.*?)\|\|ACTION_END\|\|/s', $replyText, $matches)) {
        $actionJson = trim($matches[1]);
        $actionData = json_decode($actionJson, true);
        
        if ($actionData && !empty($actionData['action']) && !empty($actionData['params'])) {
            $actionType = $actionData['action'];
            $params = $actionData['params'];
            
            $db->beginTransaction();
            try {
                if ($actionType === 'create_task') {
                    $title = trim($params['title'] ?? '');
                    if (!empty($title)) {
                        $desc = trim($params['description'] ?? '');
                        $dueDate = trim($params['due_date'] ?? date('Y-m-d'));
                        $priority = trim($params['priority'] ?? 'medium');
                        
                        $stmt = $db->prepare("INSERT INTO crm_tasks (user_id, title, description, due_date, status, priority) VALUES (?, ?, ?, ?, 'pending', ?)");
                        $stmt->execute([$userId, $title, $desc, $dueDate, $priority]);
                        
                        $actionPerformedMessage = "\n\n🚀 **System Note**: Successfully created task: **$title** due on **$dueDate**.";
                    }
                } elseif ($actionType === 'create_lead') {
                    $name = trim($params['name'] ?? '');
                    if (!empty($name)) {
                        $company = trim($params['company'] ?? '');
                        $email = strtolower(trim($params['email'] ?? ''));
                        $phone = trim($params['phone'] ?? '');
                        $budget = (float)($params['budget'] ?? 0.00);
                        $reqs = trim($params['requirements'] ?? '');
                        $priority = trim($params['priority'] ?? 'medium');
                        $stage = trim($params['stage'] ?? 'New');
                        
                        $stmt = $db->prepare("INSERT INTO crm_leads (user_id, name, company, email, phone, budget, requirements, priority, stage, lead_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'AI Assistant')");
                        $stmt->execute([$userId, $name, $company, $email, $phone, $budget, $reqs, $priority, $stage]);
                        
                        $actionPerformedMessage = "\n\n🚀 **System Note**: Successfully added lead: **$name** (" . ($company ?: 'No company') . ") with budget **₹" . number_format($budget, 2) . "**.";
                    }
                } elseif ($actionType === 'create_contact') {
                    $name = trim($params['name'] ?? '');
                    if (!empty($name)) {
                        $email = strtolower(trim($params['email'] ?? ''));
                        $phone = trim($params['phone'] ?? '');
                        $designation = trim($params['designation'] ?? '');
                        $location = trim($params['location'] ?? '');
                        
                        $stmt = $db->prepare("INSERT INTO crm_contacts (user_id, name, email, phone, designation, location) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$userId, $name, $email, $phone, $designation, $location]);
                        
                        $actionPerformedMessage = "\n\n🚀 **System Note**: Successfully added contact profile: **$name** (" . ($email ?: 'No email') . ").";
                    }
                } elseif ($actionType === 'create_company') {
                    $name = trim($params['name'] ?? '');
                    if (!empty($name)) {
                        $website = trim($params['website'] ?? '');
                        $industry = trim($params['industry'] ?? '');
                        
                        $stmt = $db->prepare("INSERT INTO crm_companies (user_id, name, website, industry, status, source) VALUES (?, ?, ?, ?, 'Active', 'AI Assistant')");
                        $stmt->execute([$userId, $name, $website, $industry]);
                        
                        $actionPerformedMessage = "\n\n🚀 **System Note**: Successfully cataloged company: **$name**.";
                    }
                }
                $db->commit();
            } catch (Throwable $trxError) {
                $db->rollBack();
                $actionPerformedMessage = "\n\n⚠️ **System Error**: Failed to execute action automatically: " . $trxError->getMessage();
            }
        }
        
        // Strip the action block from the reply sent to the user
        $replyText = preg_replace('/\|\|ACTION_START\|\|(.*?)\|\|ACTION_END\|\|/s', '', $replyText);
    }
    
    sendJsonResponse('success', 'AI response generated', [
        'reply' => trim($replyText) . $actionPerformedMessage
    ]);

} catch (Throwable $e) {
    sendJsonResponse('error', 'AI Assistant failed: ' . $e->getMessage(), [], 200);
}

function cleanEmailTextForAI($text, $maxLength = 300) {
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength) . '...';
    }
    return $text;
}
