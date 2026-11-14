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

// Direct GitHub Sync handler with cache-busting
if (isset($_GET['sync'])) {
    echo "=== DIRECT GITHUB SYNC ===\n";
    $syncFiles = [
        'backend/agent_orchestrator.php',
        'backend/ai_tool_registry.php',
        'backend/api/crm/agent.php',
        'dashboard/assets/js/agent.js',
        'backend/ai_diagnostic.php'
    ];
    $root = dirname(__DIR__);
    $baseUrl = "https://raw.githubusercontent.com/wbsoumo/linkpilot/main/";
    $synced = 0;
    foreach ($syncFiles as $f) {
        $ch = curl_init($baseUrl . $f . '?_t=' . time());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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
        } else {
            echo "Failed $f (HTTP $code)\n";
        }
    }
    if (file_exists($root . '/.htaccess')) @touch($root . '/.htaccess');
    echo "Sync finished. $synced files updated.\n\n";
}

$db = Database::getConnection();
$adminId = 1;

// 0. Auto-heal DB records for active AI models
try {
    $db->exec("UPDATE users SET active_ai_model = 'gemini-flash-lite-latest' WHERE role = 'admin' OR active_ai_model LIKE 'gemini%'");
    $db->exec("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE provider = 'google_ai_studio'");
    if (class_exists('AgentCache')) {
        AgentCache::flush();
    }
    // Also delete any tmp cache files directly
    $tmpDir = sys_get_temp_dir() . '/linkpilot_agent_cache';
    if (is_dir($tmpDir)) {
        foreach (glob($tmpDir . '/*') as $f) @unlink($f);
    }
    echo "[OK] Auto-healed users, user_ai_keys and flushed agent cache.\n";
} catch (Throwable $e) {
    echo "[!] DB heal error: " . $e->getMessage() . "\n";
}

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

// 2b. Check external app connections
$stmtAppConns = $db->prepare("SELECT id, user_id, provider, email, connected_email, scopes, status, updated_at FROM external_app_connections WHERE user_id = ?");
$stmtAppConns->execute([$adminId]);
$appConns = $stmtAppConns->fetchAll(PDO::FETCH_ASSOC);
echo "\nExternal App Connections in DB:\n";
print_r($appConns);

// 3. Resolve candidates using getAllAvailableProviders
require_once __DIR__ . '/agent_ai_client.php';
$candidates = AgentAIClient::getAllAvailableProviders($adminId);
echo "\nResolved Candidates for Admin:\n";
foreach ($candidates as $idx => $c) {
    $maskedKey = substr($c['api_key'], 0, 8) . '...' . substr($c['api_key'], -4);
    echo "[$idx] Provider: {$c['provider']}, Model: {$c['model']}, Key: {$maskedKey}\n";
}

if (isset($_GET['test_providers'])) {
    echo "\n=== DIRECT PROVIDER RAW API TESTS ===\n";
    foreach ($candidates as $idx => $c) {
        $p = $c['provider'];
        $m = $c['model'];
        $k = $c['api_key'];
        echo "Testing [$idx] $p with model $m...\n";
        if ($p === 'google_ai_studio') {
            $testModels = ['gemini-2.5-flash-lite', 'gemini-flash-latest', 'gemini-flash-lite-latest', 'gemini-2.5-flash', 'gemini-3-flash-preview', 'gemini-2.5-pro'];
            foreach ($testModels as $testModel) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$testModel}:generateContent?key=" . urlencode($k);
                $body = json_encode(['contents' => [['parts' => [['text' => 'Hi']]]]]);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                $res = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                echo "  Google ($testModel) -> HTTP $code | " . substr($res, 0, 150) . "\n";
            }
        } elseif ($p === 'groq') {
            $chG = curl_init("https://api.groq.com/openai/v1/models");
            curl_setopt($chG, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $k]);
            curl_setopt($chG, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chG, CURLOPT_TIMEOUT, 6);
            $groqRes = curl_exec($chG);
            $groqData = json_decode($groqRes, true);
            curl_close($chG);
            echo "  Groq Models List: ";
            if (!empty($groqData['data'])) {
                $gNames = array_map(function($x) { return $x['id']; }, $groqData['data']);
                echo implode(', ', array_slice($gNames, 0, 15)) . "\n";
            } else {
                echo substr($groqRes, 0, 150) . "\n";
            }
        } elseif ($p === 'openrouter') {
            $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $k]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['model' => $m, 'messages' => [['role' => 'user', 'content' => 'Hi']], 'max_tokens' => 10]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            echo "  OpenRouter ($m) -> HTTP $code | Response: " . substr($res, 0, 150) . "\n";
        }
    }
}

