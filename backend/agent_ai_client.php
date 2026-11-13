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
                $stmt = $db->query("SELECT provider, api_key, status FROM user_ai_keys WHERE user_id IN ($inClause) AND status NOT IN ('invalid', 'paused') ORDER BY id ASC");
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

        // 5. Default model mappings
        $modelMap = [
            'google_ai_studio' => defined('GOOGLE_AI_STUDIO_MODEL') ? GOOGLE_AI_STUDIO_MODEL : 'gemini-2.0-flash',
            'groq' => defined('GROQ_MODEL') ? GROQ_MODEL : 'llama-3.3-70b-versatile',
            'openrouter' => defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'google/gemini-2.0-flash-lite:free',
            'github_models' => defined('GITHUB_MODELS_MODEL') ? GITHUB_MODELS_MODEL : 'gpt-4o-mini'
        ];

        // Ensure active provider is tested first
        $providerOrder = array_unique(array_merge([$activeProvider], ['google_ai_studio', 'groq', 'openrouter', 'github_models']));

        foreach ($providerOrder as $prov) {
            if (!empty($dbKeys[$prov])) {
                $provModel = ($prov === $activeProvider && !empty($activeModel)) ? $activeModel : ($modelMap[$prov] ?? 'gpt-4o-mini');
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
                'model' => 'google/gemini-2.0-flash-lite:free',
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
     * Fast 8s timeout prevents hanging and guarantees quick responses.
     */
    public static function callChat(array $messages, array $tools = [], $userId = null, $maxTokens = 1000) {
        $providers = self::getAllAvailableProviders($userId);
        $lastError = null;

        foreach ($providers as $cfg) {
            $provider = $cfg['provider'];
            $model = $cfg['model'];
            $apiKey = $cfg['api_key'];

            if (empty($apiKey) || strpos($apiKey, 'placeholder') !== false) continue;

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
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4); // 4s connect timeout
                curl_setopt($ch, CURLOPT_TIMEOUT, 8); // 8s fast execution timeout

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
                    if (!empty($tools) && (strpos($errMsg, 'tools') !== false || strpos($errMsg, 'functions') !== false)) {
                        unset($postFields['tools']);
                        unset($postFields['tool_choice']);
                        $retryResult = self::callRawCurl($endpoint, $headers, $postFields);
                        if (!empty($retryResult['content']) || !empty($retryResult['tool_calls'])) {
                            return $retryResult;
                        }
                    }
                    throw new Exception("Provider {$provider} HTTP {$httpCode}: " . $errMsg);
                }

                $choice = self::extractChoice($data);
                if (!empty($choice['content']) || !empty($choice['tool_calls'])) {
                    return $choice;
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                error_log("AI Provider Failover: {$provider} failed ({$lastError}). Trying next candidate...");
                continue;
            }
        }

        // If all providers failed, throw or return contextual notice
        return [
            'content' => "I am having trouble reaching the AI model right now due to a network or authentication timeout. Please check your AI API key in Settings > Integrations.",
            'tool_calls' => [],
            'tokens_used' => 0
        ];
    }

    private static function callRawCurl($endpoint, $headers, $postFields) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return self::extractChoice($data);
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
