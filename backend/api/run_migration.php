<?php
// backend/api/run_migration.php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$db = Database::getConnection();

try {
    // 1. Create scraper_requests_log table
    $db->exec("CREATE TABLE IF NOT EXISTS `scraper_requests_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `linkedin_url` VARCHAR(500) NOT NULL,
        `scraping_duration` DECIMAL(6, 3) DEFAULT NULL,
        `company_found` VARCHAR(255) DEFAULT NULL,
        `hunter_lookup_status` VARCHAR(50) DEFAULT NULL,
        `credits_consumed` INT DEFAULT 0,
        `errors` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // 2. Insert or update linkedin_scraper settings
    $stmtScrapCount = $db->prepare("SELECT COUNT(*) FROM `email_provider_settings` WHERE provider_name = ?");
    $stmtScrapCount->execute(['linkedin_scraper']);
    if ((int)$stmtScrapCount->fetchColumn() === 0) {
        $stmtScrapSeed = $db->prepare("INSERT INTO `email_provider_settings` (provider_name, is_enabled, api_key, api_secret, priority) VALUES (?, ?, ?, ?, ?)");
        $stmtScrapSeed->execute(['linkedin_scraper', 1, 'http://127.0.0.1:8001', '15', 10]);
    } else {
        $db->exec("UPDATE `email_provider_settings` SET is_enabled = 1, api_key = 'http://127.0.0.1:8001' WHERE provider_name = 'linkedin_scraper'");
    }

    echo json_encode(["status" => "success", "message" => "Database migrations executed successfully!"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
