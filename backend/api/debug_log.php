<?php
// backend/api/debug_log.php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $logFile = __DIR__ . '/debug.log';
    $entry = "[" . date('Y-m-d H:i:s') . "] " . json_encode($input, JSON_PRETTY_PRINT) . "\n\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

echo json_encode(['status' => 'success']);
