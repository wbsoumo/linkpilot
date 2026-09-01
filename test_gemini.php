<?php
// test_gemini.php - Standalone testing page for Gemini API Integration
require_once __DIR__ . '/backend/config.php';

// Get API Key from POST parameter, environment variable, defined constant, or fallback input box
$apiKeyInput = $_POST['api_key'] ?? (getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : ''));
$promptInput = $_POST['prompt'] ?? 'Explain how AI works in a few words';
$responseOutput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prompt'])) {
    $promptInput = trim($_POST['prompt']);
    $apiKey = trim($apiKeyInput);
    
    if (empty($apiKey)) {
        $responseOutput = "Error: Gemini API key is missing. Please enter your API key in the API Key input box.";
    } else if (!empty($promptInput)) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';
        
        $payload = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptInput]
                    ]
                ]
            ]
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-goog-api-key: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $responseOutput = "cURL Error: " . htmlspecialchars($curlError);
        } else {
            $jsonDecoded = json_decode($result, true);
            if ($httpCode === 200 && isset($jsonDecoded['candidates'][0]['content']['parts'][0]['text'])) {
                $responseOutput = $jsonDecoded['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $responseOutput = "HTTP Status Code: " . $httpCode . "\n\nResponse:\n" . print_r($jsonDecoded ?: $result, true);
            }
        }
    } else {
        $responseOutput = "Please enter a prompt.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemini API Tester - LinkPilot AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-4 md:p-8 flex items-center justify-center">
    <div class="max-w-2xl w-full bg-slate-800 border border-slate-700/80 rounded-2xl p-6 md:p-8 shadow-2xl space-y-6">
        <div class="border-b border-slate-700/80 pb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl md:text-2xl font-extrabold text-white flex items-center space-x-2">
                    <span>Gemini API Tester</span>
                </h1>
                <p class="text-xs text-slate-400 mt-1">Test prompt responses from Google Generative AI API</p>
            </div>
            <span class="text-xs bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 px-2.5 py-1 rounded-full font-bold">gemini-flash-latest</span>
        </div>

        <form method="POST" class="space-y-4">
            <div>
                <label for="api_key" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Gemini API Key</label>
                <input type="password" id="api_key" name="api_key" value="<?php echo htmlspecialchars($apiKeyInput); ?>" placeholder="Enter your Gemini API Key" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs text-slate-100 font-mono focus:outline-none focus:border-indigo-500 transition">
            </div>

            <div>
                <label for="prompt" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Your Prompt / Question</label>
                <textarea id="prompt" name="prompt" rows="3" required placeholder="e.g. Explain how AI works in a few words" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"><?php echo htmlspecialchars($promptInput); ?></textarea>
            </div>

            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-sm rounded-xl transition shadow-lg shadow-indigo-600/20 flex items-center justify-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span>Send Request to Gemini API</span>
            </button>
        </form>

        <?php if (!empty($responseOutput)): ?>
        <div class="space-y-2 pt-2 border-t border-slate-700/80">
            <label class="block text-xs font-bold text-indigo-400 uppercase tracking-wider">Gemini API Response Box</label>
            <div class="bg-slate-900 border border-slate-700 rounded-xl p-4 text-xs font-mono text-slate-200 whitespace-pre-wrap max-h-96 overflow-y-auto leading-relaxed shadow-inner">
                <?php echo htmlspecialchars($responseOutput); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
