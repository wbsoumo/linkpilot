<?php
// backend/api/crm/email_intelligence/settings.php

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../jwt_helper.php';
require_once __DIR__ . '/../../../smtp_helper.php';
require_once __DIR__ . '/../../../imap_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($method === 'GET') {
        // Retrieve current settings
        $stmtSettings = $db->prepare("SELECT * FROM email_intelligence_settings WHERE user_id = ?");
        $stmtSettings->execute([$userId]);
        $settings = $stmtSettings->fetch();
        
        $stmtCredentials = $db->prepare("SELECT email_provider, smtp_host, smtp_port, smtp_username, smtp_encryption, imap_host, imap_port, imap_username, imap_encryption FROM imap_smtp_configurations WHERE user_id = ?");
        $stmtCredentials->execute([$userId]);
        $credentials = $stmtCredentials->fetch();
        
        if ($settings) {
            $settings['permissions'] = json_decode($settings['permissions_json'], true);
        }
        
        sendJsonResponse('success', 'Email Intelligence settings fetched', [
            'settings' => $settings ?: null,
            'connection' => $credentials ?: null
        ]);
    } 
    
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        if ($action === 'test_smtp') {
            // Test SMTP credentials
            $host = trim($input['smtp_host'] ?? '');
            $port = (int)($input['smtp_port'] ?? 587);
            $username = trim($input['smtp_username'] ?? '');
            $password = $input['smtp_password'] ?? '';
            $encryption = trim($input['smtp_encryption'] ?? 'tls');
            $senderName = trim($input['sender_name'] ?? $user['name']);
            $senderEmail = trim($input['sender_email'] ?? $username);

            if (empty($host) || empty($username) || empty($password)) {
                sendJsonResponse('error', 'SMTP Host, Username, and Password are required.', [], 400);
            }

            $test = SMTPHelper::testConnection($host, $port, $username, $password, $senderName, $senderEmail, $encryption);
            if ($test['status']) {
                sendJsonResponse('success', $test['message']);
            } else {
                sendJsonResponse('error', $test['message']);
            }
        } 
        
        elseif ($action === 'test_imap') {
            // Test IMAP credentials
            $host = trim($input['imap_host'] ?? '');
            $port = (int)($input['imap_port'] ?? 993);
            $username = trim($input['imap_username'] ?? '');
            $password = $input['imap_password'] ?? '';
            $encryption = trim($input['imap_encryption'] ?? 'ssl');

            if (empty($host) || empty($username) || empty($password)) {
                sendJsonResponse('error', 'IMAP Host, Username, and Password are required.', [], 400);
            }

            $test = IMAPHelper::testConnection($host, $port, $username, $password, $encryption);
            if ($test['status']) {
                sendJsonResponse('success', $test['message']);
            } else {
                sendJsonResponse('error', $test['message']);
            }
        } 
        
        elseif ($action === 'disconnect') {
            $db->beginTransaction();
            $db->prepare("DELETE FROM imap_smtp_configurations WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM smtp_accounts WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM email_intelligence_settings WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM received_emails WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM email_processing_logs WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM external_app_connections WHERE user_id = ? AND provider = 'google'")->execute([$userId]);
            $db->commit();
            sendJsonResponse('success', 'Email server disconnected successfully.');
        }

        else {
            // Save settings and configurations
            $emailProvider = trim($input['email_provider'] ?? 'custom');
            $smtpHost = trim($input['smtp_host'] ?? '');
            $smtpPort = (int)($input['smtp_port'] ?? 587);
            $smtpUsername = trim($input['smtp_username'] ?? '');
            $smtpPassword = $input['smtp_password'] ?? '';
            $smtpEncryption = trim($input['smtp_encryption'] ?? 'tls');
            
            $imapHost = trim($input['imap_host'] ?? '');
            $imapPort = (int)($input['imap_port'] ?? 993);
            $imapUsername = trim($input['imap_username'] ?? '');
            $imapPassword = $input['imap_password'] ?? '';
            $imapEncryption = trim($input['imap_encryption'] ?? 'ssl');
            
            $businessType = trim($input['business_type'] ?? 'Software Company');
            $industry = trim($input['industry'] ?? 'Technology');
            $timezone = trim($input['timezone'] ?? 'Asia/Kolkata');
            $workingHours = trim($input['working_hours'] ?? '09:00-18:00');
            $preferredLanguage = trim($input['preferred_language'] ?? 'en');
            $currency = trim($input['currency'] ?? 'USD');
            $syncInterval = max(15, (int)($input['sync_interval_minutes'] ?? 60));
            
            $permissions = $input['permissions'] ?? [];
            $permissionsJson = json_encode($permissions);
            
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 0;
            $consentAccepted = isset($input['consent_accepted']) ? (int)$input['consent_accepted'] : 0;

            if ($isActive && !$consentAccepted) {
                sendJsonResponse('error', 'You must accept the Privacy Agreement terms to activate the service.', [], 400);
            }

            $db->beginTransaction();

            // 1. Save or update settings
            $stmtSet = $db->prepare("INSERT INTO email_intelligence_settings (user_id, is_active, sync_interval_minutes, business_type, industry, timezone, working_hours, preferred_language, currency, permissions_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), sync_interval_minutes = VALUES(sync_interval_minutes), business_type = VALUES(business_type), industry = VALUES(industry), timezone = VALUES(timezone), working_hours = VALUES(working_hours), preferred_language = VALUES(preferred_language), currency = VALUES(currency), permissions_json = VALUES(permissions_json)");
            $stmtSet->execute([
                $userId, $isActive, $syncInterval, $businessType, $industry, $timezone, $workingHours, $preferredLanguage, $currency, $permissionsJson
            ]);

            // 2. Fetch existing credentials if password is empty (meaning they didn't modify it)
            $stmtOldCreds = $db->prepare("SELECT smtp_password, imap_password FROM imap_smtp_configurations WHERE user_id = ?");
            $stmtOldCreds->execute([$userId]);
            $oldCreds = $stmtOldCreds->fetch();

            $imapUseSmtpDetails = !empty($input['imap_use_smtp_details']) ? 1 : 0;
            $encryptedSmtpPass = !empty($smtpPassword) ? encryptData($smtpPassword) : ($oldCreds['smtp_password'] ?? '');
            if ($imapUseSmtpDetails) {
                $encryptedImapPass = $encryptedSmtpPass;
            } else {
                $encryptedImapPass = !empty($imapPassword) ? encryptData($imapPassword) : ($oldCreds['imap_password'] ?? '');
            }

            // 3. Save or update credentials
            $stmtCred = $db->prepare("INSERT INTO imap_smtp_configurations (user_id, email_provider, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, imap_host, imap_port, imap_username, imap_password, imap_encryption) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE email_provider = VALUES(email_provider), smtp_host = VALUES(smtp_host), smtp_port = VALUES(smtp_port), smtp_username = VALUES(smtp_username), smtp_password = VALUES(smtp_password), smtp_encryption = VALUES(smtp_encryption), imap_host = VALUES(imap_host), imap_port = VALUES(imap_port), imap_username = VALUES(imap_username), imap_password = VALUES(imap_password), imap_encryption = VALUES(imap_encryption)");
            $stmtCred->execute([
                $userId, $emailProvider, $smtpHost, $smtpPort, $smtpUsername, $encryptedSmtpPass, $smtpEncryption, $imapHost, $imapPort, $imapUsername, $encryptedImapPass, $imapEncryption
            ]);

            // Sync user profile business_name if empty
            if (!empty($businessType)) {
                $db->prepare("UPDATE user_profiles SET user_type = ? WHERE user_id = ? AND (user_type IS NULL OR user_type = '')")->execute([$businessType, $userId]);
            }

            // Sync custom default smtp table for legacy integrations compatibility
            if (!empty($smtpHost) && !empty($smtpUsername)) {
                $db->prepare("INSERT INTO smtp_accounts (user_id, host, port, username, password, sender_name, sender_email) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE host = VALUES(host), port = VALUES(port), username = VALUES(username), password = VALUES(password), sender_name = VALUES(sender_name), sender_email = VALUES(sender_email)")->execute([
                    $userId, $smtpHost, $smtpPort, $smtpUsername, $encryptedSmtpPass, $user['name'], $smtpUsername
                ]);
            }

            $db->commit();
            sendJsonResponse('success', 'Email Intelligence settings saved successfully.');
        }
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Operation failed: ' . $e->getMessage(), [], 500);
}
