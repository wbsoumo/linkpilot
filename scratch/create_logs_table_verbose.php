<?php
require_once __DIR__ . '/../backend/config.php';
header('Content-Type: text/plain');

try {
    $db = Database::getConnection();
    echo "Running query directly...\n";
    $sql = "CREATE TABLE `user_ai_key_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `key_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_user_ai_key_logs_key` FOREIGN KEY (`key_id`) REFERENCES `user_ai_keys` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    echo "SUCCESS!\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
