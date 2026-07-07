<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: text/plain');

$logFile = __DIR__ . '/whatsapp_debug.log';
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    // Find all lines containing "919593501403" or "23:54" or "23:55"
    $lines = explode("\n", $content);
    $filtered = [];
    foreach ($lines as $line) {
        if (strpos($line, '919593501403') !== false || strpos($line, '23:54') !== false || strpos($line, '23:55') !== false || strpos($line, '23:50') !== false) {
            $filtered[] = $line;
        }
    }
    echo "=== FILTERED WHATSAPP DEBUG LOG ===\n";
    print_r(array_slice($filtered, -20));
} else {
    echo "Log file not found.\n";
}
