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

// Memory-efficient log tail: read only the last 512KB without loading large files into RAM
$maxRead = 524288; // 512 KB
$readSize = min($fileSize, $maxRead);

fseek($fp, -$readSize, SEEK_END);
$buffer = fread($fp, $readSize);
fclose($fp);

$lines = explode("\n", $buffer);

$filteredLines = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    // Ignore massive JSON payloads/responses
    if (strpos($trimmed, 'sendJsonResponse output') !== false) continue;
    if (strpos($trimmed, 'inbox.php') !== false) continue;
    if (strpos($trimmed, 'found 0 pending queue item') !== false) continue;
    if (strpos($trimmed, '"messages":[{') !== false) continue;
    if (strpos($trimmed, '{"id":') !== false) continue;
    if (strpos($trimmed, '"threads":[{') !== false) continue;
    
    // Only include timestamped log entries or explicitly debugged lines
    if (preg_match('/^\[202\d-[0-9]{2}-[0-9]{2}/', $trimmed) || strpos($trimmed, 'WhatsApp') !== false || strpos($trimmed, 'Queue') !== false || strpos($trimmed, 'AI') !== false || strpos($trimmed, 'Error') !== false || strpos($trimmed, 'Exception') !== false) {
        $filteredLines[] = $trimmed;
    }
}

$lastLines = array_slice($filteredLines, -100);

echo "=== LINKPILOT WHATSAPP DEBUG LOGS (LAST 100 FILTERED ENTRIES | FILE SIZE: " . round($fileSize / 1024 / 1024, 2) . " MB) ===\n\n";
echo implode("\n", $lastLines);
