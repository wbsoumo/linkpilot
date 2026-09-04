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

define('LOG_VIEWER_VERSION', 'v1.1.5');

// Read last 16MB chunk to guarantee we reach actual event lines across large JSON entries
$readLen = min($fileSize, 16777216); // 16 MB
fseek($fp, -$readLen, SEEK_END);
$buffer = fread($fp, $readLen);
fclose($fp);

$allLines = explode("\n", $buffer);
if ($readLen < $fileSize) {
    array_shift($allLines); // drop potentially incomplete first line
}

$filteredLines = [];
// Iterate backwards through lines (newest first)
for ($i = count($allLines) - 1; $i >= 0; $i--) {
    $trimmed = trim($allLines[$i]);
    if ($trimmed === '') continue;
    if (strpos($trimmed, 'sendJsonResponse output') !== false) continue;
    if (strpos($trimmed, 'inbox.php') !== false) continue;
    if (strpos($trimmed, 'found 0 pending queue item') !== false) continue;
    if (strpos($trimmed, '"threads":[{') !== false) continue;
    if (strpos($trimmed, '"messages":[{') !== false) continue;
    
    $filteredLines[] = $trimmed;
    if (count($filteredLines) >= 100) break;
}

$finalLines = array_reverse($filteredLines);

echo "=== LINKPILOT WHATSAPP DEBUG LOGS [" . LOG_VIEWER_VERSION . "] (ENTRIES: " . count($finalLines) . " | FILE SIZE: " . round($fileSize / 1024 / 1024, 2) . " MB) ===\n\n";
if (empty($finalLines)) {
    echo "No matching non-system log entries found.\n";
} else {
    echo implode("\n\n", $finalLines);
}

// Check AI Debug Log
$aiLog = __DIR__ . '/../../ai_debug.log';
if (file_exists($aiLog)) {
    echo "\n\n=== RECENT AI ENGINE DEBUGLOG (ai_debug.log) ===\n";
    $aiFp = fopen($aiLog, 'rb');
    fseek($aiFp, 0, SEEK_END);
    $aiSz = ftell($aiFp);
    $aiRead = min($aiSz, 65536);
    if ($aiRead > 0) {
        fseek($aiFp, -$aiRead, SEEK_END);
        echo fread($aiFp, $aiRead);
    }
    fclose($aiFp);
}
exit;
