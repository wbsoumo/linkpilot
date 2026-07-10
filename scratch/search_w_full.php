<?php
$html = file_get_contents('index.html');
$lines = explode("\n", $html);

$navStart = 2950;
$navEnd = 7040;

for ($i = $navStart - 1; $i < $navEnd; $i++) {
    if (isset($lines[$i]) && strpos($lines[$i], 'w-full') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($lines[$i]) . "\n";
    }
}
