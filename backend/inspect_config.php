<?php
// backend/inspect_config.php
header('Content-Type: text/plain');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die("Error: config.php not found at $configFile\n");
}

$lines = file($configFile);
echo "--- Live Server config.php Inspection ---\n\n";

echo "=== AI Constants (Lines 40 to 80) ===\n";
for ($i = 40; $i < min(count($lines), 80); $i++) {
    printf("%03d: %s", $i + 1, $lines[$i]);
}

echo "\n\n=== callAI Function Structure ===\n";
$inCallAI = false;
$lineNum = 1;
foreach ($lines as $line) {
    if (strpos($line, 'function callAI') !== false) {
        $inCallAI = true;
    }
    if ($inCallAI) {
        printf("%03d: %s", $lineNum, $line);
    }
    $lineNum++;
}
