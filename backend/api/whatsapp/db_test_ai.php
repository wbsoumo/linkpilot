<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: text/plain');

try {
    echo "Running callAI test for user 1...\n";
    $sysPrompt = "You are a helpful assistant.";
    $userPrompt = "Hello! Please reply in 5 words.";
    
    $res = callAI($sysPrompt, $userPrompt, 1);
    echo "SUCCESS!\n";
    print_r($res);
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
