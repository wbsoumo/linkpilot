<?php
$html = file_get_contents('index.html');
$lines = explode("\n", $html);

for ($i = 2953; $i < 7033; $i++) {
    if (isset($lines[$i]) && strpos($lines[$i], '</nav>') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($lines[$i]) . "\n";
    }
}
