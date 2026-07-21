<?php
// backend/api/crm/migrate_email_settings_v2.php

require_once __DIR__ . '/../../config.php';

try {
    $db = Database::getConnection();

    // 1. Table for Advanced Email Settings (Warm-up, Reply-To, Tracking, Bounces, Unsubscribe)
    $db->exec("CREATE TABLE IF NOT EXISTS email_advanced_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        default_reply_to VARCHAR(255) DEFAULT NULL,
        default_sender_name VARCHAR(255) DEFAULT NULL,
        warmup_enabled TINYINT(1) DEFAULT 0,
        warmup_daily_limit INT DEFAULT 50,
        warmup_increment INT DEFAULT 5,
        warmup_min_delay INT DEFAULT 60,
        warmup_max_delay INT DEFAULT 300,
        warmup_peer_network TINYINT(1) DEFAULT 1,
        open_tracking_enabled TINYINT(1) DEFAULT 1,
        open_tracking_domain VARCHAR(255) DEFAULT NULL,
        open_tracking_exclude_ips TEXT DEFAULT NULL,
        open_tracking_bot_filter TINYINT(1) DEFAULT 1,
        click_tracking_enabled TINYINT(1) DEFAULT 1,
        click_tracking_domain VARCHAR(255) DEFAULT NULL,
        click_tracking_preserve_params TINYINT(1) DEFAULT 1,
        bounce_max_hard_bounces INT DEFAULT 1,
        bounce_auto_unsubscribe TINYINT(1) DEFAULT 1,
        bounce_alert_enabled TINYINT(1) DEFAULT 1,
        bounce_alert_threshold FLOAT DEFAULT 3.0,
        unsubscribe_enabled TINYINT(1) DEFAULT 1,
        unsubscribe_list_header TINYINT(1) DEFAULT 1,
        unsubscribe_footer_html TEXT DEFAULT NULL,
        unsubscribe_page_message TEXT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Table for Sending Domains (SPF, DKIM, DMARC)
    $db->exec("CREATE TABLE IF NOT EXISTS sending_domains (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        domain VARCHAR(255) NOT NULL,
        spf_status VARCHAR(50) DEFAULT 'verified',
        dkim_status VARCHAR(50) DEFAULT 'verified',
        dmarc_status VARCHAR(50) DEFAULT 'verified',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_domain (user_id, domain)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Table for Email Signatures
    $db->exec("CREATE TABLE IF NOT EXISTS email_signatures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        body_html TEXT NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_sig (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    try { $db->exec("ALTER TABLE email_advanced_settings ADD COLUMN followup_categories_mode VARCHAR(20) DEFAULT 'selected'"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE email_advanced_settings ADD COLUMN followup_categories TEXT DEFAULT 'New Lead,Meeting Request,Support Request,Existing Client,Invoice'"); } catch (Throwable $e) {}

    echo "Migration email_settings_v2 executed successfully.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
