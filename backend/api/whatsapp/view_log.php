<?php
// backend/api/whatsapp/view_log.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

function tail_file($filepath, $lines = 100, $excludePatterns = []) {
    if (!file_exists($filepath)) return "File not found.\n";
    
    $f = fopen($filepath, "r");
    if (!$f) return "Could not open file.\n";
    
    $bufferSize = 4096;
    fseek($f, 0, SEEK_END);
    $pos = ftell($f);
    
    $resultLines = [];
    $currentLine = "";
    
    while ($pos > 0 && count($resultLines) < $lines) {
        $readSize = min($bufferSize, $pos);
        $pos -= $readSize;
        fseek($f, $pos, SEEK_SET);
        $chunk = fread($f, $readSize);
        
        for ($i = strlen($chunk) - 1; $i >= 0; $i--) {
            $char = $chunk[$i];
            if ($char === "\n") {
                if ($currentLine !== "") {
                    $reversedLine = strrev($currentLine);
                    $exclude = false;
                    foreach ($excludePatterns as $pattern) {
                        if (strpos($reversedLine, $pattern) !== false) {
                            $exclude = true;
                            break;
                        }
                    }
                    if (!$exclude) {
                        $resultLines[] = $reversedLine;
                    }
                    $currentLine = "";
                }
            } else {
                $currentLine .= $char;
            }
            if (count($resultLines) >= $lines) {
                break;
            }
        }
    }
    if ($currentLine !== "" && count($resultLines) < $lines) {
        $reversedLine = strrev($currentLine);
        $exclude = false;
        foreach ($excludePatterns as $pattern) {
            if (strpos($reversedLine, $pattern) !== false) {
                $exclude = true;
                break;
            }
        }
        if (!$exclude) {
            $resultLines[] = $reversedLine;
        }
    }
    fclose($f);
    return implode("\n", array_reverse($resultLines)) . "\n";
}

echo "=== DIRECT SEND DIAGNOSTIC RESULTS (Last 100 lines) ===\n";
echo tail_file(__DIR__ . '/wa_temp_debug.txt', 100);
echo "\n";

echo "=== WHATSAPP SYSTEM DEBUGLOG (Filtered - Last 100 lines) ===\n";
echo tail_file(__DIR__ . '/whatsapp_debug.log', 100, ['inbox.php', 'sendJsonResponse']);
echo "\n";

echo "=== RAW REQUEST LOG (Filtered - Last 100 lines) ===\n";
echo tail_file(__DIR__ . '/request_log.txt', 100, ['inbox.php', 'METHOD=GET']);
echo "\n";

echo "=== AI SUGGESTED REPLY DEBUGLOG (Last 100 lines) ===\n";
echo tail_file(__DIR__ . '/../../ai_debug.log', 100);
echo "\n";
