<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../../config.php';
    echo "Hello World! config.php loaded successfully!\n";
    
    // Check if we can read the log file using file_get_contents on a small chunk or fopen
    $logFile = __DIR__ . '/whatsapp_debug.log';
    if (file_exists($logFile)) {
        echo "File size: " . filesize($logFile) . " bytes\n";
        $f = fopen($logFile, 'r');
        if ($f) {
            echo "Successfully opened log file via fopen!\n";
            fclose($f);
        } else {
            echo "Failed to open log file via fopen.\n";
        }
    } else {
        echo "Log file not found.\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
