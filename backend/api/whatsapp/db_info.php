<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json');

$output = [];
try {
    $db = Database::getConnection();

    $contact = $db->query("SELECT * FROM whatsapp_contacts WHERE id = 7")->fetch(PDO::FETCH_ASSOC);
    $output['contact'] = $contact;

    if ($contact) {
        $settings = $db->query("SELECT * FROM whatsapp_settings WHERE user_id = " . (int)$contact['user_id'])->fetchAll(PDO::FETCH_ASSOC);
        $output['settings'] = $settings;
    }
} catch (Throwable $e) {
    $output['error'] = $e->getMessage();
}

echo json_encode($output, JSON_PRETTY_PRINT);
