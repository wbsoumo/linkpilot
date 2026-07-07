<?php
require_once __DIR__ . '/../backend/config.php';
header('Content-Type: text/plain');

try {
    $db = Database::getConnection();
    echo "Running query directly...\n";
    $sql = "CREATE TABLE `user_ai_keys` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `provider` VARCHAR(50) NOT NULL,
        `api_key` TEXT NOT NULL,
        `status` VARCHAR(50) DEFAULT 'active',
        `error_message` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_user_ai_keys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    echo "SUCCESS!\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
