<?php
// backend/agent_ai_client.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_tool_registry.php';

class AgentAIClient {

    /**
     * Resolves all available providers with valid API keys across `user_ai_keys`, `users` table, and environment.
     */
    public static function getAllAvailableProviders($userId = null) {
        $db = Database::getConnection();
        $candidates = [];

        // 1. Fetch user & admin records from `users`
        $userRow = null;
        if ($userId) {
            try {
                $stmtUser = $db->prepare("SELECT id, role, active_ai_provider, active_ai_model, openrouter_key, github_key, google_key FROM users WHERE id = ?");
                $stmtUser->execute([$userId]);
                $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
        }

        $adminRow = null;
        try {
            $stmtAdmin = $db->query("SELECT id, role, active_ai_provider, active_ai_model, openrouter_key, github_key, google_key FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}

        $targetUser = $userRow ?: $adminRow;
        $activeProvider = (!empty($targetUser['active_ai_provider'])) ? $targetUser['active_ai_provider'] : 'google_ai_studio';
        $activeModel = $targetUser['active_ai_model'] ?? null;

        // 2. Fetch keys from `user_ai_keys` table
        $dbKeys = [];
        try {
            $userIds = array_unique(array_filter([$userId, $adminRow['id'] ?? null]));
            if (!empty($userIds)) {
                $inClause = implode(',', array_map('intval', $userIds));
                // Allow keys unless explicitly paused by user
                $stmt = $db->query("SELECT provider, api_key, status FROM user_ai_keys WHERE user_id IN ($inClause) AND status != 'paused' ORDER BY id ASC");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $dec = decryptData($r['api_key']);
                    $cleanKey = ($dec !== false) ? $dec : $r['api_key'];
                    if (!empty($cleanKey) && strpos($cleanKey, 'placeholder') === false) {
                        $dbKeys[$r['provider']] = $cleanKey;
                    }
                }
            }
        } catch (Throwable $e) {}

        // 3. Also check keys saved directly in `users` table
        foreach ([$userRow, $adminRow] as $u) {
            if (!$u) continue;
            if (!empty($u['google_key']) && !isset($dbKeys['google_ai_studio'])) {
                $dec = decryptData($u['google_key']);
                $k = ($dec !== false) ? $dec : $u['google_key'];
                if (!empty($k) && strpos($k, 'placeholder') === false) $dbKeys['google_ai_studio'] = $k;
            }
            if (!empty($u['openrouter_key']) && !isset($dbKeys['openrouter'])) {
                $dec = decryptData($u['openrouter_key']);
                $k = ($dec !== false) ? $dec : $u['openrouter_key'];
                if (!empty($k) && strpos($k, 'placeholder') === false) $dbKeys['openrouter'] = $k;
            }
            if (!empty($u['github_key']) && !isset($dbKeys['github_models'])) {
                $dec = decryptData($u['github_key']);
                $k = ($dec !== false) ? $dec : $u['github_key'];
                if (!empty($k) && strpos($k, 'placeholder') === false) $dbKeys['github_models'] = $k;
            }
        }

        // 4. Fallback to ENV constants if not found in DB
        if (!isset($dbKeys['groq'])) {
            $k = getenv('GROQ_API_KEY') ?: (defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
            if (!empty($k) && strpos($k, 'placeholder') === false) $dbKeys['groq'] = $k;
        }
        if (!isset($dbKeys['google_ai_studio'])) {
            $k = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
            if (!empty($k) && strpos($k, 'placeholder') === false) $dbKeys['google_ai_studio'] = $k;
        }
        if (!isset($dbKeys['openrouter'])) {
            $k = getenv('OPENROUTER_API_KEY') ?: (defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '');
            if (!empty($k) && strpos($k, 'placeholder') === false) $dbKeys['openrouter'] = $k;
        }
        if (!isset($dbKeys['github_models'])) {
            $k = getenv('GITHUB_TOKEN') ?: getenv('GITHUB_API_KEY') ?: (defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '');
            if (!empty($k) && strpos($k, 'placeholder') === false) $dbKeys['github_models'] = $k;
        }

        // 5. Default model mappings with verified active model IDs
        $modelMap = [
            'google_ai_studio' => 'gemini-flash-latest',
            'openrouter' => 'meta-llama/llama-3.1-8b-instruct',
            'groq' => 'llama-3.1-8b-instant',
            'github_models' => 'gpt-4o-mini'
        ];

        // Sanitize activeModel: if it contains obsolete strings, use known good default
        $safeActiveModel = $activeModel;
        if (!empty($safeActiveModel)) {
            if (strpos($safeActiveModel, '2.0-flash') !== false || strpos($safeActiveModel, '2.5-flash:free') !== false) {
                $safeActiveModel = $modelMap[$activeProvider] ?? 'gemini-flash-latest';
            }
        }

        // Ensure active provider is tested first, followed by fast, authenticated providers
        $providerOrder = array_unique(array_merge([$activeProvider], ['google_ai_studio', 'openrouter', 'groq', 'github_models']));
        $candidates = [];

        foreach ($providerOrder as $prov) {
            if (!empty($dbKeys[$prov])) {
                $provModel = ($prov === $activeProvider && !empty($safeActiveModel)) ? $safeActiveModel : ($modelMap[$prov] ?? 'gemini-flash-latest');
                $candidates[] = [
                    'provider' => $prov,
                    'model' => $provModel,
                    'api_key' => $dbKeys[$prov]
                ];
            }
        }

        // If no configured key, provide default openrouter fallback
        if (empty($candidates)) {
            $candidates[] = [
                'provider' => 'openrouter',
                'model' => 'meta-llama/llama-3.1-8b-instruct',
                'api_key' => defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : ''
            ];
        }

        return $candidates;
    }

    /**
     * Resolves the primary active provider configuration.
     */
    public static function getActiveAIConfig($userId = null) {
        $all = self::getAllAvailableProviders($userId);
        return $all[0];
    }

    /**
     * Executes OpenAI compatible Chat Completion with tool calling and automatic multi-provider failover.
     * Retries alternate valid models per provider based on execution path.
     */
    public static function callChat(array $messages, array $tools = [], $userId = null, $maxTokens = 1000, string $executionPath = 'business_ops') {
        $startTime = microtime(true);
        $providers = self::getAllAvailableProviders($userId);
        $lastError = null;

        // Model catalogs structured by execution path complexity
        $modelCatalog = [
            'simple_ai' => [
                'google_ai_studio' => ['gemini-flash-latest', 'gemini-2.5-flash', 'gemini-1.5-flash'],
                'openrouter' => ['meta-llama/llama-3.1-8b-instruct', 'google/gemini-flash-1.5'],
                'groq' => ['llama-3.1-8b-instant', 'llama3-8b-8192'],
                'github_models' => ['gpt-4o-mini']
            ],
            'business_ops' => [
                'google_ai_studio' => ['gemini-flash-latest', 'gemini-2.5-flash', 'gemini-pro-latest'],
                'openrouter' => ['meta-llama/llama-3.1-8b-instruct', 'google/gemini-flash-1.5'],
                'groq' => ['llama-3.1-8b-instant', 'llama3-70b-8192'],
                'github_models' => ['gpt-4o-mini', 'gpt-4o']
            ],
            'complex_autonomous' => [
                'google_ai_studio' => ['gemini-pro-latest', 'gemini-flash-latest', 'gemini-2.5-flash'],
                'openrouter' => ['meta-llama/llama-3.3-70b-instruct', 'meta-llama/llama-3.1-8b-instruct'],
                'groq' => ['llama3-70b-8192', 'llama-3.1-8b-instant'],
                'github_models' => ['gpt-4o', 'gpt-4o-mini']
            ]
        ];

        $pathCatalog = $modelCatalog[$executionPath] ?? $modelCatalog['business_ops'];
        $timeoutSeconds = ($executionPath === 'complex_autonomous') ? 14 : 8;

        foreach ($providers as $cfg) {
            $provider = $cfg['provider'];
            $apiKey = $cfg['api_key'];

            if (empty($apiKey) || strpos($apiKey, 'placeholder') !== false) continue;

            // Build ordered candidate models for this provider
            $candidateModels = [];
            if (!empty($cfg['model']) && strpos($cfg['model'], '3.1-flash-lite') === false && strpos($cfg['model'], '3.8-flash') === false) {
                $candidateModels[] = $cfg['model'];
            }
            if (!empty($pathCatalog[$provider])) {
                foreach ($pathCatalog[$provider] as $m) {
                    if (!in_array($m, $candidateModels)) {
                        $candidateModels[] = $m;
                    }
                }
            }

            // Cap model attempts to at most 2 fast attempts per provider
            $candidateModels = array_slice($candidateModels, 0, 2);

            // For Google AI Studio, try native v1beta generateContent endpoint first
            if ($provider === 'google_ai_studio') {
                foreach ($candidateModels as $model) {
                    try {
                        $nativeRes = self::callGoogleNative($apiKey, $model, $messages, $tools);
                        if (!empty($nativeRes['content']) || !empty($nativeRes['tool_calls'])) {
                            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
                            $nativeRes['provider'] = 'google_ai_studio';
                            $nativeRes['model'] = $model;
                            $nativeRes['latency_ms'] = $latencyMs;
                            return $nativeRes;
                        }
                    } catch (Throwable $e) {
                        error_log("Google Native {$model} error: " . $e->getMessage());
                    }
                }
            }

            $endpoint = "https://openrouter.ai/api/v1/chat/completions";
            $headers = [
                "Authorization: Bearer " . $apiKey,
                "Content-Type: application/json"
            ];

            if ($provider === 'github_models') {
                $endpoint = "https://models.inference.ai.azure.com/chat/completions";
                $headers[] = "User-Agent: LinkPilot-AI";
            } elseif ($provider === 'google_ai_studio') {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions";
            } elseif ($provider === 'groq') {
                $endpoint = "https://api.groq.com/openai/v1/chat/completions";
            } else {
                $headers[] = "HTTP-Referer: https://linkpilot.work";
                $headers[] = "X-Title: LinkPilot AI Agent";
            }

            foreach ($candidateModels as $model) {
                $postFields = [
                    "model" => $model,
                    "messages" => $messages,
                    "temperature" => 0.3,
                    "max_tokens" => $maxTokens
                ];

                if (!empty($tools)) {
                    $postFields["tools"] = $tools;
                    $postFields["tool_choice"] = "auto";
                }

                try {
                    $ch = curl_init($endpoint);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                    curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    curl_close($ch);

                    if ($error) {
                        throw new Exception("Provider {$provider} curl error: " . $error);
                    }

                    $data = json_decode($response, true);
                    if ($httpCode !== 200) {
                        $errMsg = $data['error']['message'] ?? ($data['message'] ?? "HTTP {$httpCode} Error");

                        // If tools parameter caused error on certain models, retry without tools
                        if (!empty($tools) && (stripos($errMsg, 'tool') !== false || stripos($errMsg, 'function') !== false)) {
                            unset($postFields['tools']);
                            unset($postFields['tool_choice']);
                            $retryResult = self::callRawCurl($endpoint, $headers, $postFields);
                            if (!empty($retryResult['content']) || !empty($retryResult['tool_calls'])) {
                                $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
                                $retryResult['provider'] = $provider;
                                $retryResult['model'] = $model;
                                $retryResult['latency_ms'] = $latencyMs;
                                return $retryResult;
                            }
                        }

                        // If model not found or invalid model, try next model in catalog
                        if ($httpCode === 404 || $httpCode === 400 || stripos($errMsg, 'model') !== false) {
                            error_log("AI Model Retry: {$provider}/{$model} returned HTTP {$httpCode} ({$errMsg}). Trying catalog alternate...");
                            continue;
                        }

                        throw new Exception("Provider {$provider} HTTP {$httpCode}: " . $errMsg);
                    }

                    $choice = self::extractChoice($data);
                    if (!empty($choice['content']) || !empty($choice['tool_calls'])) {
                        $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
                        $choice['provider'] = $provider;
                        $choice['model'] = $model;
                        $choice['latency_ms'] = $latencyMs;
                        return $choice;
                    }
                } catch (Throwable $e) {
                    $lastError = $e->getMessage();
                    error_log("AI Provider Failover: {$provider} ({$model}) failed ({$lastError}). Trying next candidate...");
                    continue;
                }
            }
        }

        $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
        return [
            'content' => "I am having trouble reaching the AI model right now due to a network or authentication timeout. Please check your AI API key in Settings > Integrations.",
            'tool_calls' => [],
            'tokens_used' => 0,
            'provider' => 'none',
            'model' => 'none',
            'latency_ms' => $latencyMs
        ];
    }

    private static function callRawCurl($endpoint, $headers, $postFields) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 14);

        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return self::extractChoice($data);
    }

    /**
     * Native v1beta generateContent for Google Gemini.
     * Supports system_instruction, function_declarations (tools), and multiturn conversation.
     */
    private static function callGoogleNative($apiKey, $model, array $messages, array $tools = []) {
        $cleanModel = str_replace('models/', '', $model);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$cleanModel}:generateContent?key=" . urlencode($apiKey);

        $systemParts = [];
        $contents = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $text = is_string($msg['content'] ?? '') ? $msg['content'] : json_encode($msg['content'] ?? '');

            if ($role === 'system') {
                if (!empty($text)) {
                    $systemParts[] = ['text' => $text];
                }
                continue;
            }

            $geminiRole = ($role === 'assistant') ? 'model' : 'user';

            // If preceding message had the same role, combine them to satisfy Gemini's strict alternating turn rule
            $lastIdx = count($contents) - 1;
            if ($lastIdx >= 0 && $contents[$lastIdx]['role'] === $geminiRole) {
                $contents[$lastIdx]['parts'][] = ['text' => $text];
            } else {
                $contents[] = [
                    'role' => $geminiRole,
                    'parts' => [['text' => $text]]
                ];
            }
        }

