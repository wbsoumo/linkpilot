<?php
// backend/api/crm/email_settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../smtp_helper.php';
require_once __DIR__ . '/../../imap_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($method === 'GET') {
        // Fetch SMTP & IMAP configurations
        $stmtCredentials = $db->prepare("SELECT email_provider, smtp_host, smtp_port, smtp_username, smtp_encryption, imap_host, imap_port, imap_username, imap_encryption FROM imap_smtp_configurations WHERE user_id = ?");
        $stmtCredentials->execute([$userId]);
        $credentials = $stmtCredentials->fetch(PDO::FETCH_ASSOC) ?: [];

        // Fetch Sending Domains
        $stmtDomains = $db->prepare("SELECT * FROM sending_domains WHERE user_id = ? ORDER BY id DESC");
        $stmtDomains->execute([$userId]);
        $domains = $stmtDomains->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Signatures
        $stmtSigs = $db->prepare("SELECT * FROM email_signatures WHERE user_id = ? ORDER BY is_default DESC, id DESC");
        $stmtSigs->execute([$userId]);
        $signatures = $stmtSigs->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Advanced Settings
        $stmtAdv = $db->prepare("SELECT * FROM email_advanced_settings WHERE user_id = ?");
        $stmtAdv->execute([$userId]);
        $advanced = $stmtAdv->fetch(PDO::FETCH_ASSOC);

        if (!$advanced) {
            // Seed defaults
            $stmtInsert = $db->prepare("INSERT INTO email_advanced_settings (user_id, default_reply_to, default_sender_name, warmup_enabled, warmup_daily_limit, warmup_increment, open_tracking_enabled, click_tracking_enabled, bounce_max_hard_bounces, unsubscribe_enabled, unsubscribe_footer_html) VALUES (?, ?, ?, 1, 50, 5, 1, 1, 1, 1, ?)");
            $defaultFooter = "<p style='font-size: 11px; color: #64748b; margin-top: 20px;'>If you no longer wish to receive these emails, you can <a href='{{unsubscribe_url}}' style='color: #3b82f6;'>unsubscribe here</a>.</p>";
            $stmtInsert->execute([$userId, $user['email'], $user['name'], $defaultFooter]);

            $stmtAdv->execute([$userId]);
            $advanced = $stmtAdv->fetch(PDO::FETCH_ASSOC);
        }

        // Seed default domain if empty
        if (empty($domains) && !empty($user['email'])) {
            $parts = explode('@', $user['email']);
            if (count($parts) === 2) {
                $domain = strtolower($parts[1]);
                $stmtDom = $db->prepare("INSERT INTO sending_domains (user_id, domain, spf_status, dkim_status, dmarc_status) VALUES (?, ?, 'verified', 'verified', 'verified')");
                $stmtDom->execute([$userId, $domain]);
                
                $stmtDomains->execute([$userId]);
                $domains = $stmtDomains->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Seed default signature if empty
        if (empty($signatures)) {
            $defaultSigHtml = "<div style='font-family: sans-serif; font-size: 13px; color: #334155;'><strong>" . htmlspecialchars($user['name']) . "</strong><br><span style='color: #64748b;'>LinkPilot User</span><br><a href='https://linkpilot.work' style='color: #2563eb;'>linkpilot.work</a></div>";
            $stmtSig = $db->prepare("INSERT INTO email_signatures (user_id, name, body_html, is_default) VALUES (?, 'Default Signature', ?, 1)");
            $stmtSig->execute([$userId, $defaultSigHtml]);

            $stmtSigs->execute([$userId]);
            $signatures = $stmtSigs->fetchAll(PDO::FETCH_ASSOC);
        }

        sendJsonResponse('success', 'Email settings retrieved', [
            'credentials' => $credentials,
            'domains' => $domains,
            'signatures' => $signatures,
            'advanced' => $advanced
        ]);
    }

    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        if ($action === 'save_domain') {
            $domain = strtolower(trim($input['domain'] ?? ''));
            if (empty($domain)) {
                sendJsonResponse('error', 'Domain name is required.');
            }
            $stmt = $db->prepare("INSERT INTO sending_domains (user_id, domain, spf_status, dkim_status, dmarc_status) VALUES (?, ?, 'verified', 'verified', 'verified')");
            $stmt->execute([$userId, $domain]);
            sendJsonResponse('success', 'Sending domain added successfully.');
        }

        elseif ($action === 'delete_domain') {
            $domainId = (int)($input['domain_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM sending_domains WHERE id = ? AND user_id = ?");
            $stmt->execute([$domainId, $userId]);
            sendJsonResponse('success', 'Sending domain removed.');
        }

        elseif ($action === 'save_signature') {
            $sigId = (int)($input['signature_id'] ?? 0);
            $name = trim($input['name'] ?? 'Custom Signature');
            $bodyHtml = $input['body_html'] ?? '';
            $isDefault = !empty($input['is_default']) ? 1 : 0;

            if ($isDefault) {
                $db->prepare("UPDATE email_signatures SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
            }

            if ($sigId > 0) {
                $stmt = $db->prepare("UPDATE email_signatures SET name = ?, body_html = ?, is_default = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $bodyHtml, $isDefault, $sigId, $userId]);
            } else {
                $stmt = $db->prepare("INSERT INTO email_signatures (user_id, name, body_html, is_default) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $name, $bodyHtml, $isDefault]);
            }
            sendJsonResponse('success', 'Signature saved successfully.');
        }

        elseif ($action === 'delete_signature') {
            $sigId = (int)($input['signature_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM email_signatures WHERE id = ? AND user_id = ?");
            $stmt->execute([$sigId, $userId]);
            sendJsonResponse('success', 'Signature deleted.');
        }

        elseif ($action === 'save_advanced') {
            $defaultReplyTo = trim($input['default_reply_to'] ?? $user['email']);
            $defaultSenderName = trim($input['default_sender_name'] ?? $user['name']);
            $warmupEnabled = !empty($input['warmup_enabled']) ? 1 : 0;
            $warmupDailyLimit = (int)($input['warmup_daily_limit'] ?? 50);
            $warmupIncrement = (int)($input['warmup_increment'] ?? 5);
            $warmupMinDelay = (int)($input['warmup_min_delay'] ?? 60);
            $warmupMaxDelay = (int)($input['warmup_max_delay'] ?? 300);
            $warmupPeerNetwork = !empty($input['warmup_peer_network']) ? 1 : 0;
            
            $openTrackingEnabled = !empty($input['open_tracking_enabled']) ? 1 : 0;
            $openTrackingDomain = trim($input['open_tracking_domain'] ?? '');
            $openTrackingExcludeIps = trim($input['open_tracking_exclude_ips'] ?? '');
            $openTrackingBotFilter = !empty($input['open_tracking_bot_filter']) ? 1 : 0;
            
            $clickTrackingEnabled = !empty($input['click_tracking_enabled']) ? 1 : 0;
            $clickTrackingDomain = trim($input['click_tracking_domain'] ?? '');
            $clickTrackingPreserveParams = !empty($input['click_tracking_preserve_params']) ? 1 : 0;
            
            $bounceMaxHardBounces = (int)($input['bounce_max_hard_bounces'] ?? 1);
            $bounceAutoUnsubscribe = !empty($input['bounce_auto_unsubscribe']) ? 1 : 0;
            $bounceAlertEnabled = !empty($input['bounce_alert_enabled']) ? 1 : 0;
            $bounceAlertThreshold = (float)($input['bounce_alert_threshold'] ?? 3.0);
            
            $unsubscribeEnabled = !empty($input['unsubscribe_enabled']) ? 1 : 0;
            $unsubscribeListHeader = !empty($input['unsubscribe_list_header']) ? 1 : 0;
            $unsubscribeFooterHtml = $input['unsubscribe_footer_html'] ?? '';
            $unsubscribePageMessage = $input['unsubscribe_page_message'] ?? '';

            $stmtUpdate = $db->prepare("UPDATE email_advanced_settings SET 
                default_reply_to = ?, default_sender_name = ?,
                warmup_enabled = ?, warmup_daily_limit = ?, warmup_increment = ?, warmup_min_delay = ?, warmup_max_delay = ?, warmup_peer_network = ?,
                open_tracking_enabled = ?, open_tracking_domain = ?, open_tracking_exclude_ips = ?, open_tracking_bot_filter = ?,
                click_tracking_enabled = ?, click_tracking_domain = ?, click_tracking_preserve_params = ?,
                bounce_max_hard_bounces = ?, bounce_auto_unsubscribe = ?, bounce_alert_enabled = ?, bounce_alert_threshold = ?,
                unsubscribe_enabled = ?, unsubscribe_list_header = ?, unsubscribe_footer_html = ?, unsubscribe_page_message = ?
                WHERE user_id = ?");

            $stmtUpdate->execute([
                $defaultReplyTo, $defaultSenderName,
                $warmupEnabled, $warmupDailyLimit, $warmupIncrement, $warmupMinDelay, $warmupMaxDelay, $warmupPeerNetwork,
                $openTrackingEnabled, $openTrackingDomain, $openTrackingExcludeIps, $openTrackingBotFilter,
                $clickTrackingEnabled, $clickTrackingDomain, $clickTrackingPreserveParams,
                $bounceMaxHardBounces, $bounceAutoUnsubscribe, $bounceAlertEnabled, $bounceAlertThreshold,
                $unsubscribeEnabled, $unsubscribeListHeader, $unsubscribeFooterHtml, $unsubscribePageMessage,
                $userId
            ]);

            sendJsonResponse('success', 'Email settings updated successfully.');
        }

        else {
            sendJsonResponse('error', 'Invalid action specified.');
        }
    }
} catch (Throwable $e) {
    sendJsonResponse('error', 'Server error: ' . $e->getMessage(), [], 500);
}
