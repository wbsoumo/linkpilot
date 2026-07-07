<?php
require_once __DIR__ . '/../backend/config.php';
header('Content-Type: text/plain');

try {
    $db = Database::getConnection();
    echo "Listing tables:\n";
    $stmt = $db->query("SHOW TABLES");
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
    
    echo "\nDescribing user_ai_keys:\n";
    $stmt = $db->query("DESCRIBE user_ai_keys");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
