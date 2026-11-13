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

// 4. Test Model Queries
echo "\n--- TESTING MODEL QUERIES ---\n";

foreach ($candidates as $c) {
    $prov = $c['provider'];
    $key = $c['api_key'];

    if ($prov === 'google_ai_studio') {
        echo "\nListing models for Google AI Studio...\n";
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($key));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "Google models HTTP Code: $httpCode\n";
        $data = json_decode($res, true);
        if (!empty($data['models'])) {
            $modelNames = array_map(function($m) { return str_replace('models/', '', $m['name']); }, $data['models']);
            echo "Available Google models: " . implode(', ', array_slice($modelNames, 0, 15)) . "\n";
        } else {
            echo "Google response: " . substr($res, 0, 300) . "\n";
        }
    }

    if ($prov === 'groq') {
        echo "\nListing models for Groq...\n";
        $ch = curl_init("https://api.groq.com/openai/v1/models");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $key"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "Groq models HTTP Code: $httpCode\n";
        $data = json_decode($res, true);
        if (!empty($data['data'])) {
            $modelNames = array_map(function($m) { return $m['id']; }, $data['data']);
            echo "Available Groq models: " . implode(', ', array_slice($modelNames, 0, 15)) . "\n";
        } else {
            echo "Groq response: " . substr($res, 0, 300) . "\n";
        }
    }
}

echo "\n=== END DIAGNOSTIC ===\n";
