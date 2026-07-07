<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json');

try {
    $db = Database::getConnection();
    $rows = $db->query("SELECT * FROM whatsapp_messages WHERE id >= 270 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
