<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../../config.php';
    
    $logFile = __DIR__ . '/whatsapp_debug.log';
    echo "Checking file: $logFile\n";
    if (file_exists($logFile)) {
        echo "File size: " . filesize($logFile) . " bytes\n";
        echo "Is readable: " . (is_readable($logFile) ? 'yes' : 'no') . "\n";
        
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
            echo "Could not open log file via fopen.\n";
        }
    } else {
        echo "Log file not found.\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
