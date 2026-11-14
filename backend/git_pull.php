<?php
// backend/git_pull.php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$root = dirname(__DIR__);
chdir($root);

echo "Working directory: " . getcwd() . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
$headFile = $root . '/.git/refs/heads/main';
if (file_exists($headFile)) {
    echo "SERVER GIT COMMIT: " . trim(file_get_contents($headFile)) . "\n";
}
echo "DISABLE_FUNCTIONS: " . ini_get('disable_functions') . "\n";

$cmd = "GIT_TERMINAL_PROMPT=0 git pull origin main 2>&1";
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

$configFile = $root . '/.git/config';
if (file_exists($configFile)) {
    echo "GIT CONFIG REMOTE:\n" . file_get_contents($configFile) . "\n";
}

if (!$executed) {
    echo "Notice: All CLI execution functions are restricted by hosting environment.\n";
    echo "Falling back to pure PHP sync from GitHub (wbsoumo/linkpilot@main)...\n";

    $syncFiles = [
        '.cpanel.yml',
        'backend/agent_ai_client.php',
        'backend/agent_orchestrator.php',
        'backend/ai_tool_registry.php',
        'backend/web_search_helper.php',
        'backend/config.php',
        'backend/ai_diagnostic.php',
        'backend/git_pull.php',
        'backend/api/crm/agent.php',
        'dashboard/assets/js/agent.js',
        'dashboard/assets/css/agent.css'
    ];

    // 1. Fetch latest commit SHA from GitHub first for 100% cache-free immutable raw URLs
    $latestSha = 'main';
    try {
        $chCommit = curl_init("https://api.github.com/repos/wbsoumo/linkpilot/commits/main");
        curl_setopt($chCommit, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chCommit, CURLOPT_USERAGENT, "LinkPilot-Updater");
        curl_setopt($chCommit, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chCommit, CURLOPT_TIMEOUT, 3);
        $commitJson = curl_exec($chCommit);
        $commitData = json_decode($commitJson, true);
        if (!empty($commitData['sha'])) {
            $latestSha = trim($commitData['sha']);
            $gitHeadPath = $root . '/.git/refs/heads/main';
            if (is_dir(dirname($gitHeadPath))) {
                @file_put_contents($gitHeadPath, $latestSha . "\n");
                @file_put_contents($root . '/.git/ORIG_HEAD', $latestSha . "\n");
                echo "Latest GitHub commit identified: {$latestSha}\n";
            }
        }
    } catch (Throwable $e) {
        echo "Git ref update notice: " . $e->getMessage() . "\n";
    }

    $baseUrl = "https://raw.githubusercontent.com/wbsoumo/linkpilot/{$latestSha}/";
    $synced = 0;

    foreach ($syncFiles as $file) {
        $url = $baseUrl . $file . '?_t=' . time();
        $target = $root . '/' . $file;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200 && !empty($content)) {
            if (!is_dir(dirname($target))) {
                @mkdir(dirname($target), 0755, true);
            }
            if (@file_put_contents($target, $content) !== false) {
                echo "  [OK] Synced: {$file} (" . strlen($content) . " bytes)\n";
                $synced++;
            } else {
                echo "  [FAIL] Write error: {$file}\n";
            }
        } else {
            echo "  [FAIL] HTTP {$httpCode} for: {$file}\n";
        }
    }
    echo "Pure PHP sync completed: {$synced}/" . count($syncFiles) . " files updated.\n";
}

// Touch .htaccess to flush LiteSpeed PHP opcache
$htaccess = $root . '/.htaccess';
if (file_exists($htaccess)) {
    @touch($htaccess);
    echo "Flushed LiteSpeed opcache via .htaccess touch.\n";
}
