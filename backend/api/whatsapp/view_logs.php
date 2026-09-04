<?php
// backend/api/whatsapp/view_logs.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/whatsapp_debug.log';

if (!file_exists($logFile)) {
    echo "No debug log file created yet at: " . $logFile;
    exit;
}

$content = @file_get_contents($logFile);
if ($content === false) {
    echo "Log file exists at " . $logFile . " but could not be read.";
    exit;
}

if (trim($content) === '') {
    echo "Log file exists at " . $logFile . " but is currently empty.";
    exit;
}

$lines = explode("\n", $content);
$lastLines = array_slice($lines, -250);

echo "=== LINKPILOT WHATSAPP DEBUG LOGS (LAST 250 ENTRIES) ===\n\n";
echo implode("\n", $lastLines);
