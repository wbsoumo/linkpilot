<?php
// backend/api/read_log.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt_helper.php';

// Require Admin authorization
JWTHelper::requireAdmin();

header('Content-Type: text/plain');
$logFile = __DIR__ . '/debug.log';
if (file_exists($logFile)) {
    $fileSize = filesize($logFile);
    $maxBytes = 1024 * 1024; // 1MB
    
    if ($fileSize > $maxBytes) {
        $fp = fopen($logFile, 'r');
        if ($fp) {
            fseek($fp, -$maxBytes, SEEK_END);
            // Discard the first line fragment
            fgets($fp);
            echo "[LOG TRUNCATED - showing last 1MB of logs]\n\n";
            while (!feof($fp)) {
                echo fread($fp, 8192);
            }
            fclose($fp);
        } else {
            echo "Failed to open debug.log.";
        }
    } else {
        echo file_get_contents($logFile);
    }
} else {
    echo "No debug.log file found yet.";
}
