<?php
// backend/api/crm/analyze_meta_screenshot.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../wallet_helper.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Enable CORS
sendCorsHeaders();

// Validate Auth
try {
    $user = JWTHelper::requireAuth();
    $userId = $user['id'];
    $db = Database::getConnection();
} catch (Exception $e) {
    sendJsonResponse('error', 'Unauthorized: ' . $e->getMessage(), [], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Get the raw POST content or json payload
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$base64Image = $input['image'] ?? '';
if (empty($base64Image) && isset($_FILES['image'])) {
    // Read from file upload
    $fileTmpPath = $_FILES['image']['tmp_name'] ?? '';
    if (file_exists($fileTmpPath)) {
        $fileType = mime_content_type($fileTmpPath);
        $base64Image = 'data:' . $fileType . ';base64,' . base64_encode(file_get_contents($fileTmpPath));
    }
}

if (empty($base64Image)) {
    sendJsonResponse('error', 'Image is required for analysis.', [], 400);
}

// Extract base64 components
if (preg_match('/^data:(image\/[a-zA-Z+0-9.-]+);base64,(.+)$/is', $base64Image, $matches)) {
    $mimeType = $matches[1];
    $base64Data = $matches[2];
} else {
    $mimeType = 'image/png';
    $base64Data = $base64Image;
}

try {
    // Determine the active AI Provider, Model, and API Key
    $provider = 'github_models';
    $model = GITHUB_MODELS_MODEL;

    $stmtAdmin = $db->query("SELECT active_ai_provider, active_ai_model FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $adminRes = $stmtAdmin->fetch();
    if ($adminRes) {
        if (!empty($adminRes['active_ai_provider'])) {
            $provider = $adminRes['active_ai_provider'];
        }
        if ($provider === 'github_models') {
            $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : GITHUB_MODELS_MODEL;
        } elseif ($provider === 'google_ai_studio') {
            $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : GOOGLE_AI_STUDIO_MODEL;
        } else {
            $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : OPENROUTER_MODEL;
        }
    }

    $apiKeysList = [];
    $stmtKeys = $db->prepare("
        SELECT k.id, k.api_key, k.status 
        FROM user_ai_keys k 
        JOIN users u ON k.user_id = u.id 
        WHERE u.role = 'admin' AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') 
        ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC
    ");
    $stmtKeys->execute([$provider]);
    $apiKeysList = $stmtKeys->fetchAll();

    if (empty($apiKeysList)) {
        if ($provider === 'github_models') {
            $apiKey = getenv('GITHUB_API_KEY') ?: '';
            if (!empty($apiKey)) {
                $apiKeysList[] = ['id' => null, 'api_key' => encryptData($apiKey), 'status' => 'active'];
            }
        } elseif ($provider === 'google_ai_studio') {
            $apiKey = getenv('GEMINI_API_KEY') ?: '';
            if (!empty($apiKey)) {
                $apiKeysList[] = ['id' => null, 'api_key' => encryptData($apiKey), 'status' => 'active'];
            }
        } else {
            $apiKey = getenv('OPENROUTER_API_KEY') ?: '';
            if (!empty($apiKey)) {
                $apiKeysList[] = ['id' => null, 'api_key' => encryptData($apiKey), 'status' => 'active'];
            }
        }
    }

    if (empty($apiKeysList)) {
        throw new Exception("No active API keys found for central provider: '{$provider}'");
    }

    $keyRow = $apiKeysList[0];
    $encryptedKey = $keyRow['api_key'];
    $apiKey = decryptData($encryptedKey);
    if (!$apiKey) {
        $apiKey = $encryptedKey;
    }

    // Set endpoints and auth headers based on provider
    if ($provider === 'github_models') {
        $url = "https://models.inference.ai.azure.com/chat/completions";
        $headers = [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json",
            "User-Agent: LinkPilot-AI"
        ];
    } elseif ($provider === 'google_ai_studio') {
        $url = "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions";
        $headers = [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json"
        ];
    } else { // openrouter
        $url = "https://openrouter.ai/api/v1/chat/completions";
        $headers = [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json",
            "HTTP-Referer: https://linkpilot.work",
            "X-Title: LinkPilot Onboarding Vision Assistant"
        ];
    }

    $userText = trim($input['message'] ?? '');
    if (empty($userText)) {
        $userText = "Identify this Meta console page, diagnose any issues, and give me step-by-step guidance on what to click or do next.";
    }

    $systemPrompt = "You are the LinkPilot Meta Setup Vision Assistant. Analyze the uploaded screenshot of the Meta Developer Console or Facebook Business Settings. Identify what page the user is on, locate the key fields (such as Access Tokens, WABA ID, Phone Number ID, App ID, etc.), diagnose any issues (e.g. invalid permissions, sandbox demo status, or expired tokens), and give precise, bullet-point instructions on what the user should click, edit, or copy to successfully complete their WhatsApp integration.";

    // Fetch liked responses for vision reinforcement training
    try {
        $stmtLearned = $db->prepare("SELECT question, answer FROM ai_chat_feedback WHERE user_id = ? AND feedback_type = 'like' AND (question LIKE '%screenshot%' OR question LIKE '%[User sent a Meta console screenshot%') ORDER BY id DESC LIMIT 5");
        $stmtLearned->execute([$userId]);
        $learnedPairs = $stmtLearned->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($learnedPairs)) {
            $systemPrompt .= "\n\n=== AI SCREENSHOT RESPONSE TRAINING (FOLLOW THESE PAST LIKED VISION ANSWERS FOR SIMILAR IMAGES/QUERIES) ===\n";
            foreach ($learnedPairs as $pair) {
                $systemPrompt .= "User Context/Query: " . $pair['question'] . "\n";
                $systemPrompt .= "Learned AI Analysis: " . $pair['answer'] . "\n\n";
            }
            $systemPrompt .= "=========================================================================================================\n";
        }
    } catch (Exception $e) {}

    $postFields = [
        "model" => $model,
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            [
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => $userText
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:" . $mimeType . ";base64," . $base64Data
                        ]
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("AI Provider vision service connection error: " . $error);
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        $msg = $data['message'] ?? ($data['error']['message'] ?? "Unknown Vision API Error");
        throw new Exception("Vision API Error (HTTP {$httpCode}): " . $msg);
    }

    $generatedText = $data['choices'][0]['message']['content'] ?? '';
    if (empty($generatedText)) {
        throw new Exception("Vision model returned an empty response.");
    }

    sendJsonResponse('success', 'Screenshot analyzed successfully.', [
        'reply' => trim($generatedText)
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', $e->getMessage(), [], 500);
}
