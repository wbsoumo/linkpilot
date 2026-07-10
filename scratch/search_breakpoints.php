<?php
$dir = __DIR__ . '/../assets/landing/css';
$files = scandir($dir);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $path = "$dir/$file";
    $content = file_get_contents($path);
    
    // Find all media query min-widths/max-widths
    preg_match_all('/@media\s*\([^)]+\)/i', $content, $matches);
    if (!empty($matches[0])) {
        echo "Breakpoints in $file:\n";
        $unique = array_unique($matches[0]);
        foreach ($unique as $mq) {
            echo "  $mq\n";
        }
    }
}
