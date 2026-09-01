<?php
// backend/api/crm/track_click.php

require_once __DIR__ . '/../../config.php';

$trackingId = trim($_GET['id'] ?? '');
$targetUrl = trim($_GET['url'] ?? $_GET['target'] ?? '');
$targetUrl = html_entity_decode($targetUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');

if (!empty($targetUrl) && !preg_match('/^https?:\/\//i', $targetUrl)) {
    $targetUrl = 'https://' . ltrim($targetUrl, '/');
}

if (empty($targetUrl) || preg_match('/^(javascript|vbscript|data):/i', $targetUrl)) {
    $targetUrl = 'https://linkpilot.work';
}

if (!empty($trackingId)) {
    try {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("SELECT id, campaign_log_id, recipient_email FROM email_tracking WHERE tracking_id = ?");
        $stmt->execute([$trackingId]);
        $trackRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($trackRow) {
            $campaignLogId = $trackRow['campaign_log_id'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = getClientIPAddress();

            // Client Detection
            $device = getDeviceType($userAgent);
            $browser = getBrowserName($userAgent);
            $os = getOSName($userAgent);
            $geo = getGeoLocation($ipAddress);

            // Record in email_click_tracking
            $stmtClick = $db->prepare("
                INSERT INTO email_click_tracking (tracking_id, campaign_log_id, link_url, ip_address, user_agent, device, browser, os, country, city, clicked_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtClick->execute([
                $trackingId,
                $campaignLogId,
                $targetUrl,
                $ipAddress,
                $userAgent,
                $device,
                $browser,
                $os,
                $geo['country'],
                $geo['city']
            ]);

            // Update email_campaign_logs
            if (!empty($campaignLogId)) {
                $db->prepare("
                    UPDATE email_campaign_logs
                    SET click_count = click_count + 1,
                        first_clicked_at = COALESCE(first_clicked_at, NOW()),
                        last_clicked_at = NOW()
                    WHERE id = ?
                ")->execute([$campaignLogId]);

                // Record LinkClicked activity event
                $stmtEvent = $db->prepare("
                    INSERT INTO email_activity_events (campaign_log_id, tracking_id, event_type, event_label, event_data, created_at)
                    VALUES (?, ?, 'LinkClicked', ?, ?, NOW())
                ");
                $eventData = json_encode([
                    'url' => $targetUrl,
                    'ip' => $ipAddress,
                    'device' => $device,
                    'browser' => $browser,
                    'os' => $os,
                    'location' => implode(', ', array_filter([$geo['city'], $geo['country']]))
                ]);
                $stmtEvent->execute([$campaignLogId, $trackingId, $targetUrl, $eventData]);
            }
        }
    } catch (Exception $e) {
        // Fail-safe: silently continue to redirect recipient
    }
}

// Perform 302 redirect
header('Location: ' . $targetUrl, true, 302);
exit;

// Helper functions
function getClientIPAddress() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function getDeviceType($ua) {
    if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) return 'Tablet';
    if (preg_match('/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/i', $ua)) return 'Mobile';
    return 'Desktop';
}

function getBrowserName($ua) {
    if (empty($ua)) return 'Unknown';
    if (preg_match('/Edge|Edg/i', $ua)) return 'Edge';
    if (preg_match('/Chrome/i', $ua)) return 'Chrome';
    if (preg_match('/Firefox/i', $ua)) return 'Firefox';
    if (preg_match('/Safari/i', $ua)) return 'Safari';
    return 'Other';
}

function getOSName($ua) {
    if (empty($ua)) return 'Unknown';
    if (preg_match('/macintosh|mac os x/i', $ua)) return 'macOS';
    if (preg_match('/windows|win32/i', $ua)) return 'Windows';
    if (preg_match('/iphone|ipad|ipod/i', $ua)) return 'iOS';
    if (preg_match('/android/i', $ua)) return 'Android';
    if (preg_match('/linux/i', $ua)) return 'Linux';
    return 'Unknown';
}

function getGeoLocation($ip) {
    $default = ['country' => 'Unknown', 'city' => 'Unknown'];
    if ($ip === '127.0.0.1' || $ip === '::1') return $default;

    $ctx = stream_context_create(['http' => ['timeout' => 1.5]]);
    $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,city,status", false, $ctx);
    if ($json) {
        $data = json_decode($json, true);
        if ($data && ($data['status'] ?? '') === 'success') {
            return [
                'country' => $data['country'] ?? 'Unknown',
                'city' => $data['city'] ?? 'Unknown'
            ];
        }
    }
    return $default;
}
