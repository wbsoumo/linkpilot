<?php
// backend/wallet_helper.php
require_once __DIR__ . '/config.php';

// Self-healing migrations for user_email_credits table if database was not auto-migrated
try {
    $db = Database::getConnection();
    
    // Check if free_credits column exists
    $stmt = $db->query("SHOW COLUMNS FROM `user_email_credits` LIKE 'free_credits'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE `user_email_credits` ADD COLUMN `free_credits` INT DEFAULT 200");
        $db->exec("UPDATE `user_email_credits` SET `free_credits` = remaining_credits");
    }
    
    // Check if purchased_credits column exists
    $stmt = $db->query("SHOW COLUMNS FROM `user_email_credits` LIKE 'purchased_credits'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE `user_email_credits` ADD COLUMN `purchased_credits` INT DEFAULT 0");
    }
    
    // Check if last_monthly_reset_at column exists
    $stmt = $db->query("SHOW COLUMNS FROM `user_email_credits` LIKE 'last_monthly_reset_at'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE `user_email_credits` ADD COLUMN `last_monthly_reset_at` TIMESTAMP NULL DEFAULT NULL");
        $db->exec("UPDATE `user_email_credits` SET `last_monthly_reset_at` = CURRENT_TIMESTAMP");
    }

    // Seed credit records for existing users if missing
    $db->exec("INSERT IGNORE INTO `user_email_credits` (user_id, total_credits, used_credits, remaining_credits, free_credits, purchased_credits, last_monthly_reset_at) SELECT id, 200, 0, 200, 200, 0, CURRENT_TIMESTAMP FROM `users`");

} catch (Throwable $e) {
    // Silently capture migration failures
}

if (!function_exists('checkAndResetMonthlyCredits')) {
    function checkAndResetMonthlyCredits($userId) {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("SELECT * FROM user_email_credits WHERE user_id = ?");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch();
            
            if (!$wallet) {
                $stmtInsert = $db->prepare("INSERT INTO user_email_credits (user_id, total_credits, used_credits, remaining_credits, free_credits, purchased_credits, last_monthly_reset_at) VALUES (?, 200, 0, 200, 200, 0, CURRENT_TIMESTAMP)");
                $stmtInsert->execute([$userId]);
                return;
            }
            
            $lastReset = $wallet['last_monthly_reset_at'] ?? null;
            $currentMonth = date('Y-m');
            $lastResetMonth = $lastReset ? date('Y-m', strtotime($lastReset)) : null;
            
            if ($currentMonth !== $lastResetMonth) {
                $freeCredits = (int)($wallet['free_credits'] ?? 200);
                if ($freeCredits < 100) {
                    $freeCredits = 100;
                }
                $purchasedCredits = (int)($wallet['purchased_credits'] ?? 0);
                $newRemaining = $freeCredits + $purchasedCredits;
                
                $stmtUpdate = $db->prepare("UPDATE user_email_credits SET free_credits = ?, remaining_credits = ?, last_monthly_reset_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                $stmtUpdate->execute([$freeCredits, $newRemaining, $userId]);
                
                $stmtTx = $db->prepare("INSERT INTO email_credit_transactions (user_id, type, credits, amount, status, provider_used) VALUES (?, 'reset', 100, 0.00, 'success', 'system')");
                $stmtTx->execute([$userId]);
            }
        } catch (Exception $e) {
            // Silently capture
        }
    }
}

if (!function_exists('checkContactLimit')) {
    function checkContactLimit($userId) {
        $db = Database::getConnection();
        try {
            $stmtUser = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $role = $stmtUser->fetchColumn();
            if ($role === 'admin') {
                return true;
            }
            
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ?");
            $stmtCount->execute([$userId]);
            $count = (int)$stmtCount->fetchColumn();
            
            if ($count >= 100) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return true; 
        }
    }
}

