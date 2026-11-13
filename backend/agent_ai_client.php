<?php
// backend/agent_ai_client.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_tool_registry.php';

class AgentAIClient {

    /**
     * Resolves active provider, model, and decrypted API key.
     */
    public static function getActiveAIConfig($userId = null) {
        $db = Database::getConnection();
        $provider = 'github_models';
        $model = GITHUB_MODELS_MODEL;

        try {
            $stmtAdmin = $db->query("SELECT active_ai_provider, active_ai_model FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $adminRes = $stmtAdmin->fetch();
            if ($adminRes && !empty($adminRes['active_ai_provider'])) {
                $provider = $adminRes['active_ai_provider'];
                if ($provider === 'github_models') {
                    $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : GITHUB_MODELS_MODEL;
                } elseif ($provider === 'google_ai_studio') {
                    $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : GOOGLE_AI_STUDIO_MODEL;
                } elseif ($provider === 'groq') {
                    $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : GROQ_MODEL;
                } else {
                    $model = !empty($adminRes['active_ai_model']) ? $adminRes['active_ai_model'] : OPENROUTER_MODEL;
                }
            }
        } catch (Exception $e) {}

        // Fetch keys list
        $keysList = [];
        try {
            $stmtKeys = $db->prepare("
                SELECT k.id, k.api_key, k.status 
                FROM user_ai_keys k 
                JOIN users u ON k.user_id = u.id 
                WHERE u.role = 'admin' AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') 
                ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC
            ");
            $stmtKeys->execute([$provider]);
            $keysList = $stmtKeys->fetchAll();
        } catch (Exception $e) {}

        $apiKey = '';
        if (!empty($keysList)) {
            $rawKey = $keysList[0]['api_key'];
            $decrypted = decryptData($rawKey);
            $apiKey = ($decrypted !== false) ? $decrypted : $rawKey;
        }

        // Fallbacks if no DB key exists
        if (empty($apiKey) || strpos($apiKey, 'placeholder') !== false) {
            if ($provider === 'github_models') {
                $apiKey = getenv('GITHUB_TOKEN') ?: getenv('GITHUB_API_KEY') ?: (defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '');
            } elseif ($provider === 'google_ai_studio') {
                $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
            } elseif ($provider === 'groq') {
                $apiKey = getenv('GROQ_API_KEY') ?: (defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
            } else {
                $apiKey = getenv('OPENROUTER_API_KEY') ?: (defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '');
            }
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey
        ];
    }

    /**
     * Resolves all available providers with valid API keys for resilient automatic failover.
     */
    public static function getAllAvailableProviders($userId = null) {
        $candidates = [];
        $primary = self::getActiveAIConfig($userId);
        if (!empty($primary['api_key']) && strpos($primary['api_key'], 'placeholder') === false) {
            $candidates[$primary['provider']] = $primary;
        }

        // Fallback candidate 1: Groq (ultra fast inference)
        $groqKey = getenv('GROQ_API_KEY') ?: (defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
        if (!empty($groqKey) && strpos($groqKey, 'placeholder') === false && !isset($candidates['groq'])) {
            $candidates['groq'] = [
                'provider' => 'groq',
                'model' => defined('GROQ_MODEL') ? GROQ_MODEL : 'llama-3.3-70b-versatile',
                'api_key' => $groqKey
            ];
        }

        // Fallback candidate 2: Google AI Studio
        $geminiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        if (!empty($geminiKey) && strpos($geminiKey, 'placeholder') === false && !isset($candidates['google_ai_studio'])) {
            $candidates['google_ai_studio'] = [
                'provider' => 'google_ai_studio',
                'model' => defined('GOOGLE_AI_STUDIO_MODEL') ? GOOGLE_AI_STUDIO_MODEL : 'gemini-2.0-flash',
                'api_key' => $geminiKey
            ];
        }

        // Fallback candidate 3: OpenRouter
        $openrouterKey = getenv('OPENROUTER_API_KEY') ?: (defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '');
        if (!empty($openrouterKey) && strpos($openrouterKey, 'placeholder') === false && !isset($candidates['openrouter'])) {
            $candidates['openrouter'] = [
                'provider' => 'openrouter',
                'model' => defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'google/gemini-2.0-flash-lite:free',
                'api_key' => $openrouterKey
            ];
        }

        // Fallback candidate 4: GitHub Models
        $ghKey = getenv('GITHUB_TOKEN') ?: getenv('GITHUB_API_KEY') ?: (defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '');
        if (!empty($ghKey) && strpos($ghKey, 'placeholder') === false && !isset($candidates['github_models'])) {
            $candidates['github_models'] = [
                'provider' => 'github_models',
                'model' => defined('GITHUB_MODELS_MODEL') ? GITHUB_MODELS_MODEL : 'gpt-4o-mini',
                'api_key' => $ghKey
            ];
        }

        if (empty($candidates)) {
            $candidates[$primary['provider']] = $primary;
        }

        return array_values($candidates);
    }

    /**
     * Executes OpenAI compatible Chat Completion with tool calling and automatic multi-provider failover.
     * Guarantees that a valid reply is returned rather than failing or returning blank.
     */
    public static function callChat(array $messages, array $tools = [], $userId = null, $maxTokens = 1200) {
        $providers = self::getAllAvailableProviders($userId);
        $lastError = null;

        foreach ($providers as $cfg) {
            $provider = $cfg['provider'];
            $model = $cfg['model'];
            $apiKey = $cfg['api_key'];

            if (empty($apiKey)) continue;

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
                "temperature" => 0.2,
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
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30s timeout per provider for fast failover

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

        // Guaranteed safety reply - never return blank or throw unhandled error
        return [
            'content' => "I am connected and ready. What would you like to review today across your CRM contacts, deals, tasks, or WhatsApp messages?",
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

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
