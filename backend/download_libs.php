<?php
// backend/download_libs.php

$libDir = __DIR__ . '/libs/PHPMailer/';
if (!is_dir($libDir)) {
    mkdir($libDir, 0777, true);
}

$files = [
    'PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
    'SMTP.php'      => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
    'Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'
];

echo "Starting library downloads...\n";

foreach ($files as $name => $url) {
    $targetPath = $libDir . $name;
    echo "Downloading {$name} from {$url}...\n";
    
    // Download using file_get_contents or curl
    $content = @file_get_contents($url);
    if ($content === false) {
        // Fallback to Curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $content = curl_exec($ch);
        curl_close($ch);
    }
    
    if ($content) {
        file_put_contents($targetPath, $content);
        echo "Successfully downloaded {$name} to {$targetPath}.\n";
    } else {
        echo "Failed to download {$name}.\n";
    }
}

echo "Downloads completed!\n";
