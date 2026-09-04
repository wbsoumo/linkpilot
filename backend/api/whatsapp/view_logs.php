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

define('LOG_VIEWER_VERSION', 'v1.0.7');

// Read backward in 64KB chunks to find meaningful logs
$chunkSize = 65536;
$pos = $fileSize;
$filteredLines = [];
$leftover = '';

while ($pos > 0 && count($filteredLines) < 100) {
    $readLen = min($chunkSize, $pos);
    $pos -= $readLen;
    fseek($fp, $pos, SEEK_SET);
    $chunk = fread($fp, $readLen) . $leftover;
    
    $chunkLines = explode("\n", $chunk);
    $leftover = array_shift($chunkLines); // first element might be incomplete line
    
    // Reverse to get newest lines first
    $chunkLines = array_reverse($chunkLines);
    
    foreach ($chunkLines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;
        if (strpos($trimmed, 'sendJsonResponse output') !== false) continue;
        if (strpos($trimmed, 'inbox.php') !== false) continue;
        if (strpos($trimmed, 'found 0 pending queue item') !== false) continue;
        if (strpos($trimmed, '"threads":[{') !== false) continue;
        if (strpos($trimmed, '"messages":[{') !== false) continue;
        
        $filteredLines[] = $trimmed;
        if (count($filteredLines) >= 100) break;
    }
}

if ($leftover !== '' && count($filteredLines) < 100) {
    $trimmed = trim($leftover);
    if ($trimmed !== '' && 
        strpos($trimmed, 'sendJsonResponse output') === false && 
        strpos($trimmed, 'inbox.php') === false && 
        strpos($trimmed, 'found 0 pending queue item') === false && 
        strpos($trimmed, '"threads":[{') === false && 
        strpos($trimmed, '"messages":[{') === false) {
        $filteredLines[] = $trimmed;
    }
}

fclose($fp);
$finalLines = array_reverse($filteredLines);

echo "=== LINKPILOT WHATSAPP DEBUG LOGS [" . LOG_VIEWER_VERSION . "] (ENTRIES: " . count($finalLines) . " | FILE SIZE: " . round($fileSize / 1024 / 1024, 2) . " MB) ===\n\n";
if (empty($finalLines)) {
    echo "No matching non-system log entries found in the last read chunks.\n";
} else {
    echo implode("\n\n", $finalLines);
}
