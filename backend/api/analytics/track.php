<?php
// backend/api/analytics/track.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['session_token']) || empty($input['type'])) {
    sendJsonResponse('error', 'Invalid input tracking payload', [], 400);
}

$sessionToken = trim($input['session_token']);
$type = trim($input['type']); // 'event', 'session_init', 'session_ping', 'performance', 'error'
$payload = $input['payload'] ?? [];

$db = Database::getConnection();

try {
    // 1. Run migrations if they haven't run yet (self-healing DB check)
    // We check if universal_events exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'universal_events'")->fetch();
    if (!$tableCheck) {
        $migrationSql = file_get_contents(__DIR__ . '/../../database/analytics_schema.sql');
        $db->exec($migrationSql);
    }

    // 2. Resolve Authenticated User optionally
    $user = JWTHelper::getAuthenticatedUser();
    $userId = $user ? (int)$user['id'] : null;

    // 3. Find or Create Session
    $stmtSession = $db->prepare("SELECT id, user_id FROM visitor_sessions WHERE session_token = ? LIMIT 1");
    $stmtSession->execute([$sessionToken]);
    $session = $stmtSession->fetch(PDO::FETCH_ASSOC);

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (!$session) {
        // Resolve location info
        list($country, $city) = resolveGeoIP($ipAddress);

        $stmtInsertSession = $db->prepare("
            INSERT INTO visitor_sessions (session_token, user_id, ip_address, country, city, session_status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmtInsertSession->execute([
            $sessionToken,
            $userId,
            $ipAddress,
            $country,
            $city
        ]);
        $sessionId = $db->lastInsertId();
    } else {
        $sessionId = (int)$session['id'];
        
        // Update user ID if mismatch
        if ($userId && (int)$session['user_id'] !== $userId) {
            $stmtUpdateSession = $db->prepare("UPDATE visitor_sessions SET user_id = ? WHERE id = ?");
            $stmtUpdateSession->execute([$userId, $sessionId]);
        }
    }

    // 4. Handle pay-load routing based on event Type
    switch ($type) {
        case 'session_init':
            $device = $payload['device'] ?? [];
            $browser = $payload['browser'] ?? [];
            
            $stmtInit = $db->prepare("
                UPDATE visitor_sessions SET 
                    device_brand = ?, device_model = ?, device_type = ?, 
                    os = ?, os_version = ?, browser = ?, browser_version = ?, 
                    screen_width = ?, screen_height = ?, viewport_width = ?, viewport_height = ?, 
                    pixel_ratio = ?, touch_supported = ?, dark_mode_enabled = ?
                WHERE id = ?
            ");
            $stmtInit->execute([
                $device['brand'] ?? null,
                $device['model'] ?? null,
                $browser['device_type'] ?? null,
                $browser['os'] ?? null,
                $browser['os_version'] ?? null,
                $browser['name'] ?? null,
                $browser['version'] ?? null,
                $device['screen_width'] ?? null,
                $device['screen_height'] ?? null,
                $device['viewport_width'] ?? null,
                $device['viewport_height'] ?? null,
                $device['pixel_ratio'] ?? null,
                $device['touch_supported'] ?? 0,
                $device['dark_mode_enabled'] ?? 0,
                $sessionId
            ]);
            break;

        case 'session_ping':
            $activeInc = (int)($payload['active_duration_increment'] ?? 0);
            $idleInc = (int)($payload['idle_duration_increment'] ?? 0);
            
            $db->prepare("
                UPDATE visitor_sessions SET 
                    active_duration = COALESCE(active_duration, 0) + ?,
                    idle_duration = COALESCE(idle_duration, 0) + ?,
                    session_duration = COALESCE(session_duration, 0) + ?,
                    last_activity = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([
                $activeInc,
                $idleInc,
                ($activeInc + $idleInc),
                $sessionId
            ]);
            break;

        case 'performance':
            $stmtPerf = $db->prepare("
                INSERT INTO performance_metrics (page_load_ms, api_latency_ms, db_time_ms)
                VALUES (?, ?, ?)
            ");
            $stmtPerf->execute([
                $payload['page_load_ms'] ?? null,
                $payload['api_latency_ms'] ?? null,
                $payload['db_time_ms'] ?? null
            ]);
            break;

        case 'error':
            $stmtErr = $db->prepare("
                INSERT INTO platform_errors (error_type, error_code, page_url, browser, stack_trace, api_endpoint)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtErr->execute([
                $payload['error_type'] ?? 'JavaScript Error',
                $payload['error_code'] ?? null,
                $payload['page_url'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $payload['stack_trace'] ?? null,
                $payload['api_endpoint'] ?? null
            ]);
            break;

        case 'event':
            // Resolve location details for event tags
            $stmtSessionDetails = $db->prepare("SELECT country, city, browser, os, device_type FROM visitor_sessions WHERE id = ? LIMIT 1");
            $stmtSessionDetails->execute([$sessionId]);
            $sd = $stmtSessionDetails->fetch(PDO::FETCH_ASSOC) ?: [];

            $stmtEvent = $db->prepare("
                INSERT INTO universal_events (
                    workspace_id, user_id, session_token, event_name, event_category, 
                    event_action, event_label, object_type, object_id, page_url, 
                    page_title, referrer, metadata_json, device_type, browser, os, country, city, ip_address
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtEvent->execute([
                $payload['workspace_id'] ?? null,
                $userId,
                $sessionToken,
                $payload['event_name'] ?? 'custom_event',
                $payload['event_category'] ?? 'general',
                $payload['event_action'] ?? null,
                $payload['event_label'] ?? null,
                $payload['object_type'] ?? null,
                $payload['object_id'] ?? null,
                $payload['page_url'] ?? '',
                $payload['page_title'] ?? null,
                $payload['referrer'] ?? null,
                isset($payload['metadata']) ? json_encode($payload['metadata']) : null,
                $sd['device_type'] ?? null,
                $sd['browser'] ?? null,
                $sd['os'] ?? null,
                $sd['country'] ?? null,
                $sd['city'] ?? null,
                $ipAddress
            ]);
            break;
    }

    sendJsonResponse('success', 'Metric event tracked successfully.');

} catch (Exception $e) {
    sendJsonResponse('error', 'Tracking failed: ' . $e->getMessage(), [], 500);
}

/**
 * Resolves location info using Cloudflare headers or free IP Geolocation API
 */
function resolveGeoIP($ip) {
    if (!empty($_SERVER["HTTP_CF_IPCOUNTRY"])) {
        return [$_SERVER["HTTP_CF_IPCOUNTRY"], "CF Geo Location"];
    }

    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return ['Localhost', 'Localhost'];
    }

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
        // Silence cURL timeouts
    }

    return ['Unknown', 'Unknown'];
}
