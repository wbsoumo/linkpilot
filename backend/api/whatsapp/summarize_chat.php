<?php
// backend/api/whatsapp/summarize_chat.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $contactId = isset($input['contact_id']) ? (int)$input['contact_id'] : 0;

    if ($contactId <= 0) {
        sendJsonResponse('error', 'Invalid contact ID specified.', [], 400);
    }

    // Verify contact ownership
    $stmtCheck = $db->prepare("SELECT id, profile_name, contact_id FROM whatsapp_contacts WHERE id = ? AND user_id = ? LIMIT 1");
    $stmtCheck->execute([$contactId, $userId]);
    $contact = $stmtCheck->fetch();

    if (!$contact) {
        sendJsonResponse('error', 'Contact not found or access denied.', [], 404);
    }

    $profileName = $contact['profile_name'] ?: 'Customer';

    // Fetch conversation history (last 200 messages)
    $stmtMsg = $db->prepare("SELECT direction, body, created_at FROM whatsapp_messages WHERE wa_contact_id = ? ORDER BY created_at ASC LIMIT 200");
    $stmtMsg->execute([$contactId]);
    $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

    if (count($messages) < 1) {
        sendJsonResponse('error', 'No messages found in this chat to summarize.', [], 400);
    }

    // Format chat script
    $script = "";
    foreach ($messages as $m) {
        $sender = ($m['direction'] === 'inbound') ? $profileName : "You (AI)";
        $script .= "[" . $m['created_at'] . "] " . $sender . ": " . $m['body'] . "\n";
    }

    // System Prompt for memory compression
    $systemPrompt = "You are a professional CRM assistant. Compress and summarize the following WhatsApp conversation history between our representative and the customer into a single, high-density paragraph (maximum 150 words). Focus strictly on customer needs, budget, scheduled follow-ups, and relationship state. Do not include introductory text, headers, or XML tags.";
    
    // Call AI helper
    $aiResult = callAI($systemPrompt, $script, $userId);
    $newSummary = trim($aiResult['text'] ?? '');

    if (empty($newSummary)) {
        sendJsonResponse('error', 'AI summarizer returned an empty response. Please try again.', [], 500);
    }

    // Update summary in database
    $stmtUpdate = $db->prepare("UPDATE whatsapp_contacts SET chat_summary = ? WHERE id = ?");
    $stmtUpdate->execute([$newSummary, $contactId]);

    // Optional: Log timeline activity
    if ($contact['contact_id']) {
        $stmtLog = $db->prepare("INSERT INTO crm_timeline (user_id, contact_id, activity_type, description) VALUES (?, ?, 'AI Summary Updated', ?)");
        $stmtLog->execute([$userId, $contact['contact_id'], "AI optimized and compiled conversation summary."]);
    }

    sendJsonResponse('success', 'Conversation history compressed and optimized successfully.', [
        'chat_summary' => $newSummary
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Summarizer error: ' . $e->getMessage(), [], 500);
}
