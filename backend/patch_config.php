<?php
// backend/patch_config.php
header('Content-Type: text/plain');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die("Error: config.php not found at $configFile\n");
}

echo "Reading config.php...\n";
$content = file_get_contents($configFile);

$updated = false;

// Force inject logging to the system temp directory
echo "1. Patching config.php with writable system temp path logging...\n";

$target = '                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $error = curl_error($ch);
                        curl_close($ch);';
                        
$replacement = '                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $error = curl_error($ch);
                        curl_close($ch);

                        // Safe system temp debug logging
                        file_put_contents(sys_get_temp_dir() . \'/api_debug.log\', sprintf(
                            "[%s] Model: %s, HTTP Code: %d, Error: %s, Response: %s\n",
                            date(\'Y-m-d H:i:s\'),
                            $currentModel,
                            $httpCode,
                            $error ?: \'None\',
                            $response ?: \'Empty\'
                        ), FILE_APPEND);';
                        
// Normalize newlines for search
$normalizedContent = str_replace("\r\n", "\n", $content);
$normalizedTarget = str_replace("\r\n", "\n", $target);
$normalizedReplacement = str_replace("\r\n", "\n", $replacement);

// Remove any existing local api_debug.log paths first to update cleanly
$normalizedContent = preg_replace('/\/\/ Debug logging.*?FILE_APPEND\);/s', '', $normalizedContent);

if (strpos($normalizedContent, $normalizedTarget) !== false) {
    $content = str_replace($normalizedTarget, $normalizedReplacement, $normalizedContent);
    $updated = true;
} else {
    echo "   WARNING: Target curl response block not found for debug logging.\n";
}

// 2. Force update OPENROUTER_MODEL to google/gemini-2.5-flash
if (strpos($content, "define('OPENROUTER_MODEL', 'google/gemini-2.5-flash:free')") !== false) {
    echo "2. Updating OPENROUTER_MODEL constant...\n";
    $content = str_replace(
        "define('OPENROUTER_MODEL', 'google/gemini-2.5-flash:free')",
        "define('OPENROUTER_MODEL', 'google/gemini-2.5-flash')",
        $content
    );
    $updated = true;
}

if ($updated) {
    echo "Saving patched config.php...\n";
    if (file_put_contents($configFile, $content) !== false) {
        echo "SUCCESS: config.php patched successfully!\n";
    } else {
        echo "Error: Failed to write patched config.php!\n";
    }
} else {
    echo "config.php is already updated.\n";
}

if (function_exists('opcache_reset')) {
    echo "Resetting PHP OPcache...\n";
    if (opcache_reset()) {
        echo "SUCCESS: OPcache reset successfully!\n";
    } else {
        echo "FAILED: OPcache reset failed.\n";
    }
}
