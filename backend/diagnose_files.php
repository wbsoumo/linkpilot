<?php
// backend/diagnose_files.php
header('Content-Type: text/plain');

echo "--- Live Server Dashboard Files Diagnostics ---\n\n";

$dashboardDir = __DIR__ . '/../dashboard';
$loginFile = $dashboardDir . '/login.html';

// 1. Check dashboard directory
if (file_exists($dashboardDir)) {
    echo "Dashboard Directory: EXISTS\n";
    echo "Readable by Web Server: " . (is_readable($dashboardDir) ? "YES" : "NO") . "\n";
    echo "Writable by Web Server: " . (is_writable($dashboardDir) ? "YES" : "NO") . "\n";
    echo "Permissions (Octal): " . substr(sprintf('%o', fileperms($dashboardDir)), -4) . "\n";
    echo "Owner ID: " . fileowner($dashboardDir) . " (" . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($dashboardDir))['name'] : 'unknown') . ")\n";
    echo "Group ID: " . filegroup($dashboardDir) . " (" . (function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($dashboardDir))['name'] : 'unknown') . ")\n";
} else {
    echo "Dashboard Directory: NOT FOUND at $dashboardDir\n";
}

echo "\n----------------------------------------\n\n";

// 2. Check login.html
if (file_exists($loginFile)) {
    echo "login.html File: EXISTS\n";
    echo "Readable by Web Server: " . (is_readable($loginFile) ? "YES" : "NO") . "\n";
    echo "Permissions (Octal): " . substr(sprintf('%o', fileperms($loginFile)), -4) . "\n";
    echo "Owner ID: " . fileowner($loginFile) . " (" . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($loginFile))['name'] : 'unknown') . ")\n";
    echo "Group ID: " . filegroup($loginFile) . " (" . (function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($loginFile))['name'] : 'unknown') . ")\n";
    echo "Size: " . filesize($loginFile) . " bytes\n";
} else {
    echo "login.html File: NOT FOUND at $loginFile\n";
}

echo "\n----------------------------------------\n\n";

// 3. Check for any .htaccess file in dashboard
$htaccessFile = $dashboardDir . '/.htaccess';
if (file_exists($htaccessFile)) {
    echo ".htaccess in dashboard: EXISTS\n";
    echo "Contents:\n";
    echo file_get_contents($htaccessFile);
} else {
    echo ".htaccess in dashboard: NOT FOUND\n";
}
