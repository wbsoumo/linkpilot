<?php
require_once __DIR__ . '/../backend/config.php';

$db = Database::getConnection();

echo "--- 1. WHATSAPP ACCOUNTS ---\n";
$accounts = $db->query("SELECT id, user_id, phone_number_id, phone_number, business_name, status, updated_at FROM whatsapp_accounts")->fetchAll(PDO::FETCH_ASSOC);
print_r($accounts);

echo "\n--- 2. WHATSAPP SETTINGS ---\n";
$settings = $db->query("SELECT * FROM whatsapp_settings")->fetchAll(PDO::FETCH_ASSOC);
print_r($settings);

echo "\n--- 3. USER AI KEYS ---\n";
$keys = $db->query("SELECT id, user_id, provider, masked_key, status, error_message, updated_at FROM user_ai_keys")->fetchAll(PDO::FETCH_ASSOC);
print_r($keys);

echo "\n--- 4. USER CREDITS ---\n";
$credits = $db->query("SELECT user_id, free_credits, purchased_credits, remaining_credits, used_credits FROM user_email_credits")->fetchAll(PDO::FETCH_ASSOC);
print_r($credits);

echo "\n--- 5. RECENT WHATSAPP QUEUE ITEMS (Last 10) ---\n";
$queue = $db->query("SELECT id, user_id, phone_number_id, recipient_number, type, status, attempts, error_message, created_at, updated_at FROM whatsapp_queue ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($queue);

echo "\n--- 6. RECENT WHATSAPP MESSAGES (Last 10) ---\n";
$messages = $db->query("SELECT id, user_id, wa_contact_id, message_id, direction, type, body, status, ai_summary, ai_suggested_reply, created_at FROM whatsapp_messages ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($messages);
