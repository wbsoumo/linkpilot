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

// 4. Test each candidate individually with exact response
echo "\n--- INDIVIDUAL CANDIDATE TESTS ---\n";
foreach ($candidates as $idx => $c) {
    $prov = $c['provider'];
    $model = $c['model'];
    $key = $c['api_key'];
    $maskedKey = substr($key, 0, 8) . '...' . substr($key, -4);
    echo "\n[Test $idx] Provider: $prov | Model: $model | Key: $maskedKey\n";

    $endpoint = "https://openrouter.ai/api/v1/chat/completions";
    $headers = [
        "Authorization: Bearer " . $key,
        "Content-Type: application/json"
    ];

    if ($prov === 'github_models') {
        $endpoint = "https://models.inference.ai.azure.com/chat/completions";
        $headers[] = "User-Agent: LinkPilot-AI";
    } elseif ($prov === 'google_ai_studio') {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions";
    } elseif ($prov === 'groq') {
        $endpoint = "https://api.groq.com/openai/v1/chat/completions";
    } else {
        $headers[] = "HTTP-Referer: https://linkpilot.work";
        $headers[] = "X-Title: LinkPilot AI Agent";
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => 'hi']],
        'max_tokens' => 20
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $t0 = microtime(true);
    $res = curl_exec($ch);
    $took = round(microtime(true) - $t0, 2);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    echo "  Time: {$took}s | HTTP: {$httpCode}\n";
    if ($err) echo "  cURL Error: {$err}\n";
    echo "  Response: " . substr($res, 0, 400) . "\n";

    // If Google, also test native v1beta endpoint
    if ($prov === 'google_ai_studio') {
        echo "  [Google Native Endpoint Test]:\n";
        $nativeUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($key);
        $ch2 = curl_init($nativeUrl);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
            "contents" => [["parts" => [["text" => "hi"]]]]
        ]));
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
        $res2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $err2 = curl_error($ch2);
        curl_close($ch2);
        echo "  Native HTTP: {$httpCode2}\n";
        if ($err2) echo "  Native Error: {$err2}\n";
        echo "  Native Response: " . substr($res2, 0, 400) . "\n";
    }
}

// 5. Test all rows in user_ai_keys
echo "\n--- ALL USER_AI_KEYS TABLE ROWS ---\n";
$stmtAll = $db->query("SELECT id, user_id, provider, status, api_key FROM user_ai_keys ORDER BY id ASC");
$allKeys = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
foreach ($allKeys as $kRow) {
    $dec = decryptData($kRow['api_key']);
    $finalKey = ($dec !== false) ? $dec : $kRow['api_key'];
    $mKey = substr($finalKey, 0, 8) . '...' . substr($finalKey, -4);
    echo "ID: {$kRow['id']} | Provider: {$kRow['provider']} | Status: {$kRow['status']} | Key: {$mKey}\n";
}

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
