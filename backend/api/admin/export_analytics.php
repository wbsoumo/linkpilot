<?php
// backend/api/admin/export_analytics.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

$db = Database::getConnection();

function xmlEscape($val) {
    if (is_null($val)) return '';
    return htmlspecialchars((string)$val, ENT_XML1, 'UTF-8');
}

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


    // 2. Set headers for native Excel XML download
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=linkpilot_database_report_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    // 3. Output XML SpreadsheetML header
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
    echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
    echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    
    echo ' <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">' . "\n";
    echo '  <Author>LinkPilot AI</Author>' . "\n";
    echo '  <Created>' . date('Y-m-d\TH:i:s\Z') . '</Created>' . "\n";
    echo ' </DocumentProperties>' . "\n";

    echo ' <Styles>' . "\n";
    echo '  <Style ss:ID="Default" ss:Name="Normal">' . "\n";
    echo '   <Alignment ss:Vertical="Bottom"/>' . "\n";
    echo '   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>' . "\n";
    echo '  </Style>' . "\n";
    echo '  <Style ss:ID="Header">' . "\n";
    echo '   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>' . "\n";
    echo '   <Interior ss:Color="#4F46E5" ss:Pattern="Solid"/>' . "\n";
    echo '  </Style>' . "\n";
    echo ' </Styles>' . "\n";

    // 4. Output tables as sheets
    foreach ($tables as $tableName) {
        // Limit sheet name to 31 chars (Excel limit) and clean characters
        $sheetName = substr($tableName, 0, 31);
        $sheetName = str_replace(['\\', '/', '?', '*', '[', ']'], '', $sheetName);

        // Fetch all rows
        $rows = $db->query("SELECT * FROM `$tableName` LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
        
        // Get column headers dynamically
        if (!empty($rows)) {
            $headers = array_keys($rows[0]);
        } else {
            $headers = $db->query("DESCRIBE `$tableName`")->fetchAll(PDO::FETCH_COLUMN);
        }

        echo ' <Worksheet ss:Name="' . xmlEscape($sheetName) . '">' . "\n";
        echo '  <Table>' . "\n";
        
        // Header Row
        echo '   <Row>' . "\n";
        foreach ($headers as $header) {
            echo '    <Cell ss:StyleID="Header"><Data ss:Type="String">' . xmlEscape($header) . '</Data></Cell>' . "\n";
        }
        echo '   </Row>' . "\n";

        // Data Rows
        foreach ($rows as $row) {
            echo '   <Row>' . "\n";
            foreach ($row as $val) {
                if (is_numeric($val) && !preg_match('/^0[0-9]+/', $val)) {
                    echo '    <Cell><Data ss:Type="Number">' . $val . '</Data></Cell>' . "\n";
                } else {
                    echo '    <Cell><Data ss:Type="String">' . xmlEscape($val) . '</Data></Cell>' . "\n";
                }
            }
            echo '   </Row>' . "\n";
        }

        echo '  </Table>' . "\n";
        echo ' </Worksheet>' . "\n";
    }

    echo '</Workbook>' . "\n";
    exit;

} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Export failed: " . $e->getMessage();
    exit;
}
