<?php
// backend/api/analytics/track.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['session_token']) || empty($input['activity_type'])) {
    sendJsonResponse('error', 'Invalid input data', [], 400);
}

$sessionToken = trim($input['session_token']);
$activityType = trim($input['activity_type']);
$pageUrl = trim($input['page_url'] ?? '');
$pageTitle = trim($input['page_title'] ?? '');
$elementTag = trim($input['element_tag'] ?? '');
$elementId = trim($input['element_id'] ?? '');
$elementClass = trim($input['element_class'] ?? '');
$elementText = trim($input['element_text'] ?? '');
$referrer = trim($input['referrer'] ?? '');

$db = Database::getConnection();

try {
    // 1. Ensure database tables exist (self-healing migration)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `visitor_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `session_token` VARCHAR(255) UNIQUE NOT NULL,
            `user_id` INT DEFAULT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `country` VARCHAR(100) DEFAULT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `browser` VARCHAR(100) DEFAULT NULL,
            `os` VARCHAR(100) DEFAULT NULL,
            `device_type` VARCHAR(50) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `referrer` VARCHAR(500) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_sessions_token` (`session_token`),
            INDEX `idx_sessions_user` (`user_id`)
        ) ENGINE=InnoDB;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `visitor_activities` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `session_id` INT NOT NULL,
            `activity_type` VARCHAR(100) NOT NULL,
            `page_url` VARCHAR(500) NOT NULL,
            `page_title` VARCHAR(255) DEFAULT NULL,
            `element_tag` VARCHAR(50) DEFAULT NULL,
            `element_id` VARCHAR(100) DEFAULT NULL,
            `element_class` VARCHAR(255) DEFAULT NULL,
            `element_text` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_activities_session` FOREIGN KEY (`session_id`) REFERENCES `visitor_sessions` (`id`) ON DELETE CASCADE,
            INDEX `idx_activities_session` (`session_id`)
        ) ENGINE=InnoDB;
    ");

    // 2. Resolve Authenticated User optionally
    $user = JWTHelper::getAuthenticatedUser();
    $userId = $user ? (int)$user['id'] : null;

    // 3. Find or Create Session
    $stmtSession = $db->prepare("SELECT id, user_id FROM visitor_sessions WHERE session_token = ? LIMIT 1");
    $stmtSession->execute([$sessionToken]);
    $session = $stmtSession->fetch(PDO::FETCH_ASSOC);

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (!$session) {
        // Resolve location & UA details for new session
        list($browser, $os, $deviceType) = parseUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '');
        list($country, $city) = resolveGeoIP($ipAddress);

        $stmtInsertSession = $db->prepare("
            INSERT INTO visitor_sessions (session_token, user_id, ip_address, country, city, browser, os, device_type, user_agent, referrer)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsertSession->execute([
            $sessionToken,
            $userId,
            $ipAddress,
            $country,
            $city,
            $browser,
            $os,
            $deviceType,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $referrer ?: null
        ]);
        $sessionId = $db->lastInsertId();
    } else {
        $sessionId = (int)$session['id'];
        
        // If user logged in, associate anonymous session retrospectively
        if ($userId && empty($session['user_id'])) {
            $stmtUpdateSession = $db->prepare("UPDATE visitor_sessions SET user_id = ? WHERE id = ?");
            $stmtUpdateSession->execute([$userId, $sessionId]);
        }
    }

    // 4. Log the activity details
    $stmtInsertActivity = $db->prepare("
        INSERT INTO visitor_activities (session_id, activity_type, page_url, page_title, element_tag, element_id, element_class, element_text)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtInsertActivity->execute([
        $sessionId,
        $activityType,
        $pageUrl,
        $pageTitle,
        $elementTag ?: null,
        $elementId ?: null,
        $elementClass ?: null,
        $elementText ?: null
    ]);

    sendJsonResponse('success', 'Event tracked successfully.');

} catch (Exception $e) {
    sendJsonResponse('error', 'Tracking failed: ' . $e->getMessage(), [], 500);
}

/**
 * Parses user agent string to extract Browser, OS, and Device type
 */
function parseUserAgent($ua) {
    $browser = "Unknown Browser";
    $os = "Unknown OS";
    $deviceType = "Desktop";

    if (empty($ua)) return [$browser, $os, $deviceType];

    // Browser
    if (preg_match('/Chrome/i', $ua) && !preg_match('/Edge|Edg/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/MSIE/i', $ua) || preg_match('/Trident/i', $ua)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/Edge|Edg/i', $ua)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/Opera|OPR/i', $ua)) {
        $browser = 'Opera';
    }

    // OS
    if (preg_match('/windows|win32/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
        $os = 'Mac OS X';
    } elseif (preg_match('/linux/i', $ua)) {
        $os = 'Linux';
    } elseif (preg_match('/iphone|ipad|ipod/i', $ua)) {
        $os = 'iOS';
        $deviceType = 'Mobile';
    } elseif (preg_match('/android/i', $ua)) {
        $os = 'Android';
        $deviceType = 'Mobile';
    }

    if (preg_match('/mobile|tablet|phone/i', $ua)) {
        $deviceType = 'Mobile';
    }

    return [$browser, $os, $deviceType];
}

/**
 * Resolves location info using Cloudflare headers or free IP Geolocation API
 */
function resolveGeoIP($ip) {
    // Check Cloudflare Country Header
    if (!empty($_SERVER["HTTP_CF_IPCOUNTRY"])) {
        return [$_SERVER["HTTP_CF_IPCOUNTRY"], "CF Geo Location"];
    }

    // Dev/Localhost check
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return ['Localhost', 'Localhost'];
    }

    // Fetch Geo Location with strict timeout
    try {
        $ch = curl_init("http://ip-api.com/json/" . $ip);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if (($data['status'] ?? '') === 'success') {
                return [$data['country'] ?? 'Unknown', $data['city'] ?? 'Unknown'];
            }
        }
    } catch (Exception $e) {
        // Fallback
    }

    return ['Unknown', 'Unknown'];
}
