<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: text/plain');
$db = Database::getConnection();

echo "=== LAST 10 MESSAGES ===\n";
$stmt = $db->query("SELECT m.*, c.profile_name FROM whatsapp_messages m JOIN whatsapp_contacts c ON m.wa_contact_id = c.id ORDER BY m.id DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== WHATSAPP SETTINGS ===\n";
print_r($db->query("SELECT * FROM whatsapp_settings")->fetchAll(PDO::FETCH_ASSOC));
