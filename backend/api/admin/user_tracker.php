<?php
// backend/api/admin/user_tracker.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

$db = Database::getConnection();

try {
    // If querying details of a specific session
    if (isset($_GET['session_id'])) {
        $sessionId = (int)$_GET['session_id'];
        
        $stmtSession = $db->prepare("
            SELECT s.*, u.name AS user_name, u.email AS user_email
            FROM visitor_sessions s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmtSession->execute([$sessionId]);
        $session = $stmtSession->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            sendJsonResponse('error', 'Session not found.', [], 404);
        }
        
        $stmtActivities = $db->prepare("
            SELECT * FROM visitor_activities 
            WHERE session_id = ? 
            ORDER BY id ASC
        ");
        $stmtActivities->execute([$sessionId]);
        $activities = $stmtActivities->fetchAll(PDO::FETCH_ASSOC);
        
        sendJsonResponse('success', 'Session details loaded.', [
            'session' => $session,
            'activities' => $activities
        ]);
        exit;
    }

    // 1. Total Session Counters
    $totalSessions = (int)$db->query("SELECT COUNT(*) FROM visitor_sessions")->fetchColumn();
    
    // Active in last 15 minutes
    $stmtActive = $db->query("SELECT COUNT(*) FROM visitor_sessions WHERE updated_at >= NOW() - INTERVAL 15 MINUTE");
    $activeSessions = (int)$stmtActive->fetchColumn();

    // 2. Device Breakdown
    $deviceStats = $db->query("
        SELECT COALESCE(device_type, 'Desktop') AS label, COUNT(*) AS count 
        FROM visitor_sessions 
        GROUP BY device_type
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Country Breakdown
    $countryStats = $db->query("
        SELECT COALESCE(country, 'Unknown') AS label, COUNT(*) AS count 
        FROM visitor_sessions 
        GROUP BY country 
        ORDER BY count DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Recent Sessions List
    $stmtRecent = $db->query("
        SELECT s.id, s.session_token, s.user_id, s.ip_address, s.country, s.city, s.browser, s.os, s.device_type, s.referrer, s.created_at, s.updated_at,
               u.name AS user_name, u.email AS user_email,
               (SELECT COUNT(*) FROM visitor_activities WHERE session_id = s.id) AS activity_count
        FROM visitor_sessions s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.updated_at DESC
        LIMIT 100
    ");
    $sessions = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse('success', 'Analytics data loaded successfully.', [
        'counters' => [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions
        ],
        'devices' => $deviceStats,
        'countries' => $countryStats,
        'sessions' => $sessions
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to load user tracker stats: ' . $e->getMessage(), [], 500);
}
