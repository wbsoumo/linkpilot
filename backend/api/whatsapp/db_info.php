<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json');

$output = [];
try {
    $db = Database::getConnection();

    $settings = $db->query("SELECT * FROM whatsapp_settings")->fetchAll(PDO::FETCH_ASSOC);
    $output['settings'] = $settings;

    $msgs = $db->query("SELECT id, wa_contact_id, direction, body, status, error_message, created_at FROM whatsapp_messages ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $output['messages'] = $msgs;
} catch (Throwable $e) {
    $output['error'] = $e->getMessage() . "\n" . $e->getTraceAsString();
}

echo json_encode($output, JSON_PRETTY_PRINT);
