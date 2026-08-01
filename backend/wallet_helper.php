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

if (!function_exists('callAI')) {
    function callAI($systemPrompt, $userPrompt, $userId = null) {
        $provider = 'github_models';
        $model = GITHUB_MODELS_MODEL;

        $db = Database::getConnection();
        
        try {
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
            } else {
                if ($userId !== null) {
                    $stmtUser = $db->prepare("SELECT active_ai_provider, active_ai_model FROM users WHERE id = ?");
                    $stmtUser->execute([$userId]);
                    $res = $stmtUser->fetch();
                    if ($res) {
                        if (!empty($res['active_ai_provider'])) {
                            $provider = $res['active_ai_provider'];
                        }
                        if ($provider === 'github_models') {
                            $model = !empty($res['active_ai_model']) ? $res['active_ai_model'] : GITHUB_MODELS_MODEL;
                        } elseif ($provider === 'google_ai_studio') {
                            $model = !empty($res['active_ai_model']) ? $res['active_ai_model'] : GOOGLE_AI_STUDIO_MODEL;
                        } else {
                            $model = !empty($res['active_ai_model']) ? $res['active_ai_model'] : OPENROUTER_MODEL;
                        }
                    }
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
                $apiKey = getenv('GEMINI_API_KEY') ?: '';
                if (!empty($apiKey) && strpos($apiKey, 'placeholder') === false) {
                    $apiKeysList[] = ['id' => null, 'api_key' => encryptData($apiKey), 'status' => 'active'];
                }
            } else {
                $apiKey = getenv('OPENROUTER_API_KEY') ?: '';
                if (!empty($apiKey) && strpos($apiKey, 'placeholder') === false) {
                    $apiKeysList[] = ['id' => null, 'api_key' => encryptData($apiKey), 'status' => 'active'];
                }
            }
        }

        if (empty($apiKeysList)) {
            throw new Exception("No active API keys found for central provider: '{$provider}'");
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
                        ]
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
                        ]
                    ];

                    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/openai/chat/completions");
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
                        'google/gemini-2.5-flash:free',
                        'google/gemini-2.5-flash',
                        'meta-llama/llama-3.3-70b-instruct:free',
                        'google/gemini-2.0-flash-lite:free',
                        'meta-llama/llama-3.1-8b-instruct:free',
                        'meta-llama/llama-3-8b-instruct:free',
                        'google/gemini-2.0-flash-exp'
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
                                ]
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

                    throw new Exception("Failed to generate outreach content using OpenRouter. Last error: " . $lastError);
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

if (!function_exists('callOpenRouter')) {
    function callOpenRouter($systemPrompt, $userPrompt, $userId = null) {
        return callAI($systemPrompt, $userPrompt, $userId);
    }
}

if (!function_exists('testAIKeyConnection')) {
    function testAIKeyConnection($provider, $apiKey) {
        $systemPrompt = "You are a connectivity test bot. Reply with one word: Success.";
        $userPrompt = "ping";
        
        if ($provider === 'github_models') {
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
            $model = "gemini-2.5-flash";
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
                "max_tokens" => 5
            ];
            
            $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/openai/chat/completions");
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
            
        } else { // openrouter
            $model = "google/gemini-2.0-flash-lite:free";
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
