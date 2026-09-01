<?php
// test_gemini.php - Standalone testing page for Gemini API Integration
require_once __DIR__ . '/backend/config.php';

// Get API Key from POST parameter, environment variable, defined constant, or fallback input box
$selectedModel = $_POST['model'] ?? 'gemini-3.1-flash-lite';
$apiKeyInput = $_POST['api_key'] ?? (getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : ''));
$promptInput = $_POST['prompt'] ?? 'Explain how AI works in a few words';
$responseOutput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey = trim($apiKeyInput);
    
    if (empty($apiKey)) {
        $responseOutput = "Error: Gemini API key is missing. Please enter your API key in the API Key input box.";
    } else if (isset($_POST['list_models'])) {
        // Fetch available models for this API key via ListModels endpoint
        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($apiKey);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot-AI/1.0');
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $jsonDecoded = json_decode($result, true);
        if ($httpCode === 200 && isset($jsonDecoded['models'])) {
            $supportedModels = array_map(function($m) {
                return $m['name'] . " (" . implode(', ', $m['supportedGenerationMethods'] ?? []) . ")";
            }, $jsonDecoded['models']);
            $responseOutput = "[Available Models for your API Key]:\n\n" . implode("\n", $supportedModels);
        } else {
            $responseOutput = "HTTP Status Code: " . $httpCode . "\n\nResponse:\n" . print_r($jsonDecoded ?: $result, true);
        }
    } else if (isset($_POST['prompt'])) {
        $promptInput = trim($_POST['prompt']);
        
        if (!empty($promptInput)) {
            // Models to benchmark in parallel simultaneously
            $modelsToTest = ['gemini-3.1-flash-lite', 'gemini-3.6-flash', 'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-flash-latest'];
            
            $mh = curl_multi_init();
            $curlHandles = [];
            $startTime = microtime(true);
            
            foreach ($modelsToTest as $mName) {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $mName . ':generateContent?key=' . urlencode($apiKey);
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
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                
                curl_multi_add_handle($mh, $ch);
                $curlHandles[$mName] = $ch;
            }
            
            // Execute all multi-cURL handles in parallel
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.01);
            } while ($running > 0);
            
            $results = [];
            $fastestModel = null;
            $fastestTime = PHP_FLOAT_MAX;
            
            foreach ($curlHandles as $mName => $ch) {
                $content = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
                $curlError = curl_error($ch);
                
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                
                $timeMs = round($totalTime * 1000, 2);
                
                if ($curlError) {
                    $results[$mName] = [
                        'status' => 'error',
                        'time' => $timeMs,
                        'message' => "cURL Error: " . $curlError
                    ];
                } else {
                    $jsonDecoded = json_decode($content, true);
                    if ($httpCode === 200 && isset($jsonDecoded['candidates'][0]['content']['parts'][0]['text'])) {
                        $text = trim($jsonDecoded['candidates'][0]['content']['parts'][0]['text']);
                        $results[$mName] = [
                            'status' => 'success',
                            'time' => $timeMs,
                            'text' => $text
                        ];
                        if ($totalTime < $fastestTime) {
                            $fastestTime = $totalTime;
                            $fastestModel = $mName;
                        }
                    } else {
                        $errMessage = $jsonDecoded['error']['message'] ?? "HTTP Status {$httpCode}";
                        $results[$mName] = [
                            'status' => 'failed',
                            'time' => $timeMs,
                            'message' => $errMessage
                        ];
                    }
                }
            }
            curl_multi_close($mh);
            
            // Build visual benchmark output
            $out = "⚡ PARALLEL MODEL BENCHMARK RESULTS ⚡\n";
            $out .= "==================================================\n";
            if ($fastestModel) {
                $out .= "🏆 FASTEST MODEL: " . strtoupper($fastestModel) . " (" . round($fastestTime * 1000, 2) . " ms)\n";
            } else {
                $out .= "❌ No model succeeded.\n";
            }
            $out .= "==================================================\n\n";
            
            foreach ($results as $mName => $res) {
                $isWinner = ($mName === $fastestModel) ? " 🥇 [FASTEST]" : "";
                $out .= "► Model: " . $mName . $isWinner . "\n";
                $out .= "  Response Time: " . $res['time'] . " ms\n";
                if ($res['status'] === 'success') {
                    $out .= "  Status: 200 OK\n";
                    $out .= "  Response: " . $res['text'] . "\n\n";
                } else {
                    $out .= "  Status: " . strtoupper($res['status']) . "\n";
                    $out .= "  Error: " . $res['message'] . "\n\n";
                }
            }
            
            $responseOutput = $out;
        } else {
            $responseOutput = "Please enter a prompt.";
        }
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
            <span class="text-xs bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 px-2.5 py-1 rounded-full font-bold">Google Generative AI</span>
        </div>

        <form method="POST" class="space-y-4">
            <div>
                <label for="api_key" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Gemini API Key</label>
                <input type="password" id="api_key" name="api_key" value="<?php echo htmlspecialchars($apiKeyInput); ?>" placeholder="Enter your Gemini API Key" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs text-slate-100 font-mono focus:outline-none focus:border-indigo-500 transition">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div class="md:col-span-2">
                    <label for="model" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Target Model</label>
                    <select id="model" name="model" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs text-slate-100 font-semibold focus:outline-none focus:border-indigo-500 transition">
                        <option value="gemini-3.1-flash-lite" <?php echo $selectedModel === 'gemini-3.1-flash-lite' ? 'selected' : ''; ?>>gemini-3.1-flash-lite (Ultra Fast & Lightweight - Recommended)</option>
                        <option value="gemini-3.6-flash" <?php echo $selectedModel === 'gemini-3.6-flash' ? 'selected' : ''; ?>>gemini-3.6-flash (Latest Standard)</option>
                        <option value="gemini-2.5-flash" <?php echo $selectedModel === 'gemini-2.5-flash' ? 'selected' : ''; ?>>gemini-2.5-flash (Stable Production)</option>
                        <option value="gemini-2.5-pro" <?php echo $selectedModel === 'gemini-2.5-pro' ? 'selected' : ''; ?>>gemini-2.5-pro (High Reasoning)</option>
                        <option value="gemini-flash-latest" <?php echo $selectedModel === 'gemini-flash-latest' ? 'selected' : ''; ?>>gemini-flash-latest (Alias)</option>
                    </select>
                </div>
                <div>
                    <button type="submit" name="list_models" value="1" class="w-full py-3 bg-slate-700 hover:bg-slate-600 text-slate-200 font-bold text-xs rounded-xl transition flex items-center justify-center space-x-1">
                        <span>List Models</span>
                    </button>
                </div>
            </div>

            <div>
                <label for="prompt" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Your Prompt / Question</label>
                <textarea id="prompt" name="prompt" rows="3" placeholder="e.g. Explain how AI works in a few words" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"><?php echo htmlspecialchars($promptInput); ?></textarea>
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
