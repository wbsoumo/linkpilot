<?php
// backend/api/admin/export_analytics.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

$db = Database::getConnection();

// Helper to generate clean CSV format strings safely
function generateCsv($headers, $rows) {
    $fp = fopen('php://temp', 'r+');
    fputcsv($fp, $headers);
    foreach ($rows as $row) {
        fputcsv($fp, array_map(function($val) {
            return is_null($val) ? '' : $val;
        }, $row));
    }
    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);
    return $csv;
}

try {
    // 1. Fetch User Identities & Auth
    $users = $db->query("SELECT id, name, email, role, status, created_at FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $userHeaders = ['User ID', 'Name', 'Email Address', 'Privilege Role', 'Account Status', 'Signed Up At'];

    // 2. Fetch Visitor Sessions
    $sessions = $db->query("
        SELECT id, session_token, user_id, ip_address, country, city, browser, os, device_type, active_duration, idle_duration, session_duration, created_at 
        FROM visitor_sessions
    ")->fetchAll(PDO::FETCH_ASSOC);
    $sessionHeaders = ['Session ID', 'Session Token', 'Associated User ID', 'IP Address', 'Country', 'City', 'Browser Name', 'OS Name', 'Device Type', 'Active Secs', 'Idle Secs', 'Total Session Secs', 'Created At'];

    // 3. Fetch Click Interactions
    $activities = $db->query("
        SELECT id, session_id, activity_type, page_url, page_title, element_tag, element_id, element_class, element_text, created_at 
        FROM visitor_activities
    ")->fetchAll(PDO::FETCH_ASSOC);
    $activityHeaders = ['Activity ID', 'Session ID', 'Activity Type', 'Page URL', 'Page Title', 'HTML Tag', 'Element ID', 'CSS Classes', 'Element Inner Text', 'Logged At'];

    // 4. Fetch Universal Event Log
    $events = $db->query("
        SELECT id, user_id, session_token, event_name, event_category, event_action, event_label, page_url, page_title, metadata_json, created_at 
        FROM universal_events
    ")->fetchAll(PDO::FETCH_ASSOC);
    $eventHeaders = ['Event ID', 'Associated User ID', 'Session Token', 'Event Name', 'Category', 'Action Taken', 'Label Description', 'Page URL', 'Page Title', 'Metadata JSON Object', 'Captured At'];

    // 5. Fetch Performance Telemetry
    $perfMetrics = $db->query("
        SELECT id, page_load_ms, api_latency_ms, db_time_ms, created_at 
        FROM performance_metrics
    ")->fetchAll(PDO::FETCH_ASSOC);
    $perfHeaders = ['Latency Log ID', 'Page Load Time (ms)', 'API Latency (ms)', 'DB Execution (ms)', 'Logged At'];

    // 6. Fetch Exceptions & Platform Errors
    $errors = $db->query("
        SELECT id, error_type, error_code, page_url, browser, stack_trace, api_endpoint, created_at 
        FROM platform_errors
    ")->fetchAll(PDO::FETCH_ASSOC);
    $errHeaders = ['Error Entry ID', 'Error Type', 'Error Code / Message', 'Occurrence Page URL', 'Browser Agent String', 'Stack Trace Logs', 'Trigger API Endpoint', 'Logged At'];

    // 7. Zip Archive Generation
    if (class_exists('ZipArchive')) {
        $zipName = tempnam(sys_get_temp_dir(), 'analytics_export_');
        $zip = new ZipArchive();
        
        if ($zip->open($zipName, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString('1_user_identities.csv', generateCsv($userHeaders, $users));
            $zip->addFromString('2_visitor_sessions.csv', generateCsv($sessionHeaders, $sessions));
            $zip->addFromString('3_activities_clicks.csv', generateCsv($activityHeaders, $activities));
            $zip->addFromString('4_universal_events.csv', generateCsv($eventHeaders, $events));
            $zip->addFromString('5_performance_telemetry.csv', generateCsv($perfHeaders, $perfMetrics));
            $zip->addFromString('6_platform_errors.csv', generateCsv($errHeaders, $errors));
            $zip->close();

            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename=linkpilot_analytics_export_" . date('Ymd_His') . ".zip");
            header("Content-Length: " . filesize($zipName));
            readfile($zipName);
            unlink($zipName);
            exit;
        }
    }

    // Fallback: If ZipArchive class is missing, output Universal Events CSV directly
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=linkpilot_universal_events_export_" . date('Ymd_His') . ".csv");
    echo generateCsv($eventHeaders, $events);
    exit;

} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Export failed: " . $e->getMessage();
    exit;
}
