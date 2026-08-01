<?php
// backend/read_debug_log.php
header('Content-Type: text/plain');

$logFile = __DIR__ . '/api_debug.log';
if (!file_exists($logFile)) {
    die("Debug log file not found. Please try to generate the email in the extension again to generate logs.\n");
}

echo "--- OpenRouter API Debug Log ---\n\n";
echo file_get_contents($logFile);
