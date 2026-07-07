<?php
// backend/api/whatsapp/view_log.php
header('Content-Type: text/plain; charset=utf-8');

echo "=== WHATSAPP SYSTEM DEBUGLOG ===\n";
$debugLog = __DIR__ . '/whatsapp_debug.log';
if (file_exists($debugLog)) {
    echo file_get_contents($debugLog);
} else {
    echo "No whatsapp_debug.log file found.\n";
}

echo "\n=== RAW REQUEST LOG ===\n";
$reqLog = __DIR__ . '/request_log.txt';
if (file_exists($reqLog)) {
    echo file_get_contents($reqLog);
} else {
    echo "No request_log.txt file found.\n";
}

echo "\n=== AI SUGGESTED REPLY DEBUGLOG ===\n";
$aiLog = __DIR__ . '/../../ai_debug.log';
if (file_exists($aiLog)) {
    echo file_get_contents($aiLog);
} else {
    echo "No ai_debug.log file found.\n";
}
