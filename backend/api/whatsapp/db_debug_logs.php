<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: text/plain');

$logFile = __DIR__ . '/whatsapp_debug.log';
if (file_exists($logFile)) {
    $f = fopen($logFile, 'r');
    if ($f) {
        $filtered = [];
        while (($line = fgets($f)) !== false) {
            if (strpos($line, '919593501403') !== false || strpos($line, '23:54') !== false || strpos($line, '23:55') !== false || strpos($line, '23:50') !== false) {
                $filtered[] = $line;
                if (count($filtered) > 100) {
                    array_shift($filtered);
                }
            }
        }
        fclose($f);
        echo "=== FILTERED WHATSAPP DEBUG LOG ===\n";
        print_r($filtered);
    } else {
        echo "Could not open log file.\n";
    }
} else {
    echo "Log file not found.\n";
}
