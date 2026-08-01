<?php
// backend/test_openrouter_token.php
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

$db = Database::getConnection();
try {
    // 1. Fetch Key ID 1 details
    $stmt = $db->prepare("SELECT api_key FROM user_ai_keys WHERE id = 1");
    $stmt->execute();
    $keyRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$keyRow) {
        throw new Exception("OpenRouter Key ID 1 not found in database.");
    }
    
    $rawKey = $keyRow['api_key'];
    $decrypted = decryptData($rawKey);
    $apiKey = ($decrypted !== false) ? $decrypted : $rawKey;
    
    echo "--- OpenRouter Token Tester & Recovery Tool ---\n";
    echo "Testing Key ID 1...\n";
    echo "Token Length: " . strlen($apiKey) . " characters\n";
    echo "Testing model: google/gemini-2.5-flash:free\n\n";
    
    $headers = [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
        "HTTP-Referer: https://linkpilot.work",
        "X-Title: LinkPilot AI"
    ];

    $postFields = [
        "model" => "google/gemini-2.5-flash:free",
        "messages" => [
            ["role" => "user", "content" => "Ping. Reply with: Pong."]
        ],
        "max_tokens" => 10
    ];

    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("Connection Error: $error");
    }

    echo "HTTP Status Code: $httpCode\n";
    echo "Response:\n$response\n\n";
    
    $data = json_decode($response, true);
    if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
        echo "SUCCESS! OpenRouter connection is fully working.\n\n";
        
        // Recover key status in DB
        $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = 1");
        $stmtUpdate->execute();
        
        // Switch active provider to openrouter
        $stmtUser = $db->prepare("UPDATE users SET active_ai_provider = 'openrouter', active_ai_model = 'google/gemini-2.5-flash:free' WHERE id = 1");
        $stmtUser->execute();
        
        echo "Recovered Settings:\n";
        echo "1. Set OpenRouter Key ID 1 status to 'active'.\n";
        echo "2. Set active provider to 'openrouter' with model 'google/gemini-2.5-flash:free'.\n";
        echo "\nTry using your extension now!";
    } else {
        echo "FAILED: OpenRouter returned an error. Please verify your OpenRouter API key credits or validity.";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
