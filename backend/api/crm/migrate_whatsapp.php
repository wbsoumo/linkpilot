<?php
// backend/api/crm/migrate_whatsapp.php

require_once __DIR__ . '/../../config.php';

// If called directly, set json headers
$is_direct = (basename($_SERVER['SCRIPT_FILENAME']) === 'migrate_whatsapp.php');
if ($is_direct) {
    header('Content-Type: application/json');
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

$db = Database::getConnection();
$wa_messages = [];

try {
    // 1. whatsapp_accounts
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_accounts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `business_name` VARCHAR(255) DEFAULT NULL,
        `business_id` VARCHAR(100) DEFAULT NULL,
        `waba_id` VARCHAR(100) DEFAULT NULL,
        `phone_number_id` VARCHAR(100) DEFAULT NULL,
        `display_phone_number` VARCHAR(50) DEFAULT NULL,
        `access_token` TEXT DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'disconnected',
        `quality_rating` VARCHAR(50) DEFAULT 'unknown',
        `messaging_limit` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_accounts' checked/created.";

    // 2. whatsapp_contacts
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_contacts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `contact_id` INT DEFAULT NULL,
        `wa_id` VARCHAR(50) NOT NULL,
        `profile_name` VARCHAR(255) DEFAULT NULL,
        `last_message_at` TIMESTAMP NULL DEFAULT NULL,
        `unread_count` INT DEFAULT 0,
        `tags` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_contacts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_wa_contacts_crm` FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
        UNIQUE KEY `idx_wa_contact_unique` (`user_id`, `wa_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_contacts' checked/created.";

    // 3. whatsapp_messages
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `wa_contact_id` INT NOT NULL,
        `message_id` VARCHAR(255) NOT NULL,
        `direction` VARCHAR(20) NOT NULL,
        `type` VARCHAR(50) DEFAULT 'text',
        `body` TEXT DEFAULT NULL,
        `media_url` VARCHAR(500) DEFAULT NULL,
        `media_mime_type` VARCHAR(100) DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'sent',
        `error_message` TEXT DEFAULT NULL,
        `ai_summary` TEXT DEFAULT NULL,
        `ai_suggested_reply` TEXT DEFAULT NULL,
        `sentiment` VARCHAR(50) DEFAULT 'neutral',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_msg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_wa_msg_contact` FOREIGN KEY (`wa_contact_id`) REFERENCES `whatsapp_contacts` (`id`) ON DELETE CASCADE,
        UNIQUE KEY `idx_wa_msg_id` (`message_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_messages' checked/created.";

    // 4. whatsapp_campaigns
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_campaigns` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `template_name` VARCHAR(255) NOT NULL,
        `template_language` VARCHAR(50) DEFAULT 'en',
        `filters_json` TEXT DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'draft',
        `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
        `total_contacts` INT DEFAULT 0,
        `sent_count` INT DEFAULT 0,
        `delivered_count` INT DEFAULT 0,
        `read_count` INT DEFAULT 0,
        `failed_count` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_camp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_campaigns' checked/created.";

    // 5. whatsapp_campaign_logs
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_campaign_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `campaign_id` INT NOT NULL,
        `wa_contact_id` INT NOT NULL,
        `message_id` VARCHAR(255) DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'pending',
        `error_message` TEXT DEFAULT NULL,
        `sent_at` TIMESTAMP NULL DEFAULT NULL,
        CONSTRAINT `fk_wa_camp_log_camp` FOREIGN KEY (`campaign_id`) REFERENCES `whatsapp_campaigns` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_wa_camp_log_contact` FOREIGN KEY (`wa_contact_id`) REFERENCES `whatsapp_contacts` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_campaign_logs' checked/created.";

    // 6. whatsapp_templates
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `category` VARCHAR(100) DEFAULT NULL,
        `language` VARCHAR(50) DEFAULT 'en',
        `status` VARCHAR(50) DEFAULT 'APPROVED',
        `components_json` LONGTEXT DEFAULT NULL,
        `last_sync_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_temp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        UNIQUE KEY `idx_wa_temp_unique` (`user_id`, `name`, `language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_templates' checked/created.";

    // 7. whatsapp_template_sync
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_template_sync` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `waba_id` VARCHAR(100) NOT NULL,
        `last_sync` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_temp_sync_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_template_sync' checked/created.";

    // 8. whatsapp_media
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_media` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `media_id` VARCHAR(255) NOT NULL,
        `mime_type` VARCHAR(100) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `file_size` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_media_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        UNIQUE KEY `idx_wa_media_id` (`media_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_media' checked/created.";

    // 9. whatsapp_webhook_logs
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_webhook_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `payload` LONGTEXT NOT NULL,
        `status` VARCHAR(50) DEFAULT 'unprocessed',
        `error_message` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_webhook_logs' checked/created.";

    // 10. whatsapp_queue
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_queue` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `phone_number_id` VARCHAR(100) NOT NULL,
        `recipient_number` VARCHAR(50) NOT NULL,
        `payload_json` LONGTEXT NOT NULL,
        `type` VARCHAR(50) NOT NULL,
        `status` VARCHAR(50) DEFAULT 'pending',
        `attempts` INT DEFAULT 0,
        `error_message` TEXT DEFAULT NULL,
        `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_queue_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_queue' checked/created.";

    // 11. whatsapp_settings
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNIQUE NOT NULL,
        `max_numbers` INT DEFAULT 1,
        `max_campaign_size` INT DEFAULT 500,
        `max_messages_per_day` INT DEFAULT 1000,
        `ai_enabled` TINYINT(1) DEFAULT 1,
        `auto_crm_creation` TINYINT(1) DEFAULT 1,
        `auto_lead_detection` TINYINT(1) DEFAULT 1,
        `auto_contact_detection` TINYINT(1) DEFAULT 1,
        `auto_company_detection` TINYINT(1) DEFAULT 1,
        `auto_reply_suggestions` TINYINT(1) DEFAULT 1,
        `media_upload_limit_mb` INT DEFAULT 16,
        `allowed_file_types` VARCHAR(255) DEFAULT 'jpg,png,pdf,mp4,mp3,docx',
        `webhook_retry_count` INT DEFAULT 3,
        `queue_processing_interval` INT DEFAULT 60,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_settings' checked/created.";

    // 12. whatsapp_automation_logs
    $db->exec("CREATE TABLE IF NOT EXISTS `whatsapp_automation_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `trigger_event` VARCHAR(100) NOT NULL,
        `workflow_id` INT DEFAULT NULL,
        `action_taken` VARCHAR(255) NOT NULL,
        `status` VARCHAR(50) DEFAULT 'success',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_wa_auto_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $wa_messages[] = "Table 'whatsapp_automation_logs' checked/created.";

    if ($is_direct) {
        sendJsonResponse('success', 'WhatsApp Database Migrations check completed.', ['details' => $wa_messages]);
    }
} catch (Exception $e) {
    if ($is_direct) {
        sendJsonResponse('error', 'Migration failed: ' . $e->getMessage(), ['details' => $wa_messages], 500);
    } else {
        throw $e;
    }
}
