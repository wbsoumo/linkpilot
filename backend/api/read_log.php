<?php
// backend/api/read_log.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt_helper.php';

// Require Admin authorization
JWTHelper::requireAdmin();

header('Content-Type: text/plain');
$logFile = __DIR__ . '/debug.log';
if (file_exists($logFile)) {
    echo file_get_contents($logFile);
} else {
    echo "No debug.log file found yet.";
}
