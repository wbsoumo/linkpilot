<?php
$dir = __DIR__ . '/../assets/landing/css';
$files = scandir($dir);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $path = "$dir/$file";
    $content = file_get_contents($path);
    if (strpos($content, 'xl:hidden') !== false || strpos($content, 'xl\\:hidden') !== false) {
        echo "Found 'xl:hidden' in $file\n";
    }
}
