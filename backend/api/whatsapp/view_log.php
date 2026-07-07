<?php
// backend/api/whatsapp/view_log.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

function tail_file($filepath, $lines = 100) {
    if (!file_exists($filepath)) return "File not found.\n";
    $f = fopen($filepath, "r");
    if (!$f) return "Could not open file.\n";
    
    // Quick size check to avoid excessive loops on huge files
    $size = filesize($filepath);
    if ($size < 4096) {
        $data = file_get_contents($filepath);
        fclose($f);
        return $data;
    }
    
    // Read backwards
    $pos = -2;
    $lineCount = 0;
    
    while ($lineCount < $lines && fseek($f, $pos, SEEK_END) !== -1) {
        $char = fgetc($f);
        if ($char === "\n") {
            $lineCount++;
        }
        $pos--;
    }
    
    $data = "";
    while (!feof($f)) {
        $data .= fgets($f);
    }
    fclose($f);
    return $data;
}

echo "=== DIRECT SEND DIAGNOSTIC RESULTS (Last 100 lines) ===\n";
echo tail_file(__DIR__ . '/wa_temp_debug.txt', 100);
echo "\n";

echo "=== WHATSAPP SYSTEM DEBUGLOG (Last 100 lines) ===\n";
echo tail_file(__DIR__ . '/whatsapp_debug.log', 100);
echo "\n";

echo "=== RAW REQUEST LOG (Last 100 lines) ===\n";
echo tail_file(__DIR__ . '/request_log.txt', 100);
echo "\n";

echo "=== AI SUGGESTED REPLY DEBUGLOG (Last 100 lines) ===\n";
echo tail_file(__DIR__ . '/../../ai_debug.log', 100);
echo "\n";
