<?php
// backend/test_github_token.php
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

$db = Database::getConnection();
try {
    // Fetch the active github_models key for Admin (User ID 1)
    $stmt = $db->prepare("
        SELECT id, api_key 
        FROM user_ai_keys 
        WHERE user_id = 1 AND provider = 'github_models' AND status != 'paused'
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute();
    $keyRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$keyRow) {
        // Fallback to config GITHUB_TOKEN
        $apiKey = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '';
        $source = "config.php (constant GITHUB_TOKEN)";
    } else {
        $rawKey = $keyRow['api_key'];
        $decrypted = decryptData($rawKey);
        $apiKey = ($decrypted !== false) ? $decrypted : $rawKey;
        $source = "Database Key ID: {$keyRow['id']}";
    }
    
    if (empty($apiKey) || strpos($apiKey, 'placeholder') !== false) {
        throw new Exception("No GitHub token configured. Please configure it in your settings.");
    }
    
    echo "--- GitHub Models Token Tester ---\n";
    echo "Source of Token: $source\n";
    echo "Token Prefix: " . substr($apiKey, 0, 15) . "...\n";
    echo "Token Length: " . strlen($apiKey) . " characters\n\n";
    
    echo "Sending test request to GitHub Models API...\n";
    
    $headers = [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
        "User-Agent: LinkPilot-AI"
    ];

    $postFields = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "user", "content" => "Ping. Reply with: Pong."]
        ],
        "max_tokens" => 10
    ];

    $ch = curl_init("https://models.inference.ai.azure.com/chat/completions");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true); // Include response headers in output
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "Connection Error: $error\n";
        exit;
    }

    echo "HTTP Status Code: $httpCode\n\n";
    echo "--- RAW RESPONSE (Headers & Body) ---\n";
    echo $response;
    echo "\n-------------------------------------\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
