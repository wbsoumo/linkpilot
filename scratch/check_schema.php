<?php
require_once __DIR__ . '/../backend/config.php';
$db = Database::getConnection();
try {
    $stmt = $db->query("DESCRIBE whatsapp_accounts");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($fields, JSON_PRETTY_PRINT) . "\n";
} catch(Exception $e) {
    echo $e->getMessage() . "\n";
}
