<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: text/plain');

try {
    $db = Database::getConnection();

    echo "=== WHATSAPP SETTINGS ===\n";
    $settings = $db->query("SELECT * FROM whatsapp_settings")->fetchAll(PDO::FETCH_ASSOC);
    print_r($settings);

    echo "\n=== LAST 5 MESSAGES ===\n";
    $msgs = $db->query("SELECT id, wa_contact_id, direction, body, status, error_message, created_at FROM whatsapp_messages ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($msgs);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
