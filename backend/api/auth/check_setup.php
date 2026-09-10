<?php
// backend/api/auth/check_setup.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../external_apps_helper.php';

// Require authentication
$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];

$db = Database::getConnection();

try {
    // 1. Ensure user_profiles table has setup tracking columns
    try {
        $stmtCol1 = $db->query("SHOW COLUMNS FROM `user_profiles` LIKE 'setup_completed'");
        if (!$stmtCol1->fetch()) {
            $db->exec("ALTER TABLE `user_profiles` ADD COLUMN `setup_completed` TINYINT(1) DEFAULT 0");
        }
        $stmtCol2 = $db->query("SHOW COLUMNS FROM `user_profiles` LIKE 'setup_step'");
        if (!$stmtCol2->fetch()) {
            $db->exec("ALTER TABLE `user_profiles` ADD COLUMN `setup_step` INT DEFAULT 1");
        }
    } catch (Exception $ex) {
        // Ignore column check errors if DB user lacks alter permissions
    }

    // 2. Check Email Connection (Google OAuth or SMTP)
    $emailConnected = false;
    $emailType = null;
    
    // Check Google OAuth Connection
    try {
        $stmtGoogle = $db->prepare("SELECT status FROM external_app_connections WHERE user_id = ? AND provider = 'google' AND status = 'connected' LIMIT 1");
        $stmtGoogle->execute([$userId]);
        if ($stmtGoogle->fetch()) {
            $emailConnected = true;
            $emailType = 'google';
        }
    } catch (Exception $e) {}

    // Check custom SMTP Accounts
    if (!$emailConnected) {
        try {
            $stmtSmtp = $db->prepare("SELECT id FROM smtp_accounts WHERE user_id = ? LIMIT 1");
            $stmtSmtp->execute([$userId]);
            if ($stmtSmtp->fetch()) {
                $emailConnected = true;
                $emailType = 'smtp';
            }
        } catch (Exception $e) {}
    }

    // 3. Check Meta WhatsApp Connection
    $whatsappConnected = false;
    try {
        $stmtWa = $db->prepare("SELECT id FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
        $stmtWa->execute([$userId]);
        if ($stmtWa->fetch()) {
            $whatsappConnected = true;
        }
    } catch (Exception $e) {}

    // 4. Check user profile for explicit setup_completed flag
    $explicitCompleted = false;
    $savedStep = 1;
    try {
        $stmtProfile = $db->prepare("SELECT setup_completed, setup_step FROM user_profiles WHERE user_id = ? LIMIT 1");
        $stmtProfile->execute([$userId]);
        $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $explicitCompleted = !empty($profile['setup_completed']);
            $savedStep = (int)($profile['setup_step'] ?? 1);
        }
    } catch (Exception $e) {}

    // 5. Determine Overall Setup Completion & Target Redirect
    // Setup is complete if user explicitly finished or if connections (Email and/or WhatsApp) are configured
    $setupCompleted = $explicitCompleted || ($emailConnected && $whatsappConnected) || ($emailConnected || $whatsappConnected);

    $recommendedStep = 1;
    if ($setupCompleted) {
        $recommendedStep = 5;
        $redirectTarget = 'index.html';
    } elseif ($emailConnected && !$whatsappConnected) {
        $recommendedStep = 3;
        $redirectTarget = 'setup.html?step=3';
    } else {
        $recommendedStep = 1;
        $redirectTarget = 'setup.html?step=1';
    }

    sendJsonResponse('success', 'Setup status evaluated successfully.', [
        'email_connected' => $emailConnected,
        'email_type' => $emailType,
        'whatsapp_connected' => $whatsappConnected,
        'explicit_completed' => $explicitCompleted,
        'setup_completed' => $setupCompleted,
        'recommended_step' => $recommendedStep,
        'redirect_target' => $redirectTarget
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Server error checking setup status: ' . $e->getMessage(), [], 500);
}
