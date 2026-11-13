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

// Query ModelService.ListModels on Google
echo "\n--- CALLING ModelService.ListModels on Google ---\n";
$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($candidates[0]['api_key']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$listRes = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Google ListModels HTTP $code:\n";
$listJson = json_decode($listRes, true);
if (!empty($listJson['models'])) {
    $supported = [];
    foreach ($listJson['models'] as $m) {
        $name = str_replace('models/', '', $m['name']);
        $methods = $m['supportedGenerationMethods'] ?? [];
        if (in_array('generateContent', $methods)) {
            $supported[] = $name;
        }
    }
    echo "Supported generateContent models: " . implode(', ', $supported) . "\n";
} else {
    echo "Google ListModels response: " . substr($listRes, 0, 500) . "\n";
}

// Query Groq models
echo "\n--- CALLING Groq /v1/models ---\n";
$ch = curl_init("https://api.groq.com/openai/v1/models");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $candidates[1]['api_key']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$groqRes = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Groq models HTTP $code:\n";
$groqJson = json_decode($groqRes, true);
if (!empty($groqJson['data'])) {
    $gModels = array_map(function($m) { return $m['id']; }, $groqJson['data']);
    echo "Available Groq models: " . implode(', ', array_slice($gModels, 0, 20)) . "\n";
} else {
    echo "Groq response: " . substr($groqRes, 0, 500) . "\n";
}

// Test OpenRouter with suggested slug: meta-llama/llama-3.1-8b-instruct
echo "\n--- TEST OpenRouter with meta-llama/llama-3.1-8b-instruct ---\n";
$ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $candidates[2]['api_key'],
    "Content-Type: application/json",
    "HTTP-Referer: https://linkpilot.work",
    "X-Title: LinkPilot AI Agent"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'meta-llama/llama-3.1-8b-instruct',
    'messages' => [['role' => 'user', 'content' => 'hello']],
    'max_tokens' => 20
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$orRes = curl_exec($ch);
$orCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "OpenRouter test HTTP $orCode:\n";
echo "Response: " . substr($orRes, 0, 500) . "\n";


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