        // Gemini requires first content turn to be 'user'
        if (!empty($contents) && $contents[0]['role'] !== 'user') {
            array_unshift($contents, [
                'role' => 'user',
                'parts' => [['text' => 'Hello']]
            ]);
        }

        if (empty($contents)) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => 'Hello']]
            ];
        }

        $payload = ['contents' => $contents];

        if (!empty($systemParts)) {
            $payload['system_instruction'] = ['parts' => $systemParts];
        }

        // Convert OpenAI-style tools to Gemini function_declarations
        if (!empty($tools)) {
            $funcDecls = [];
            foreach ($tools as $t) {
                if (!empty($t['function'])) {
                    $fn = $t['function'];
                    $decl = [
                        'name' => $fn['name'],
                        'description' => $fn['description'] ?? ''
                    ];
                    if (!empty($fn['parameters'])) {
                        $decl['parameters'] = $fn['parameters'];
                    }
                    $funcDecls[] = $decl;
                }
            }
            if (!empty($funcDecls)) {
                $payload['tools'] = [['function_declarations' => $funcDecls]];
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 9);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            $candidate = $data['candidates'][0]['content'] ?? [];
            $parts = $candidate['parts'] ?? [];

            $textContent = '';
            $toolCalls = [];

            foreach ($parts as $p) {
                if (isset($p['text'])) {
                    $textContent .= $p['text'];
                }
                if (isset($p['functionCall'])) {
                    $toolCalls[] = [
                        'id' => 'call_' . uniqid(),
                        'name' => $p['functionCall']['name'] ?? '',
                        'arguments' => $p['functionCall']['args'] ?? []
                    ];
                }
            }

            $textContent = trim($textContent);

            // Also check if text content contains markdown tool calls
            if (empty($toolCalls) && !empty($textContent)) {
                if (preg_match_all('/```(?:tool_call|json)?\s*(\{\s*"name"\s*:\s*"[^"]+".*?\})\s*```/is', $textContent, $m)) {
                    foreach ($m[1] as $block) {
                        $parsed = json_decode($block, true);
                        if ($parsed && !empty($parsed['name'])) {
                            $toolCalls[] = [
                                'id' => 'call_' . uniqid(),
                                'name' => $parsed['name'],
                                'arguments' => $parsed['arguments'] ?? []
                            ];
                        }
                    }
                    $textContent = trim(preg_replace('/```(?:tool_call|json)?\s*\{\s*"name"\s*:\s*"[^"]+".*?\}\s*```/is', '', $textContent));
                }
            }

            if (!empty($textContent) || !empty($toolCalls)) {
                return [
                    'content' => $textContent,
                    'tool_calls' => $toolCalls,
                    'tokens_used' => $data['usageMetadata']['totalTokenCount'] ?? 0
                ];
            }
        }

        return ['content' => '', 'tool_calls' => [], 'tokens_used' => 0];
    }

    /**
     * Extracts text content and tool calls from response.
     */
    private static function extractChoice($data) {
        $message = $data['choices'][0]['message'] ?? [];
        $content = trim($message['content'] ?? '');
        $toolCalls = [];

        // 1. Check native tool_calls
        if (!empty($message['tool_calls']) && is_array($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $args = $tc['function']['arguments'] ?? '{}';
                if (is_string($args)) {
                    $args = json_decode($args, true) ?: [];
                }
                $toolCalls[] = [
                    'id' => $tc['id'] ?? ('call_' . uniqid()),
                    'name' => $tc['function']['name'] ?? '',
                    'arguments' => $args
                ];
            }
        }

        // 2. Fallback: Parse markdown ```tool_call or JSON inside content
        if (empty($toolCalls) && !empty($content)) {
            if (preg_match_all('/```(?:tool_call|json)?\s*(\{\s*"name"\s*:\s*"[^"]+".*?\})\s*```/is', $content, $m)) {
                foreach ($m[1] as $block) {
                    $parsed = json_decode($block, true);
                    if ($parsed && !empty($parsed['name'])) {
                        $toolCalls[] = [
                            'id' => 'call_' . uniqid(),
                            'name' => $parsed['name'],
                            'arguments' => $parsed['arguments'] ?? []
                        ];
                    }
                }
                // Strip out the tool block from user-visible content
                $content = trim(preg_replace('/```(?:tool_call|json)?\s*\{\s*"name"\s*:\s*"[^"]+".*?\}\s*```/is', '', $content));
            }
        }

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
            'tokens_used' => $data['usage']['total_tokens'] ?? 0
        ];
    }
}
