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

// 4. Test Chat Call directly
echo "\n--- TESTING DIRECT CHAT CALL ---\n";
$messages = [
    ['role' => 'user', 'content' => 'hello, reply with one word test']
];

$tools = AIToolRegistry::getToolsForMessage('hello');
echo "Filtered Tools for 'hello': " . count($tools) . " tools\n";

foreach ($candidates as $c) {
    $prov = $c['provider'];
    $model = $c['model'];
    $key = $c['api_key'];
    echo "\nTesting Candidate {$prov} ({$model})...\n";

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
        'messages' => $messages,
        'max_tokens' => 50
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);

    echo "HTTP Code: {$httpCode}\n";
    if ($err) echo "cURL Error: {$err}\n";
    echo "Response: " . substr($res, 0, 500) . "\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
