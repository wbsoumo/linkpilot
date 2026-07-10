<?php
$dir = __DIR__ . '/../assets/landing/css';
$files = scandir($dir);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $path = "$dir/$file";
    $content = file_get_contents($path);
    if (strpos($content, 'js-top-menu-burger-button') !== false) {
        echo "Found 'js-top-menu-burger-button' in $file\n";
    }
}
