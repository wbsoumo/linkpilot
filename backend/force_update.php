<?php
// backend/force_update.php
header('Content-Type: text/plain');
echo "1. Resetting live server changes...\n";
echo shell_exec("git reset --hard origin/main 2>&1");
echo "\n2. Pulling latest code from GitHub...\n";
echo shell_exec("git pull origin main 2>&1");

if (function_exists('opcache_reset')) {
    echo "\n3. Resetting PHP OPcache...\n";
    if (opcache_reset()) {
        echo "OPcache reset successfully!\n";
    } else {
        echo "OPcache reset failed.\n";
    }
} else {
    echo "\n3. OPcache is not enabled or available.\n";
}
