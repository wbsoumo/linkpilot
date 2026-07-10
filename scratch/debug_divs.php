<?php
$html = file_get_contents('index.html');
$lines = explode("\n", $html);

$start = 3053; // index 3052 (0-indexed)
$end = 6360;

$openDivs = 0;
for ($i = $start - 1; $i < $end; $i++) {
    $line = $lines[$i];
    $openCount = substr_count($line, '<div');
    $closeCount = substr_count($line, '</div>');
    
    $openDivs += $openCount;
    $openDivs -= $closeCount;
    
    if ($openCount > 0 || $closeCount > 0) {
        echo "Line " . ($i + 1) . ": Open +$openCount, Close -$closeCount, Balance: $openDivs | " . trim($line) . "\n";
    }
}
