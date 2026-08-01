<?php
// backend/reset_opcache.php
header('Content-Type: text/plain');

if (function_exists('opcache_reset')) {
    echo "Resetting PHP OPcache...\n";
    if (opcache_reset()) {
        echo "SUCCESS: OPcache reset successfully!\n";
    } else {
        echo "FAILED: OPcache reset failed.\n";
    }
} else {
    echo "OPcache is not enabled or available on this server.\n";
}