if (isset($_GET['run_tests'])) {
    // Live Agent Orchestrator Test (WhatsApp query from user screenshot)
    echo "\n--- LIVE AGENT ORCHESTRATOR TEST ('Who sent the latest WhatsApp message?') ---\n";
    require_once __DIR__ . '/agent_orchestrator.php';
    try {
        $conv = AgentOrchestrator::createConversation($adminId, 'Diag Test WhatsApp', $db);
        $convId = $conv['id'] ?? uniqid('conv_');
        $t0 = microtime(true);
        $orchRes = AgentOrchestrator::processChat($adminId, $convId, 'Who sent the latest WhatsApp message?', [], null, $db);
        $orchTook = round(microtime(true) - $t0, 2);
        echo "Orchestrator responded in {$orchTook}s:\n";
        echo "Status: " . ($orchRes['status'] ?? 'unknown') . "\n";
        $replyText = $orchRes['content'] ?? ($orchRes['message']['content'] ?? 'NO CONTENT');
        echo "Reply: " . $replyText . "\n";
        if (!empty($orchRes['steps'])) {
            echo "Steps executed: " . count($orchRes['steps']) . "\n";
            foreach ($orchRes['steps'] as $s) {
                echo "  - Step tool: " . ($s['tool_name'] ?? $s['tool_id'] ?? 'unknown') . "\n";
            }
        }
    } catch (Throwable $e) {
        echo "Orchestrator Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    }

    // Live Agent Orchestrator Test (Email query from user screenshot)
    echo "\n--- LIVE AGENT ORCHESTRATOR TEST ('send a mail to soumojit.200@gmail.com about his 2000 rs pending payment') ---\n";
    try {
        $convEmail = AgentOrchestrator::createConversation($adminId, 'Diag Test Email', $db);
        $convEmailId = $convEmail['id'] ?? uniqid('conv_');
        $t0 = microtime(true);
        $orchEmail = AgentOrchestrator::processChat($adminId, $convEmailId, 'send a mail to soumojit.200@gmail.com about his 2000 rs pending payment', [], null, $db);
        $orchTook = round(microtime(true) - $t0, 2);
        echo "Email test responded in {$orchTook}s:\n";
        echo "Status: " . ($orchEmail['status'] ?? 'unknown') . "\n";
        $replyText = $orchEmail['content'] ?? ($orchEmail['message']['content'] ?? 'NO CONTENT');
        echo "Reply: " . $replyText . "\n";
        if (!empty($orchEmail['steps'])) {
            echo "Steps executed: " . count($orchEmail['steps']) . "\n";
            foreach ($orchEmail['steps'] as $s) {
                echo "  - Step tool: " . ($s['tool_name'] ?? $s['tool_id'] ?? 'unknown') . "\n";
            }
        }
    } catch (Throwable $e) {
        echo "Email Test Error: " . $e->getMessage() . "\n";
    }

    echo "\n--- LIVE AGENT ORCHESTRATOR TEST ('Generate a Google Meet room link for our upcoming quarterly sales presentation.') ---\n";
    try {
        $convMeet = AgentOrchestrator::createConversation($adminId, 'Diag Test Meet', $db);
        $convMeetId = $convMeet['id'] ?? uniqid('conv_');
        $t0 = microtime(true);
        $orchMeet = AgentOrchestrator::processChat($adminId, $convMeetId, 'Generate a Google Meet room link for our upcoming quarterly sales presentation.', [], null, $db);
        $orchTook = round(microtime(true) - $t0, 2);
        echo "Meet test responded in {$orchTook}s:\n";
        echo "Status: " . ($orchMeet['status'] ?? 'unknown') . "\n";
        $replyText = $orchMeet['content'] ?? ($orchMeet['message']['content'] ?? 'NO CONTENT');
        echo "Reply: " . $replyText . "\n";
        if (!empty($orchMeet['steps'])) {
            echo "Steps executed: " . count($orchMeet['steps']) . "\n";
            foreach ($orchMeet['steps'] as $s) {
                echo "  - Step tool: " . ($s['tool_name'] ?? $s['tool_id'] ?? 'unknown') . "\n";
            }
        }
    } catch (Throwable $e) {
        echo "Meet Test Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n(To run live LLM test chats, pass &run_tests=1 in URL)\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
