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

clearstatcache();
$fp = @fopen($logFile, 'rb');
if (!$fp) {
    echo "Could not open log file for reading.";
    exit;
}

fseek($fp, 0, SEEK_END);
$fileSize = ftell($fp);
if ($fileSize === 0) {
    echo "Log file exists at " . $logFile . " but is currently empty.";
    fclose($fp);
    exit;
}

// Memory-efficient log tail: read only the last 64KB without loading large files into RAM
$maxRead = 65536; // 64 KB
$readSize = min($fileSize, $maxRead);

fseek($fp, -$readSize, SEEK_END);
$buffer = fread($fp, $readSize);
fclose($fp);

$lines = explode("\n", $buffer);

$filteredLines = [];
foreach ($lines as $line) {
    if (strpos($line, 'sendJsonResponse output') !== false) continue;
    if (strpos($line, 'inbox.php') !== false) continue;
    if (strpos($line, 'found 0 pending queue item') !== false) continue;
    $filteredLines[] = $line;
}

$lastLines = array_slice($filteredLines, -100);

echo "=== LINKPILOT WHATSAPP DEBUG LOGS (LAST 100 FILTERED ENTRIES | FILE SIZE: " . round($fileSize / 1024 / 1024, 2) . " MB) ===\n\n";
echo implode("\n", $lastLines);
