<?php
// backend/api/whatsapp/view_logs.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/whatsapp_debug.log';

if (!file_exists($logFile)) {
    echo "No debug logs recorded yet at " . $logFile;
    exit;
}

$lines = file($logFile);
$lastLines = array_slice($lines, -150);

echo "--- LINKPILOT WHATSAPP DEBUG LOGS (LAST 150 ENTRIES) ---\n\n";
echo implode("", $lastLines);
