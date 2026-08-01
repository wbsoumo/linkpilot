<?php
// backend/test_openrouter_full.php
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

$db = Database::getConnection();
try {
    $stmt = $db->prepare("SELECT api_key FROM user_ai_keys WHERE id = 1");
    $stmt->execute();
    $keyRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$keyRow) {
        throw new Exception("OpenRouter Key ID 1 not found.");
    }
    
    $rawKey = $keyRow['api_key'];
    $decrypted = decryptData($rawKey);
    $apiKey = ($decrypted !== false) ? $decrypted : $rawKey;
    
    $systemPrompt = "You are LinkPilot AI, a premium outreach generator. Write the email in the specified tone: Professional.";
    $userPrompt = "LinkedIn Post Content: \"Hello world! Let's connect.\" Generate a personalized outreach email.";
    
    echo "--- OpenRouter Full Request Tester ---\n";
    echo "Testing Key ID 1...\n";
    echo "Model: google/gemini-2.5-flash\n\n";
    
    $headers = [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
        "HTTP-Referer: https://linkpilot.work",
        "X-Title: LinkPilot AI"
    ];

    $postFields = [
        "model" => "google/gemini-2.5-flash",
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $userPrompt]
        ]
    ];

    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "Connection Error: $error\n";
        exit;
    }

    echo "HTTP Status Code: $httpCode\n\n";
    echo "Response:\n$response\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