if (!function_exists('callAIProvider')) {
    function callAIProvider($provider, $systemPrompt, $userPrompt, $userId = null) {
        $db = Database::getConnection();
        $model = '';

        if ($provider === 'github_models') {
            $model = GITHUB_MODELS_MODEL;
        } elseif ($provider === 'google_ai_studio') {
            $model = GOOGLE_AI_STUDIO_MODEL;
        } elseif ($provider === 'groq') {
            $model = GROQ_MODEL;
        } else {
            $model = OPENROUTER_MODEL;
        }

        try {
            $stmtAdmin = $db->query("SELECT active_ai_model FROM users WHERE role = 'admin' AND active_ai_provider = " . $db->quote($provider) . " LIMIT 1");
            $adminRes = $stmtAdmin->fetch();
            if ($adminRes && !empty($adminRes['active_ai_model'])) {
                $model = $adminRes['active_ai_model'];
            } elseif ($userId !== null) {
                $stmtUser = $db->prepare("SELECT active_ai_model FROM users WHERE id = ? AND active_ai_provider = ?");
                $stmtUser->execute([$userId, $provider]);
                $res = $stmtUser->fetch();
                if ($res && !empty($res['active_ai_model'])) {
                    $model = $res['active_ai_model'];
                }
            }
        } catch (Exception $e) {}

        $apiKeysList = [];
        try {
            if ($userId !== null) {
                $stmtKeys = $db->prepare("
                    SELECT k.id, k.api_key, k.status 
                    FROM user_ai_keys k 
                    WHERE k.user_id = ? AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') 
                    ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC
                ");
                $stmtKeys->execute([$userId, $provider]);
                $apiKeysList = $stmtKeys->fetchAll();
            }
            
            if (empty($apiKeysList)) {
                $stmtKeys = $db->prepare("
                    SELECT k.id, k.api_key, k.status 
                    FROM user_ai_keys k 
                    JOIN users u ON k.user_id = u.id 
                    WHERE u.role = 'admin' AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') 
                    ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC
                ");
                $stmtKeys->execute([$provider]);
                $apiKeysList = $stmtKeys->fetchAll();
            }
        } catch (Exception $e) {}

        if (empty($apiKeysList)) {
            if ($provider === 'github_models') {
                $apiKey = getenv('GITHUB_API_KEY') ?: getenv('GITHUB_TOKEN') ?: (defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '');
                if (!empty($apiKey) && strpos($apiKey, 'placeholder') === false) {
                    $apiKeysList[] = ['id' => null, 'api_key' => encryptData($apiKey), 'status' => 'active'];
                }
            } elseif ($provider === 'google_ai_studio') {
                $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
                if (!empty($apiKey) && strpos($apiKey, 'placeholder') === false) {
                    $apiKeysList[] = ['id' => null, 'api_key' => encryptData($apiKey), 'status' => 'active'];
                }
            } elseif ($provider === 'groq') {
                $apiKey = getenv('GROQ_API_KEY') ?: (defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
                if (!empty($apiKey) && strpos($apiKey, 'placeholder') === false) {
                    $apiKeysList[] = ['id' => null, 'api_key' => encryptData($apiKey), 'status' => 'active'];
                }
            } else {
                $apiKey = getenv('OPENROUTER_API_KEY') ?: (defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '');
                if (!empty($apiKey) && strpos($apiKey, 'placeholder') === false) {
                    $apiKeysList[] = ['id' => null, 'api_key' => encryptData($apiKey), 'status' => 'active'];
                }
            }
        }

        if (empty($apiKeysList)) {
            throw new Exception("No active API keys found for provider: '{$provider}'");
        }

        $errors = [];
        foreach ($apiKeysList as $keyRow) {
            $keyId = $keyRow['id'];
            $encryptedKey = $keyRow['api_key'];
            $apiKey = decryptData($encryptedKey);

            if (!$apiKey) {
                $apiKey = $encryptedKey; 
            }

            try {
                if ($provider === 'github_models') {
                    $headers = [
                        "Authorization: Bearer " . $apiKey,
                        "Content-Type: application/json",
                        "User-Agent: LinkPilot-AI"
                    ];

                    $postFields = [
                        "model" => $model,
                        "messages" => [
                            ["role" => "system", "content" => $systemPrompt],
                            ["role" => "user", "content" => $userPrompt]
                        ],
                        "max_tokens" => 1000
                    ];

                    $ch = curl_init("https://models.inference.ai.azure.com/chat/completions");
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    curl_close($ch);

                    if ($error) {
                        throw new Exception("GitHub Models connection error: " . $error);
                    }

                    $data = json_decode($response, true);
                    if ($httpCode !== 200) {
                        if ($httpCode === 401) {
                            throw new Exception("401: Unauthorized: Invalid GitHub Personal Access Token.");
                        }
                        if ($httpCode === 429 || (isset($data['error']['message']) && strpos(strtolower($data['error']['message']), 'limit') !== false)) {
                            throw new Exception("429: Rate limit exceeded.");
                        }
                        $msg = $data['message'] ?? ($data['error']['message'] ?? "Unknown GitHub Models Error");
                        throw new Exception("GitHub Models API Error (HTTP {$httpCode}): " . $msg);
                    }

                    if ($keyId !== null) {
                        $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = ?");
                        $stmtUpdate->execute([$keyId]);
                        $stmtLog = $db->prepare("INSERT INTO user_ai_key_logs (key_id) VALUES (?)");
                        $stmtLog->execute([$keyId]);
                    }

                    $generatedText = $data['choices'][0]['message']['content'] ?? '';
                    $tokensUsed = $data['usage']['total_tokens'] ?? 0;

                    return [
                        "text" => trim($generatedText),
                        "tokens" => $tokensUsed
                    ];

                } elseif ($provider === 'groq') {
                    $groqModelsToTry = array_unique([
                        $model,
                        'openai/gpt-oss-120b',
                        'llama-3.3-70b-versatile',
                        'llama-3.1-8b-instant',
                        'deepseek-r1-distill-llama-70b'
                    ]);
                    $lastGroqError = '';

                    foreach ($groqModelsToTry as $currentGroqModel) {
                        if (empty($currentGroqModel)) continue;
                        try {
                            $headers = [
                                "Authorization: Bearer " . $apiKey,
                                "Content-Type: application/json",
                                "User-Agent: LinkPilot-AI"
                            ];

                            $postFields = [
                                "model" => $currentGroqModel,
                                "messages" => [
                                    ["role" => "system", "content" => $systemPrompt],
                                    ["role" => "user", "content" => $userPrompt]
                                ],
                                "max_tokens" => 1000
                            ];

                            $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $error = curl_error($ch);
                            curl_close($ch);

                            if ($error) {
                                throw new Exception("Groq connection error: " . $error);
                            }

                            $data = json_decode($response, true);
                            if ($httpCode !== 200) {
                                if ($httpCode === 401) {
                                    throw new Exception("401: Unauthorized: Invalid Groq API Key.");
                                }
                                if ($httpCode === 429 || (isset($data['error']['message']) && strpos(strtolower($data['error']['message']), 'limit') !== false)) {
                                    throw new Exception("429: Rate limit exceeded.");
                                }
                                $msg = $data['error']['message'] ?? "Unknown Groq API Error";
                                throw new Exception("Groq API Error (HTTP {$httpCode}) with model {$currentGroqModel}: " . $msg);
                            }

                            if ($keyId !== null) {
                                $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = ?");
                                $stmtUpdate->execute([$keyId]);
                                $stmtLog = $db->prepare("INSERT INTO user_ai_key_logs (key_id) VALUES (?)");
                                $stmtLog->execute([$keyId]);
                            }

                            $generatedText = $data['choices'][0]['message']['content'] ?? '';
                            $tokensUsed = $data['usage']['total_tokens'] ?? 0;

                            return [
                                "text" => trim($generatedText),
                                "tokens" => $tokensUsed
                            ];
                        } catch (Exception $gEx) {
                            $lastGroqError = $gEx->getMessage();
                            if (strpos($lastGroqError, '401:') !== false || strpos($lastGroqError, '429:') !== false) {
                                throw $gEx;
                            }
                        }
                    }
                    throw new Exception("Groq provider failed. Last error: " . $lastGroqError);

                } elseif ($provider === 'google_ai_studio') {
                    $headers = [
                        "Authorization: Bearer " . $apiKey,
                        "Content-Type: application/json"
                    ];

                    $postFields = [
                        "model" => $model,
                        "messages" => [
                            ["role" => "system", "content" => $systemPrompt],
                            ["role" => "user", "content" => $userPrompt]
                        ],
                        "max_tokens" => 1000
                    ];

                    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/openai/chat/completions");
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot-AI/1.0');
                    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    curl_close($ch);

                    if ($error) {
                        throw new Exception("Google AI Studio connection error: " . $error);
                    }

                    $data = json_decode($response, true);
                    if ($httpCode !== 200) {
                        if ($httpCode === 401) {
                            throw new Exception("401: Unauthorized: Invalid Google Gemini API Key.");
                        }
                        if ($httpCode === 429 || (isset($data['error']['message']) && strpos(strtolower($data['error']['message']), 'limit') !== false)) {
                            throw new Exception("429: Rate limit exceeded.");
                        }
                        $msg = $data['message'] ?? ($data['error']['message'] ?? "Unknown Google AI Studio Error");
                        throw new Exception("Google AI Studio API Error (HTTP {$httpCode}): " . $msg);
                    }

                    if ($keyId !== null) {
                        $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = ?");
                        $stmtUpdate->execute([$keyId]);
                        $stmtLog = $db->prepare("INSERT INTO user_ai_key_logs (key_id) VALUES (?)");
                        $stmtLog->execute([$keyId]);
                    }

                    $generatedText = $data['choices'][0]['message']['content'] ?? '';
                    $tokensUsed = $data['usage']['total_tokens'] ?? 0;

                    return [
                        "text" => trim($generatedText),
                        "tokens" => $tokensUsed
                    ];

                } else {
                    $modelsToTry = [
                        $model,
                        'meta-llama/llama-3.3-70b-instruct:free',
                        'google/gemini-2.5-flash',
                        'meta-llama/llama-3.1-8b-instruct:free',
                        'google/gemini-2.0-flash-lite:free',
                        'deepseek/deepseek-r1:free'
                    ];
                    $modelsToTry = array_unique($modelsToTry);
                    $lastError = '';

                    foreach ($modelsToTry as $currentModel) {
                        try {
                            $headers = [
                                "Authorization: Bearer " . $apiKey,
                                "Content-Type: application/json",
                                "HTTP-Referer: https://linkpilot.work",
                                "X-Title: LinkPilot AI"
                            ];

                            $postFields = [
                                "model" => $currentModel,
                                "messages" => [
                                    ["role" => "system", "content" => $systemPrompt],
                                    ["role" => "user", "content" => $userPrompt]
                                ],
                                "max_tokens" => 1000
                            ];

                            $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $error = curl_error($ch);
                            curl_close($ch);

                            if ($error) {
                                throw new Exception("OpenRouter connection error: " . $error);
                            }

                            $data = json_decode($response, true);
                            if ($httpCode !== 200) {
                                if ($httpCode === 401 || (isset($data['error']['code']) && $data['error']['code'] === 401)) {
                                    throw new Exception("401: Unauthorized: Invalid OpenRouter API Key.");
                                }
                                if ($httpCode === 429 || (isset($data['error']['message']) && strpos(strtolower($data['error']['message']), 'limit') !== false)) {
                                    throw new Exception("429: Rate limit exceeded.");
                                }
                                $msg = $data['error']['message'] ?? "Unknown OpenRouter Error";
                                throw new Exception("OpenRouter API Error (HTTP {$httpCode}) with model {$currentModel}: " . $msg);
                            }

                            if ($keyId !== null) {
                                $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = ?");
                                $stmtUpdate->execute([$keyId]);
                                $stmtLog = $db->prepare("INSERT INTO user_ai_key_logs (key_id) VALUES (?)");
                                $stmtLog->execute([$keyId]);
                            }

                            $generatedText = $data['choices'][0]['message']['content'] ?? '';
                            $tokensUsed = $data['usage']['total_tokens'] ?? 0;

                            return [
                                "text" => trim($generatedText),
                                "tokens" => $tokensUsed
                            ];
                        } catch (Exception $ex) {
                            $lastError = $ex->getMessage();
                            if (strpos($lastError, '401:') !== false || strpos($lastError, '429:') !== false) {
                                throw $ex;
                            }
                        }
                    }

                    throw new Exception("Failed to generate content using OpenRouter. Last error: " . $lastError);
                }
            } catch (Exception $ex) {
                $errMessage = $ex->getMessage();
                $errors[] = "Key ID " . ($keyId ?? 'ENV') . ": " . $errMessage;

                if ($keyId !== null) {
                    $status = 'limit_exceeded';
                    if (strpos($errMessage, '401:') !== false || strpos($errMessage, 'Unauthorized') !== false) {
                        $status = 'invalid';
                    }
                    try {
                        $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = ?, error_message = ? WHERE id = ?");
                        $stmtUpdate->execute([$status, $errMessage, $keyId]);
                    } catch (Exception $dbEx) {}
                }
                continue;
            }
        }

        throw new Exception("All configured API keys for provider '{$provider}' failed. Details:\n" . implode("\n", $errors));
    }
}

if (!function_exists('callAI')) {
    function callAI($systemPrompt, $userPrompt, $userId = null) {
        $primaryProvider = 'google_ai_studio';
        $db = Database::getConnection();

        try {
            if ($userId !== null) {
                $stmtUser = $db->prepare("SELECT active_ai_provider FROM users WHERE id = ?");
                $stmtUser->execute([$userId]);
                $res = $stmtUser->fetch();
                if ($res && !empty($res['active_ai_provider'])) {
                    $primaryProvider = $res['active_ai_provider'];
                }
            }
            if (empty($primaryProvider) || $primaryProvider === 'google_ai_studio') {
                $stmtAdmin = $db->query("SELECT active_ai_provider FROM users WHERE role = 'admin' AND active_ai_provider IS NOT NULL AND active_ai_provider != '' ORDER BY id ASC LIMIT 1");
                $adminRes = $stmtAdmin->fetch();
                if ($adminRes && !empty($adminRes['active_ai_provider'])) {
                    $primaryProvider = $adminRes['active_ai_provider'];
                }
            }
        } catch (Exception $e) {}

        // Construct failover chain starting with primary provider
        $allProviders = ['google_ai_studio', 'groq', 'github_models', 'openrouter'];
        $failoverChain = array_merge([$primaryProvider], array_diff($allProviders, [$primaryProvider]));

        $providerErrors = [];
        foreach ($failoverChain as $currentProvider) {
            try {
                return callAIProvider($currentProvider, $systemPrompt, $userPrompt, $userId);
            } catch (Exception $pEx) {
                $providerErrors[] = "[{$currentProvider}]: " . $pEx->getMessage();
            }
        }

        throw new Exception("All AI Providers in failover chain failed:\n" . implode("\n", $providerErrors));
    }
}

if (!function_exists('callOpenRouter')) {
    function callOpenRouter($systemPrompt, $userPrompt, $userId = null) {
        return callAI($systemPrompt, $userPrompt, $userId);
    }
}

if (!function_exists('testAIKeyConnection')) {
    function testAIKeyConnection($provider, $apiKey) {
        $systemPrompt = "You are a connectivity test bot. Reply with one word: Success.";
        $userPrompt = "ping";

        if ($provider === 'groq') {
            $modelsToTry = [
                'openai/gpt-oss-120b',
                'llama-3.3-70b-versatile',
                'llama-3.1-8b-instant',
                'deepseek-r1-distill-llama-70b'
            ];
            $lastException = null;

            foreach ($modelsToTry as $currentModel) {
                $headers = [
                    "Authorization: Bearer " . $apiKey,
                    "Content-Type: application/json",
                    "User-Agent: LinkPilot-AI"
                ];
                $postFields = [
                    "model" => $currentModel,
                    "messages" => [
                        ["role" => "system", "content" => $systemPrompt],
                        ["role" => "user", "content" => $userPrompt]
                    ],
                    "max_tokens" => 5
                ];

                $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
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

                if (!$error && $httpCode === 200) {
                    return true; // Connection & model test succeeded
                }

                if ($httpCode === 401) {
                    throw new Exception("401: Unauthorized: Invalid Groq API Key.");
                }

                if ($error) {
                    $lastException = new Exception("Groq connection failed ({$currentModel}): " . $error);
                } else {
                    $data = json_decode($response, true);
                    $msg = $data['error']['message'] ?? "HTTP error {$httpCode}";
                    $lastException = new Exception("Groq API ({$currentModel}): {$msg}");
                }
            }

            if ($lastException) {
                throw $lastException;
            }
            return true;

        } elseif ($provider === 'github_models') {
            $model = "gpt-4o-mini";
            $headers = [
                "Authorization: Bearer " . $apiKey,
                "Content-Type: application/json",
                "User-Agent: LinkPilot-AI"
            ];
            $postFields = [
                "model" => $model,
                "messages" => [
                    ["role" => "system", "content" => $systemPrompt],
                    ["role" => "user", "content" => $userPrompt]
                ],
                "max_tokens" => 5
            ];
            
            $ch = curl_init("https://models.inference.ai.azure.com/chat/completions");
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
                throw new Exception("Connection failed: " . $error);
            }
            $data = json_decode($response, true);
            if ($httpCode !== 200) {
                $msg = $data['message'] ?? ($data['error']['message'] ?? "HTTP error {$httpCode}");
                throw new Exception($msg);
            }
            return true;
            
        } elseif ($provider === 'google_ai_studio') {
            $lastException = null;
            $modelsToTry = ['gemini-3.1-flash-lite', 'gemini-3.6-flash', 'gemini-2.5-flash', 'gemini-flash-latest'];
            
            // Retry loop up to 3 attempts across models/endpoints
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $currentModel = $modelsToTry[($attempt - 1) % count($modelsToTry)];
                
                // Attempt via native v1beta generateContent endpoint
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$currentModel}:generateContent?key=" . urlencode($apiKey);
                $postFields = [
                    "contents" => [
                        ["parts" => [["text" => "hi"]]]
                    ]
                ];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if (!$error && $httpCode === 200) {
                    return true; // Test succeeded
                }
                
                if ($error) {
                    $lastException = new Exception("Connection attempt {$attempt} failed ({$currentModel}): " . $error);
                } else {
                    $data = json_decode($response, true);
                    $msg = $data['error']['message'] ?? "HTTP error {$httpCode}";
                    $lastException = new Exception("HTTP {$httpCode} ({$currentModel}): {$msg}");
                }
                
                // Small 300ms pause before retry
                usleep(300000);
            }
            
            if ($lastException) {
                throw $lastException;
            }
            return true;
            
        } else { // openrouter
            $model = "google/gemini-2.5-flash";
            $headers = [
                "Authorization: Bearer " . $apiKey,
                "Content-Type: application/json",
                "HTTP-Referer: https://linkpilot.work",
                "X-Title: LinkPilot AI"
            ];
            $postFields = [
                "model" => $model,
                "messages" => [
                    ["role" => "system", "content" => $systemPrompt],
                    ["role" => "user", "content" => $userPrompt]
                ],
                "max_tokens" => 5
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
                throw new Exception("Connection failed: " . $error);
            }
            $data = json_decode($response, true);
            if ($httpCode !== 200) {
                $msg = $data['error']['message'] ?? "HTTP error {$httpCode}";
                throw new Exception($msg);
            }
            return true;
        }
    }
}
