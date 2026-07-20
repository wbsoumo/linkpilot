<?php
// backend/api/crm/track_open.php

require_once __DIR__ . '/../../config.php';

$trackingId = trim($_GET['id'] ?? '');

// Initialize values
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ips[0]);
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Google Proxy detection
$isGoogleProxy = (stripos($userAgent, 'GoogleImageProxy') !== false || stripos($userAgent, 'ggpht.com') !== false) ? 1 : 0;

// Apple Privacy detection: Apple Mail Privacy Protection requests come from Apple proxy IPs or contain specific UA strings.
// Apple News / Mail proxy IPs are in the range of 17.57.*, 17.58.*, 17.253.*, etc. A general check for '17.' prefix is highly reliable for Apple.
$isApplePrivacy = (stripos($userAgent, 'AppleNews') !== false || stripos($userAgent, 'AppleMail') !== false || strpos($ip, '17.') === 0) ? 1 : 0;

// User Agent parsing
function parseUserAgent($ua) {
    $browser = 'Unknown';
    $os = 'Unknown';
    $device = 'Desktop';

    // Device & OS detection
    if (stripos($ua, 'windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'macintosh') !== false || stripos($ua, 'mac os x') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'iphone') !== false) {
        $os = 'iOS';
        $device = 'Mobile';
    } elseif (stripos($ua, 'ipad') !== false) {
        $os = 'iOS';
        $device = 'Tablet';
    } elseif (stripos($ua, 'android') !== false) {
        $os = 'Android';
        $device = 'Mobile';
    } elseif (stripos($ua, 'linux') !== false) {
        $os = 'Linux';
    }

    // Browser detection
    if (stripos($ua, 'chrome') !== false && stripos($ua, 'safari') !== false && stripos($ua, 'edge') === false && stripos($ua, 'edg') === false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'safari') !== false && stripos($ua, 'chrome') === false) {
        $browser = 'Safari';
    } elseif (stripos($ua, 'firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'edge') !== false || stripos($ua, 'edg') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'msie') !== false || stripos($ua, 'trident') !== false) {
        $browser = 'Internet Explorer';
    }

    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}

$uaInfo = parseUserAgent($userAgent);

// GeoIP Lookup with timeout (1.5 seconds)
function getGeoIpData($ipAddress) {
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['country' => 'Private IP', 'city' => 'Local'];
    }
    $url = "http://ip-api.com/json/" . urlencode($ipAddress);
    $ctx = stream_context_create(['http' => ['timeout' => 1.5]]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res) {
        $data = json_decode($res, true);
        if ($data && ($data['status'] ?? '') === 'success') {
            return [
                'country' => $data['country'] ?? 'Unknown',
                'city' => $data['city'] ?? 'Unknown'
            ];
        }
    }
    return ['country' => 'Unknown', 'city' => 'Unknown'];
}

$geo = getGeoIpData($ip);

// Update tracking data in database if valid tracking ID
if (!empty($trackingId)) {
    try {
        $db = Database::getConnection();
        
        // Atomically update tracking record using COALESCE to capture first open and prevent duplicates
        $stmt = $db->prepare("
            UPDATE email_tracking 
            SET 
                first_opened_at = COALESCE(first_opened_at, NOW()),
                last_opened_at = NOW(),
                open_count = open_count + 1,
                ip_address = ?,
                user_agent = ?,
                device = ?,
                browser = ?,
                os = ?,
                country = ?,
                city = ?,
                is_google_proxy = ?,
                is_apple_privacy = ?
            WHERE tracking_id = ?
        ");
        $stmt->execute([
            $ip,
            $userAgent,
            $uaInfo['device'],
            $uaInfo['browser'],
            $uaInfo['os'],
            $geo['country'],
            $geo['city'],
            $isGoogleProxy,
            $isApplePrivacy,
            $trackingId
        ]);
    } catch (Exception $e) {
        // Fail silently to always return the transparent pixel image to the email client
    }
}

// Return 1x1 transparent PNG
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
exit;
