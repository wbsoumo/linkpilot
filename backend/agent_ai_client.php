<?php
// backend/agent_ai_client.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_tool_registry.php';

class AgentAIClient {

    /**
     * Resolves all available providers with valid API keys across `user_ai_keys`, `users` table, and environment.
     */
    public static function getAllAvailableProviders($userId = null) {
        $db = null;
        try {
            $db = Database::getConnection();
        } catch (Throwable $e) {}
        $candidates = [];

        // 1. Fetch user & admin records from `users`
        $userRow = null;
        if ($userId && $db) {
            try {
                $stmtUser = $db->prepare("SELECT id, role, active_ai_provider, active_ai_model, openrouter_key, github_key, google_key FROM users WHERE id = ?");
                $stmtUser->execute([$userId]);
                $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
        }

        $adminRow = null;
        if ($db) {
            try {
                $stmtAdmin = $db->query("SELECT id, role, active_ai_provider, active_ai_model, openrouter_key, github_key, google_key FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
                $adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
        }

        $targetUser = $userRow ?: $adminRow;
        $activeProvider = (!empty($targetUser['active_ai_provider'])) ? $targetUser['active_ai_provider'] : 'google_ai_studio';
        $activeModel = $targetUser['active_ai_model'] ?? null;

        // 2. Fetch keys from `user_ai_keys` table
        $dbKeys = [];
        try {
            $userIds = array_unique(array_filter([$userId, $adminRow['id'] ?? null]));
            if (!empty($userIds) && $db) {
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
            'google_ai_studio' => 'gemini-3-flash-preview',
            'openrouter' => 'meta-llama/llama-3.1-8b-instruct',
            'groq' => 'llama-3.1-8b-instant',
            'github_models' => 'gpt-4o-mini'
        ];

        // Sanitize activeModel: if it contains obsolete strings or non-existent 3.5/2.x/1.5 models, use verified default
        $safeActiveModel = $activeModel;
        if (!empty($safeActiveModel)) {
            if (
                strpos($safeActiveModel, '3.5-flash') !== false ||
                strpos($safeActiveModel, '2.0-flash') !== false ||
                strpos($safeActiveModel, '2.5-flash') !== false ||
                strpos($safeActiveModel, '1.5-flash') !== false ||
                strpos($safeActiveModel, '1.5-pro') !== false
            ) {
                $safeActiveModel = 'gemini-3-flash-preview';
            }
        }

        // Ensure active provider is tested first, followed by fast, authenticated providers
        $providerOrder = array_unique(array_merge([$activeProvider], ['google_ai_studio', 'openrouter', 'groq', 'github_models']));
        $candidates = [];

        foreach ($providerOrder as $prov) {
            if (!empty($dbKeys[$prov])) {
                $provModel = ($prov === $activeProvider && !empty($safeActiveModel)) ? $safeActiveModel : ($modelMap[$prov] ?? 'gemini-3-flash-preview');
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

        // Model catalogs structured by execution path complexity with verified active models
        $modelCatalog = [
            'simple_ai' => [
                'google_ai_studio' => ['gemini-3-flash-preview', 'gemini-3.8-flash', 'gemini-flash-latest'],
                'openrouter' => ['meta-llama/llama-3.1-8b-instruct', 'google/gemini-flash-1.5'],
                'groq' => ['llama-3.1-8b-instant', 'llama3-8b-8192'],
                'github_models' => ['gpt-4o-mini']
            ],
            'business_ops' => [
                'google_ai_studio' => ['gemini-3-flash-preview', 'gemini-3.8-flash', 'gemini-flash-latest'],
                'openrouter' => ['meta-llama/llama-3.1-8b-instruct', 'google/gemini-flash-1.5'],
                'groq' => ['llama-3.1-8b-instant', 'llama3-70b-8192'],
                'github_models' => ['gpt-4o-mini', 'gpt-4o']
            ],
            'complex_autonomous' => [
                'google_ai_studio' => ['gemini-3-flash-preview', 'gemini-3.8-flash', 'gemini-flash-latest'],
                'openrouter' => ['meta-llama/llama-3.3-70b-instruct', 'meta-llama/llama-3.1-8b-instruct'],
                'groq' => ['llama3-70b-8192', 'llama-3.1-8b-instant'],
                'github_models' => ['gpt-4o', 'gpt-4o-mini']
            ]
        ];

        $pathCatalog = $modelCatalog[$executionPath] ?? $modelCatalog['business_ops'];
        $timeoutSeconds = ($executionPath === 'complex_autonomous') ? 45 : 30;

        // =========================================================================
        // ROUTING: LOCAL LIGHTWEIGHT LLM WITH AUTOMATIC GOOGLE AI FALLBACK
        // For basic general questions, greetings, simple explanations & conversations (simple_ai)
        // Never routes CRM actions, WhatsApp, Email, Deals, or tools to lightweight model.
        // =========================================================================
        $localFallbackInfo = null;
        $useLocal = (
            defined('LOCAL_AI_ENABLED') && LOCAL_AI_ENABLED &&
            defined('LOCAL_AI_ENDPOINT') && !empty(LOCAL_AI_ENDPOINT) &&
            $executionPath === 'simple_ai' &&
            empty($tools)
        );

        if ($useLocal) {
            $localStart = microtime(true);
            $localResult = self::callLocalOllama($messages, $userId, $maxTokens);

            if (!empty($localResult['success']) && !empty($localResult['content'])) {
                $validation = self::validateLocalResponse($localResult['content'], $localResult);
                if ($validation['valid']) {
                    return [
                        'content' => $localResult['content'],
                        'tool_calls' => [],
                        'tokens_used' => $localResult['tokens_used'] ?? 0,
                        'provider' => 'local_ollama',
                        'model' => $localResult['model'] ?? (defined('LOCAL_AI_MODEL') ? LOCAL_AI_MODEL : 'qwen2.5:0.5b'),
                        'latency_ms' => $localResult['latency_ms'] ?? (int)round((microtime(true) - $localStart) * 1000),
                        'fallback' => false
                    ];
                } else {
                    $localFallbackInfo = [
                        'from' => 'local_ollama',
                        'reason' => $validation['reason'],
                        'latency_ms' => $localResult['latency_ms'] ?? 0
                    ];
                    error_log("Local LLM validation failed: " . $validation['reason'] . " -> Automatically triggering Google AI fallback.");
                }
            } else {
                $localError = $localResult['error'] ?? 'Local inference failed or timed out';
                $localFallbackInfo = [
                    'from' => 'local_ollama',
                    'reason' => $localError,
                    'latency_ms' => $localResult['latency_ms'] ?? 0
                ];
                error_log("Local LLM service unavailable: " . $localError . " -> Automatically triggering Google AI fallback.");
            }
        }

        foreach ($providers as $cfg) {
            $provider = $cfg['provider'];
            $apiKey = $cfg['api_key'];

            if (empty($apiKey) || strpos($apiKey, 'placeholder') !== false) continue;

            // Build ordered candidate models for this provider
            $candidateModels = [];
            if (!empty($cfg['model']) && 
                strpos($cfg['model'], '3.5-flash') === false && 
                strpos($cfg['model'], '2.0-flash') === false && 
                strpos($cfg['model'], '2.5-flash') === false && 
                strpos($cfg['model'], '1.5-flash') === false) {
                $candidateModels[] = $cfg['model'];
            }
            if (!empty($pathCatalog[$provider])) {
                foreach ($pathCatalog[$provider] as $m) {
                    if (!in_array($m, $candidateModels)) {
                        $candidateModels[] = $m;
                    }
                }
            }

            // Cap model attempts to at most 3 fast attempts per provider
            $candidateModels = array_slice($candidateModels, 0, 3);

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
                            if ($localFallbackInfo) {
                                $nativeRes['fallback_from'] = $localFallbackInfo['from'];
                                $nativeRes['fallback_reason'] = $localFallbackInfo['reason'];
                            }
                            return $nativeRes;
                        }
                    } catch (Throwable $e) {
                        error_log("Google Native {$model} error: " . $e->getMessage());
                    }
                }
            }

            $endpoint = "https://openrouter.ai/api/v1/chat/completions";
            $headers = [
                "Content-Type: application/json"
            ];

            if ($provider === 'google_ai_studio') {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions";
                $headers[] = "x-goog-api-key: " . $apiKey;
            } elseif ($provider === 'github_models') {
                $endpoint = "https://models.inference.ai.azure.com/chat/completions";
                $headers[] = "Authorization: Bearer " . $apiKey;
                $headers[] = "User-Agent: LinkPilot-AI";
            } elseif ($provider === 'groq') {
                $endpoint = "https://api.groq.com/openai/v1/chat/completions";
                $headers[] = "Authorization: Bearer " . $apiKey;
            } else {
                $headers[] = "Authorization: Bearer " . $apiKey;
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
                    $sanitizedTools = [];
                    foreach ($tools as $t) {
                        if (!empty($t['function'])) {
                            $fn = $t['function'];
                            if (isset($fn['parameters']) && is_array($fn['parameters'])) {
                                if (empty($fn['parameters']['properties']) || (is_array($fn['parameters']['properties']) && count($fn['parameters']['properties']) === 0)) {
                                    $fn['parameters']['properties'] = (object)[];
                                }
                            }
                            $t['function'] = $fn;
                        }
                        $sanitizedTools[] = $t;
                    }
                    $postFields["tools"] = $sanitizedTools;
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
                        if ($localFallbackInfo) {
                            $choice['fallback_from'] = $localFallbackInfo['from'];
                            $choice['fallback_reason'] = $localFallbackInfo['reason'];
                        }
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
                    $params = $fn['parameters'] ?? ['type' => 'object', 'properties' => (object)[]];
                    if (is_array($params)) {
                        if (!isset($params['type'])) $params['type'] = 'object';
                        if (empty($params['properties']) || (is_array($params['properties']) && count($params['properties']) === 0)) {
                            $params['properties'] = (object)[];
                        } else {
                            $params['properties'] = (object)$params['properties'];
                        }
                    } elseif (is_object($params) && empty((array)$params->properties)) {
                        $params->properties = (object)[];
                    }
                    $decl['parameters'] = $params;
                    $funcDecls[] = $decl;
                }
            }
            if (!empty($funcDecls)) {
                $payload['tools'] = [['function_declarations' => $funcDecls]];
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "x-goog-api-key: " . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot-AI/2.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Google Native cURL error ({$cleanModel}): " . $curlError);
        }
        if ($httpCode !== 200 && !empty($response)) {
            $errData = json_decode($response, true);
            $errMsg = $errData['error']['message'] ?? "HTTP error {$httpCode}";
            error_log("Google Native HTTP {$httpCode} ({$cleanModel}): " . $errMsg);
        }

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

    /**
     * Executes inference on the Local Lightweight LLM (AWS EC2 Ollama Bridge).
     * Accessible via secure PHP API endpoint on EC2 Free Tier.
     */
    public static function callLocalOllama(array $messages, $userId = null, $maxTokens = 512): array {
        $endpoint = defined('LOCAL_AI_ENDPOINT') ? LOCAL_AI_ENDPOINT : 'http://13.232.195.16/api/local-ai.php';
        $apiKey = defined('LOCAL_AI_API_KEY') ? LOCAL_AI_API_KEY : '';
        $model = defined('LOCAL_AI_MODEL') ? LOCAL_AI_MODEL : 'qwen2.5:0.5b';
        $timeout = defined('LOCAL_AI_TIMEOUT') ? (int)LOCAL_AI_TIMEOUT : 15;

        $startTime = microtime(true);

        // Sanitize messages for lightweight model: keep system concise and cap history to 4 turns
        $trimmedMessages = [];
        $systemText = "";
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemText .= $m['content'] . "\n";
            } else {
                $trimmedMessages[] = $m;
            }
        }

        if (count($trimmedMessages) > 4) {
            $trimmedMessages = array_slice($trimmedMessages, -4);
        }

        $formattedMessages = [];
        if (!empty($systemText)) {
            $conciseSystem = "You are Muse, LinkPilot's helpful AI assistant. Answer clearly, accurately, and concisely in markdown.";
            $formattedMessages[] = ['role' => 'system', 'content' => $conciseSystem];
        }
        foreach ($trimmedMessages as $tm) {
            $formattedMessages[] = $tm;
        }

        $payload = [
            'model' => $model,
            'messages' => $formattedMessages,
            'max_tokens' => min($maxTokens, 512),
            'temperature' => 0.6,
            'stream' => false
        ];

        $headers = [
            'Content-Type: application/json'
        ];
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        try {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

            $rawResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrNo = curl_errno($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            if ($curlErrNo !== 0) {
                return [
                    'success' => false,
                    'error' => "cURL ({$curlErrNo}): {$curlError}",
                    'latency_ms' => $latencyMs
                ];
            }

            if ($httpCode !== 200 || empty($rawResponse)) {
                return [
                    'success' => false,
                    'error' => "HTTP {$httpCode} from local AI endpoint",
                    'latency_ms' => $latencyMs
                ];
            }

            $decoded = json_decode($rawResponse, true);
            if (!$decoded || empty($decoded['text'])) {
                return [
                    'success' => false,
                    'error' => "Empty or invalid response from local AI",
                    'latency_ms' => $latencyMs
                ];
            }

            return [
                'success' => true,
                'content' => trim($decoded['text']),
                'model' => $decoded['model'] ?? $model,
                'latency_ms' => (int)($decoded['latency_ms'] ?? $latencyMs),
                'tokens_used' => (int)(($decoded['prompt_eval_count'] ?? 0) + ($decoded['eval_count'] ?? 0)),
                'provider' => 'local_ollama'
            ];
        } catch (Throwable $e) {
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'latency_ms' => $latencyMs
            ];
        }
    }

    /**
     * Structured response validation for lightweight local model inference.
     * Rejects empty, degenerate loops, trace errors, or raw token dumps before presenting to user.
     */
    public static function validateLocalResponse(string $text, array $meta = []): array {
        $t = trim($text);

        // 1. Minimum length
        if (mb_strlen($t) < 3) {
            return ['valid' => false, 'reason' => 'Response too short (under 3 characters)'];
        }

        // 2. Fatal error or trace markers
        if (preg_match('/^(fatal error|uncaught exception|syntax error|cURL error|traceback \(most recent call last\):)/i', $t)) {
            return ['valid' => false, 'reason' => 'Response contains fatal error or traceback signature'];
        }

        // 3. Raw prompt / tokenizer leak check (<|im_start|>, <|im_end|>, etc.)
        if (preg_match('/<\|(?:im_start|im_end|endoftext)\|>/i', $t)) {
            if (mb_strlen(preg_replace('/<\|[^>]+\|>/', '', $t)) < 5) {
                return ['valid' => false, 'reason' => 'Response dominated by unparsed model tokenizer tags'];
            }
        }

        // 4. Degenerative repetition check (e.g. repeated phrase appearing 3 or more times)
        if (preg_match('/(\b[\w\s]{12,40}\b)(?:[\s,.-]+\1){2,}/iu', $t)) {
            return ['valid' => false, 'reason' => 'Degenerative repetitive phrase loop detected'];
        }

        // Vocabulary entropy check for long responses (> 35 words)
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($t), -1, PREG_SPLIT_NO_EMPTY);
        $totalWordCount = count($words);
        if ($totalWordCount >= 35) {
            $uniqueCount = count(array_unique($words));
            $diversityRatio = $uniqueCount / $totalWordCount;
            if ($diversityRatio < 0.28) {
                return ['valid' => false, 'reason' => "Low vocabulary diversity ({$uniqueCount}/{$totalWordCount} unique words)"];
            }
        }

        // 5. Abrupt mid-sentence truncation with trailing conjunction or preposition
        if (mb_strlen($t) > 60 && preg_match('/\b(and|or|because|with|that|which|to|of|in|at|for|the|a|an)\s*$/i', $t)) {
            return ['valid' => false, 'reason' => 'Response terminated abruptly with dangling conjunction or preposition'];
        }

        return ['valid' => true, 'reason' => 'Structured validation passed'];
    }

    /**
     * Checks health and resource telemetry of the Local LLM endpoint.
     */
    public static function checkLocalHealth(): array {
        $endpoint = defined('LOCAL_AI_ENDPOINT') ? LOCAL_AI_ENDPOINT : 'http://13.232.195.16/api/local-ai.php';
        $apiKey = defined('LOCAL_AI_API_KEY') ? LOCAL_AI_API_KEY : '';
        $url = $endpoint . '?action=health';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        if (!empty($apiKey)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $apiKey]);
        }
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && !empty($res)) {
            $data = json_decode($res, true);
            if (is_array($data)) {
                return array_merge(['online' => true], $data);
            }
        }

        return [
            'online' => false,
            'status' => 'offline',
            'error' => $curlErr ?: "HTTP {$httpCode}",
            'endpoint' => $endpoint
        ];
    }
}
