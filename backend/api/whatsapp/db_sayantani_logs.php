<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../../config.php';
    
    $logFile = __DIR__ . '/whatsapp_debug.log';
    if (file_exists($logFile)) {
        $f = fopen($logFile, 'r');
        if ($f) {
            $filtered = [];
            while (($line = fgets($f)) !== false) {
                // Look for Sayantani's phone number or logs around 23:5
                if (strpos($line, '919593501403') !== false || strpos($line, '23:5') !== false || strpos($line, '00:0') !== false) {
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
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
