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
    // 1. Fetch all tables from active database dynamically
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (class_exists('ZipArchive') && !empty($tables)) {
        $zipName = tempnam(sys_get_temp_dir(), 'analytics_export_');
        $zip = new ZipArchive();
        
        if ($zip->open($zipName, ZipArchive::CREATE) === TRUE) {
            foreach ($tables as $tableName) {
                // Fetch all rows for this table
                $rows = $db->query("SELECT * FROM `$tableName`")->fetchAll(PDO::FETCH_ASSOC);
                
                // Get column headers dynamically
                if (!empty($rows)) {
                    $headers = array_keys($rows[0]);
                } else {
                    $headers = $db->query("DESCRIBE `$tableName`")->fetchAll(PDO::FETCH_COLUMN);
                }
                
                // Build CSV string
                $csvContent = generateCsv($headers, $rows);
                
                // Add sheet to zip archive
                $zip->addFromString($tableName . '.csv', $csvContent);
            }
            $zip->close();

            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename=linkpilot_database_export_" . date('Ymd_His') . ".zip");
            header("Content-Length: " . filesize($zipName));
            readfile($zipName);
            unlink($zipName);
            exit;
        }
    }

    // Fallback: If ZipArchive class is missing, output universal_events CSV directly
    $events = $db->query("SELECT * FROM `universal_events` LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
    $headers = !empty($events) ? array_keys($events[0]) : ['id', 'event_name'];
    
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=linkpilot_universal_events_export_" . date('Ymd_His') . ".csv");
    echo generateCsv($headers, $events);
    exit;

} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Export failed: " . $e->getMessage();
    exit;
}
