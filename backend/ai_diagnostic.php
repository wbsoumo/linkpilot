<?php
// backend/ai_diagnostic.php
header('Content-Type: text/plain; charset=utf-8');

$token = $_GET['token'] ?? '';
if ($token !== 'LP_DIAGNOSTIC_9823') {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/agent_ai_client.php';
require_once __DIR__ . '/ai_tool_registry.php';

$db = Database::getConnection();

echo "=== LINKPILOT AI DIAGNOSTIC ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check Super Admin User
$stmt = $db->query("SELECT id, name, email, role, active_ai_provider, active_ai_model, CASE WHEN google_key IS NOT NULL AND google_key != '' THEN 1 ELSE 0 END as has_google, CASE WHEN openrouter_key IS NOT NULL AND openrouter_key != '' THEN 1 ELSE 0 END as has_or, CASE WHEN github_key IS NOT NULL AND github_key != '' THEN 1 ELSE 0 END as has_gh FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Admin Record:\n";
print_r($admin);

$adminId = $admin['id'] ?? 1;

// 2. Check user_ai_keys table
$stmtKeys = $db->prepare("SELECT id, user_id, provider, status, error_message, created_at, updated_at FROM user_ai_keys WHERE user_id = ?");
$stmtKeys->execute([$adminId]);
$aiKeys = $stmtKeys->fetchAll(PDO::FETCH_ASSOC);
echo "\nUser AI Keys in DB:\n";
print_r($aiKeys);

// 3. Resolve candidates using getAllAvailableProviders
$candidates = AgentAIClient::getAllAvailableProviders($adminId);
echo "\nResolved Candidates for Admin:\n";
foreach ($candidates as $idx => $c) {
    $maskedKey = substr($c['api_key'], 0, 8) . '...' . substr($c['api_key'], -4);
    echo "[$idx] Provider: {$c['provider']}, Model: {$c['model']}, Key: {$maskedKey}\n";
}

// 4. Test Chat Execution via AgentAIClient::callChat
echo "\n--- TESTING FULL AgentAIClient::callChat() ---\n";
$start = microtime(true);
$res = AgentAIClient::callChat([
    ['role' => 'user', 'content' => 'hello, reply with one short sentence']
], [], $adminId);
$elapsed = round(microtime(true) - $start, 2);
echo "Elapsed Time: {$elapsed}s\n";
echo "Response Content: " . ($res['content'] ?? 'EMPTY') . "\n";
echo "Tokens Used: " . ($res['tokens_used'] ?? 0) . "\n";

// 5. Optional file sync trigger
if (isset($_GET['sync'])) {
    echo "\n--- DIRECT GITHUB SYNC ---\n";
    $syncFiles = [
        'backend/agent_ai_client.php',
        'backend/agent_orchestrator.php',
        'backend/ai_tool_registry.php',
        'backend/web_search_helper.php',
        'backend/config.php',
        'backend/git_pull.php',
        'backend/ai_diagnostic.php'
    ];
    $root = dirname(__DIR__);
    $baseUrl = "https://raw.githubusercontent.com/wbsoumo/linkpilot/main/";
    $synced = 0;
    foreach ($syncFiles as $f) {
        $ch = curl_init($baseUrl . $f);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $c = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && !empty($c)) {
            $dest = $root . '/' . $f;
            if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0755, true);
            if (@file_put_contents($dest, $c) !== false) {
                echo "Synced: $f (" . strlen($c) . " bytes)\n";
                $synced++;
            }
        }
    }
    if (file_exists($root . '/.htaccess')) @touch($root . '/.htaccess');
    echo "Sync finished. $synced files updated.\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
