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
    echo "Token Length: " . strlen($apiKey) . " characters\n\n";
    
    // List of models to test in order of preference
    $modelsToTest = [
        "google/gemini-2.5-flash", // Paid, but standard & high quality
        "meta-llama/llama-3.3-70b-instruct:free", // Free Llama 3.3
        "meta-llama/llama-3.1-8b-instruct:free", // Free Llama 3.1
        "google/gemini-2.0-flash-exp:free" // Free Gemini 2.0 Exp
    ];
    
    $workingModel = null;
    $successResponse = null;
    
    foreach ($modelsToTest as $model) {
        echo "Testing model: $model ... ";
        
        $headers = [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json",
            "HTTP-Referer: https://linkpilot.work",
            "X-Title: LinkPilot AI"
        ];

        $postFields = [
            "model" => $model,
            "messages" => [
                ["role" => "user", "content" => "Ping."]
            ],
            "max_tokens" => 5
        ];

        $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            echo "Failed (Connection error: $error)\n";
            continue;
        }

        $data = json_decode($response, true);
        if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
            echo "SUCCESS!\n";
            $workingModel = $model;
            $successResponse = $data;
            break;
        } else {
            $msg = $data['error']['message'] ?? "Status Code $httpCode";
            echo "Failed ($msg)\n";
        }
    }
    
    if ($workingModel) {
        echo "\nSUCCESS! Found a working model: $workingModel\n\n";
        
        // Recover key status in DB
        $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = 1");
        $stmtUpdate->execute();
        
        // Switch active provider to openrouter and set working model
        $stmtUser = $db->prepare("UPDATE users SET active_ai_provider = 'openrouter', active_ai_model = ? WHERE id = 1");
        $stmtUser->execute([$workingModel]);
        
        // Update default model constant for fallback in config.php (since it is read from DB, we also save it in users table)
        echo "Recovered Settings:\n";
        echo "1. Set OpenRouter Key ID 1 status to 'active'.\n";
        echo "2. Set active provider to 'openrouter' with model '$workingModel'.\n";
        echo "\nTry using your extension now!";
    } else {
        echo "\nFAILED: All tested OpenRouter models failed. Please verify your OpenRouter account has enough credits or if your key is active.";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
