<?php
// backend/git_pull.php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$root = dirname(__DIR__);
chdir($root);

echo "Working directory: " . getcwd() . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";

$cmd = "git pull origin main 2>&1";
$output = [];
$executed = false;

if (function_exists('shell_exec')) {
    try {
        $res = @shell_exec($cmd);
        if ($res !== null) {
            echo "shell_exec output:\n" . $res . "\n";
            $executed = true;
        }
    } catch (Throwable $e) {
        echo "shell_exec exception: " . $e->getMessage() . "\n";
    }
}

if (!$executed && function_exists('exec')) {
    try {
        @exec($cmd, $output, $returnVar);
        echo "exec output (code $returnVar):\n" . implode("\n", $output) . "\n";
        $executed = true;
    } catch (Throwable $e) {
        echo "exec exception: " . $e->getMessage() . "\n";
    }
}

if (!$executed && function_exists('passthru')) {
    try {
        echo "passthru output:\n";
        @passthru($cmd);
        $executed = true;
    } catch (Throwable $e) {
        echo "passthru exception: " . $e->getMessage() . "\n";
    }
}

if (!$executed && function_exists('system')) {
    try {
        echo "system output:\n";
        @system($cmd);
        $executed = true;
    } catch (Throwable $e) {
        echo "system exception: " . $e->getMessage() . "\n";
    }
}

if (!$executed) {
    echo "Notice: All CLI execution functions are restricted by hosting environment.\n";
}

// Touch .htaccess to flush LiteSpeed PHP opcache
$htaccess = $root . '/.htaccess';
if (file_exists($htaccess)) {
    @touch($htaccess);
    echo "Flushed LiteSpeed opcache via .htaccess touch.\n";
}
