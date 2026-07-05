<?php
// backend/api/crm/migrate.php

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

// Disable error display to avoid corrupting JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

$db = Database::getConnection();
$messages = [];

try {
    // 1. Email Intelligence Settings Table
    $db->exec("CREATE TABLE IF NOT EXISTS `email_intelligence_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNIQUE NOT NULL,
        `is_active` TINYINT(1) DEFAULT 0,
        `sync_interval_minutes` INT DEFAULT 60,
        `last_sync_at` TIMESTAMP NULL DEFAULT NULL,
        `next_sync_at` TIMESTAMP NULL DEFAULT NULL,
        `business_type` VARCHAR(100) DEFAULT NULL,
        `industry` VARCHAR(100) DEFAULT NULL,
        `timezone` VARCHAR(100) DEFAULT 'Asia/Kolkata',
        `working_hours` VARCHAR(100) DEFAULT NULL,
        `preferred_language` VARCHAR(50) DEFAULT 'en',
        `currency` VARCHAR(10) DEFAULT 'USD',
        `permissions_json` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_ei_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'email_intelligence_settings' checked/created.";

    // 2. IMAP & SMTP Custom Configurations (to expand beyond simple SMTP)
    $db->exec("CREATE TABLE IF NOT EXISTS `imap_smtp_configurations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNIQUE NOT NULL,
        `email_provider` VARCHAR(50) DEFAULT 'custom',
        `smtp_host` VARCHAR(255) DEFAULT NULL,
        `smtp_port` INT DEFAULT NULL,
        `smtp_username` VARCHAR(255) DEFAULT NULL,
        `smtp_password` TEXT DEFAULT NULL,
        `smtp_encryption` VARCHAR(10) DEFAULT 'tls',
        `imap_host` VARCHAR(255) DEFAULT NULL,
        `imap_port` INT DEFAULT NULL,
        `imap_username` VARCHAR(255) DEFAULT NULL,
        `imap_password` TEXT DEFAULT NULL,
        `imap_encryption` VARCHAR(10) DEFAULT 'ssl',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_imap_smtp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'imap_smtp_configurations' checked/created.";

    // 3. CRM Companies Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_companies` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `industry` VARCHAR(255) DEFAULT NULL,
        `website` VARCHAR(255) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `gst` VARCHAR(50) DEFAULT NULL,
        `employees` INT DEFAULT 0,
        `owner` VARCHAR(255) DEFAULT NULL,
        `revenue` DECIMAL(15, 2) DEFAULT 0.00,
        `source` VARCHAR(100) DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `tags` VARCHAR(255) DEFAULT NULL,
        `social_links` TEXT DEFAULT NULL,
        `custom_fields` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_crm_companies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        INDEX `idx_crm_comp_name` (`user_id`, `name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'crm_companies' checked/created.";

    // 4. CRM Contacts Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_contacts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `company_id` INT DEFAULT NULL,
        `name` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `alternate_email` VARCHAR(255) DEFAULT NULL,
        `linkedin` VARCHAR(255) DEFAULT NULL,
        `whatsapp` VARCHAR(50) DEFAULT NULL,
        `designation` VARCHAR(100) DEFAULT NULL,
        `department` VARCHAR(100) DEFAULT NULL,
        `birthday` DATE DEFAULT NULL,
        `location` VARCHAR(255) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `custom_fields` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_crm_contacts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_crm_contacts_company` FOREIGN KEY (`company_id`) REFERENCES `crm_companies` (`id`) ON DELETE SET NULL,
        INDEX `idx_crm_cont_email` (`user_id`, `email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'crm_contacts' checked/created.";

    // 5. CRM Leads Table (AI intelligence creates these automatically)
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_leads` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `company_id` INT DEFAULT NULL,
        `contact_id` INT DEFAULT NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `company` VARCHAR(255) DEFAULT NULL,
        `budget` DECIMAL(15, 2) DEFAULT 0.00,
        `requirements` TEXT DEFAULT NULL,
        `services_required` TEXT DEFAULT NULL,
        `priority` VARCHAR(20) DEFAULT 'medium',
        `expected_closing_date` DATE DEFAULT NULL,
        `assigned_employee` VARCHAR(255) DEFAULT NULL,
        `lead_score` INT DEFAULT 0,
        `ai_confidence_score` INT DEFAULT 0,
        `lead_source` VARCHAR(100) DEFAULT NULL,
        `stage` VARCHAR(50) DEFAULT 'New',
        `tags` VARCHAR(255) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `custom_fields` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_crm_leads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_crm_leads_company` FOREIGN KEY (`company_id`) REFERENCES `crm_companies` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_crm_leads_contact` FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
        INDEX `idx_crm_leads_stage` (`user_id`, `stage`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'crm_leads' checked/created.";

    // 6. CRM Deals Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_deals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `company_id` INT DEFAULT NULL,
        `contact_id` INT DEFAULT NULL,
        `lead_id` INT DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `stage` VARCHAR(50) NOT NULL DEFAULT 'Lead',
        `expected_revenue` DECIMAL(15, 2) DEFAULT 0.00,
        `probability` INT DEFAULT 50,
        `owner` VARCHAR(255) DEFAULT NULL,
        `closing_date` DATE DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_crm_deals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_crm_deals_company` FOREIGN KEY (`company_id`) REFERENCES `crm_companies` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_crm_deals_contact` FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_crm_deals_lead` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE SET NULL,
        INDEX `idx_crm_deals_stage` (`user_id`, `stage`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'crm_deals' checked/created.";

    // 7. CRM Meetings Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_meetings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `company_id` INT DEFAULT NULL,
        `contact_id` INT DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `start_time` DATETIME NOT NULL,
        `end_time` DATETIME DEFAULT NULL,
        `location` VARCHAR(255) DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'scheduled',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_crm_meetings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_crm_meetings_company` FOREIGN KEY (`company_id`) REFERENCES `crm_companies` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_crm_meetings_contact` FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'crm_meetings' checked/created.";

    // 8. CRM Tasks Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `company_id` INT DEFAULT NULL,
        `contact_id` INT DEFAULT NULL,
        `lead_id` INT DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `due_date` DATE DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'pending',
        `priority` VARCHAR(20) DEFAULT 'medium',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_crm_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_crm_tasks_company` FOREIGN KEY (`company_id`) REFERENCES `crm_companies` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_crm_tasks_contact` FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_crm_tasks_lead` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'crm_tasks' checked/created.";

    // 9. Received Emails (from IMAP configuration)
    $db->exec("CREATE TABLE IF NOT EXISTS `received_emails` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `message_id` VARCHAR(255) NOT NULL,
        `sender_email` VARCHAR(255) NOT NULL,
        `sender_name` VARCHAR(255) DEFAULT NULL,
        `recipient_email` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) DEFAULT NULL,
        `body_text` LONGTEXT DEFAULT NULL,
        `body_html` LONGTEXT DEFAULT NULL,
        `received_date` DATETIME DEFAULT NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `is_starred` TINYINT(1) DEFAULT 0,
        `is_archived` TINYINT(1) DEFAULT 0,
        `category` VARCHAR(50) DEFAULT 'General Query',
        `ai_summary` TEXT DEFAULT NULL,
        `ai_suggested_reply` TEXT DEFAULT NULL,
        `ai_confidence_score` INT DEFAULT 0,
        `sentiment` VARCHAR(50) DEFAULT 'neutral',
        `priority` VARCHAR(20) DEFAULT 'medium',
        `is_spam` TINYINT(1) DEFAULT 0,
        `spam_probability` INT DEFAULT 0,
        `extracted_data_json` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_rec_emails_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        INDEX `idx_rec_email_msg` (`user_id`, `message_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'received_emails' checked/created.";

    // 10. Received Email Attachments Table
    $db->exec("CREATE TABLE IF NOT EXISTS `email_attachments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `received_email_id` INT NOT NULL,
        `filename` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `file_type` VARCHAR(100) DEFAULT NULL,
        `file_size` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_attachments_email` FOREIGN KEY (`received_email_id`) REFERENCES `received_emails` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'email_attachments' checked/created.";

    // 11. CRM Timeline Table (Unified activity history stream)
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_timeline` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `company_id` INT DEFAULT NULL,
        `contact_id` INT DEFAULT NULL,
        `lead_id` INT DEFAULT NULL,
        `deal_id` INT DEFAULT NULL,
        `activity_type` VARCHAR(100) NOT NULL,
        `description` TEXT NOT NULL,
        `meta_json` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_timeline_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_timeline_company` FOREIGN KEY (`company_id`) REFERENCES `crm_companies` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_timeline_contact` FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_timeline_lead` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_timeline_deal` FOREIGN KEY (`deal_id`) REFERENCES `crm_deals` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'crm_timeline' checked/created.";

    // 12. CRM Notes Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_notes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `company_id` INT DEFAULT NULL,
        `contact_id` INT DEFAULT NULL,
        `content` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_notes_company` FOREIGN KEY (`company_id`) REFERENCES `crm_companies` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_notes_contact` FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'crm_notes' checked/created.";

    // 13. CRM Documents Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_documents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `company_id` INT DEFAULT NULL,
        `contact_id` INT DEFAULT NULL,
        `filename` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `file_size` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_documents_company` FOREIGN KEY (`company_id`) REFERENCES `crm_companies` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_documents_contact` FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'crm_documents' checked/created.";

    // 14. Automation Workflows Table
    $db->exec("CREATE TABLE IF NOT EXISTS `automation_workflows` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `trigger_type` VARCHAR(100) NOT NULL,
        `trigger_value` VARCHAR(100) DEFAULT NULL,
        `actions_json` TEXT NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_workflows_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'automation_workflows' checked/created.";

    // 15. Email Processing Logs Table
    $db->exec("CREATE TABLE IF NOT EXISTS `email_processing_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `email_subject` VARCHAR(255) DEFAULT NULL,
        `sender` VARCHAR(255) DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'pending',
        `message` TEXT DEFAULT NULL,
        `tokens_used` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_processing_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'email_processing_logs' checked/created.";

    // 16. Admin Global Settings Table
    $db->exec("CREATE TABLE IF NOT EXISTS `admin_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) UNIQUE NOT NULL,
        `setting_value` LONGTEXT DEFAULT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'admin_settings' checked/created.";

    // 17. Create scraper_requests_log table
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'scraper_requests_log' checked/created.";

    // 19. Create spam_filters table
    $db->exec("CREATE TABLE IF NOT EXISTS `spam_filters` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `filter_type` VARCHAR(50) NOT NULL, -- 'email', 'domain', or 'keyword'
        `filter_value` VARCHAR(255) NOT NULL,
        `category` VARCHAR(50) DEFAULT 'Spam', -- 'Spam' or 'Promotion'
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_spam_filters_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        UNIQUE KEY `idx_user_filter` (`user_id`, `filter_type`, `filter_value`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $messages[] = "Table 'spam_filters' checked/created.";

    // 18. Insert or update linkedin_scraper settings
    $stmtScrapCount = $db->prepare("SELECT COUNT(*) FROM `email_provider_settings` WHERE provider_name = ?");
    $stmtScrapCount->execute(['linkedin_scraper']);
    if ((int)$stmtScrapCount->fetchColumn() === 0) {
        $stmtScrapSeed = $db->prepare("INSERT INTO `email_provider_settings` (provider_name, is_enabled, api_key, api_secret, priority) VALUES (?, ?, ?, ?, ?)");
        $stmtScrapSeed->execute(['linkedin_scraper', 1, 'http://127.0.0.1:8001', '15', 10]);
        $messages[] = "Linkedin scraper settings seeded.";
    } else {
        $db->exec("UPDATE `email_provider_settings` SET is_enabled = 1, api_key = 'http://127.0.0.1:8001' WHERE provider_name = 'linkedin_scraper'");
        $messages[] = "Linkedin scraper settings updated.";
    }

    sendJsonResponse('success', 'LinkPilot CRM v2.0 Database Migrations executed successfully.', [
        'details' => $messages
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Migration failed: ' . $e->getMessage(), [
        'details' => $messages
    ], 500);
}
