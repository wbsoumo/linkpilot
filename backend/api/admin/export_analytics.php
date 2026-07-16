<?php
// backend/api/admin/export_analytics.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

$db = Database::getConnection();

try {
    // 1. Fetch all tables from active database dynamically
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    // Exclude high-volume telemetry, raw event logs, caches, and system logging tables
    $excludedTables = [
        'universal_events',
        'visitor_sessions',
        'visitor_activities',
        'performance_metrics',
        'platform_errors',
        'activity_logs',
        'otp_verifications',
        'email_cache',
        'user_ai_key_logs',
        'extension_events',
        'scraper_requests_log',
        'email_processing_logs',
        'workflow_execution_logs',
        'whatsapp_webhook_logs',
        'whatsapp_automation_logs',
        'whatsapp_campaign_logs'
    ];

    $tables = array_filter($tables, function($tableName) use ($excludedTables) {
        return !in_array($tableName, $excludedTables);
    });

    // Check for API request to list tables
    if (isset($_GET['action']) && $_GET['action'] === 'list_tables') {
        sendJsonResponse('success', 'Tables list retrieved', ['tables' => array_values($tables)]);
    }

    // Filter tables if specified
    if (!empty($_GET['tables'])) {
        $selectedList = explode(',', $_GET['tables']);
        $tables = array_filter($tables, function($t) use ($selectedList) {
            return in_array($t, $selectedList);
        });
    }

    if (empty($tables)) {
        throw new Exception("No tables selected for export.");
    }

    // Clear any previous output or buffering to prevent leading whitespace/warnings
    if (ob_get_length()) {
        ob_clean();
    }

    if (count($tables) === 1) {
        // Single table selected - download directly as CSV
        $tableName = reset($tables);
        
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=linkpilot_" . $tableName . "_" . date('Ymd_His') . ".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        $output = fopen('php://output', 'w');
        
        // Fetch rows
        $rows = $db->query("SELECT * FROM `$tableName` LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
        
        // Headers
        if (!empty($rows)) {
            $headers = array_keys($rows[0]);
        } else {
            $headers = $db->query("DESCRIBE `$tableName`")->fetchAll(PDO::FETCH_COLUMN);
        }
        
        fputcsv($output, $headers);
        
        foreach ($rows as $row) {
            $formattedRow = [];
            foreach ($row as $val) {
                if (is_array($val) || is_object($val)) {
                    $formattedRow[] = json_encode($val);
                } else {
                    $formattedRow[] = (string)$val;
                }
            }
            fputcsv($output, $formattedRow);
        }
        
        fclose($output);
        exit;
    } else {
        // Multiple tables selected - download as ZIP containing CSVs
        $zipName = sys_get_temp_dir() . '/linkpilot_db_report_' . uniqid() . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Cannot create ZIP file");
        }
        
        foreach ($tables as $tableName) {
            // Fetch rows
            $rows = $db->query("SELECT * FROM `$tableName` LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
            
            // Headers
            if (!empty($rows)) {
                $headers = array_keys($rows[0]);
            } else {
                $headers = $db->query("DESCRIBE `$tableName`")->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // Create a temporary stream for CSV content
            $tempStream = fopen('php://temp', 'r+');
            fputcsv($tempStream, $headers);
            
            foreach ($rows as $row) {
                $formattedRow = [];
                foreach ($row as $val) {
                    if (is_array($val) || is_object($val)) {
                        $formattedRow[] = json_encode($val);
                    } else {
                        $formattedRow[] = (string)$val;
                    }
                }
                fputcsv($tempStream, $formattedRow);
            }
            
            rewind($tempStream);
            $csvContent = stream_get_contents($tempStream);
            fclose($tempStream);
            
            $zip->addFromString($tableName . '.csv', $csvContent);
        }
        
        $zip->close();
        
        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=linkpilot_database_report_" . date('Ymd_His') . ".zip");
        header("Content-Length: " . filesize($zipName));
        header("Pragma: no-cache");
        header("Expires: 0");
        
        readfile($zipName);
        unlink($zipName);
        exit;
    }

} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Export failed: " . $e->getMessage();
    exit;
}
