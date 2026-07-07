<?php
header('Content-Type: text/plain');
$configPath = __DIR__ . '/../backend/config.php';
if (!file_exists($configPath)) {
    echo "config.php not found at: $configPath\n";
    exit;
}

$content = file_get_contents($configPath);
echo "File size: " . strlen($content) . "\n";

// Match database defines
preg_match_all('/define\s*\(\s*[\'"](DB_[^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i', $content, $matches);
if (!empty($matches[1])) {
    for ($i = 0; $i < count($matches[1]); $i++) {
        echo $matches[1][$i] . " => " . $matches[2][$i] . "\n";
    }
} else {
    echo "No DB defines matched.\n";
}

// Let's also print line count and first 50 lines
$lines = explode("\n", $content);
echo "Total lines: " . count($lines) . "\n";
echo "First 40 lines:\n";
echo implode("\n", array_slice($lines, 0, 40)) . "\n";
