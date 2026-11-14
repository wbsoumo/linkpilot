<?php
// backend/api/profile/save_openrouter.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../wallet_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$openrouterKey = isset($input['openrouter_key']) ? trim($input['openrouter_key']) : null;
$githubKey = isset($input['github_key']) ? trim($input['github_key']) : null;
$googleKey = isset($input['google_key']) ? trim($input['google_key']) : null;
$groqKey = isset($input['groq_key']) ? trim($input['groq_key']) : null;
$activeProvider = isset($input['active_ai_provider']) ? trim($input['active_ai_provider']) : null;
$activeModel = isset($input['active_ai_model']) ? trim($input['active_ai_model']) : null;
$isTestOnly = !empty($input['test_only']) || ($input['action'] ?? '') === 'test_connection';
$testBeforeSave = !empty($input['test_and_save']) || $isTestOnly || true; // Always validate keys on save

// Sanitize active model if obsolete
if ($activeModel) {
    if (strpos($activeModel, 'gemini-3.1') !== false || strpos($activeModel, 'gemini-3.6') !== false) {
        $activeModel = 'gemini-2.5-flash';
    }
}

$db = Database::getConnection();

try {
    // 1. Validation / Test Key Check
    // If user is testing or providing a new key, test connectivity before saving
    $keyTestTargets = [
        'google_ai_studio' => $googleKey,
        'groq' => $groqKey,
        'openrouter' => $openrouterKey,
        'github_models' => $githubKey
    ];

    $testedAny = false;
    foreach ($keyTestTargets as $prov => $kVal) {
        if (!empty($kVal) && strpos($kVal, '•') === false && strpos($kVal, 'placeholder') === false) {
            try {
                $testModel = ($prov === 'google_ai_studio') ? $activeModel : null;
                testAIKeyConnection($prov, $kVal, $testModel);
                $testedAny = true;
            } catch (Throwable $e) {
                sendJsonResponse('error', "Validation failed for {$prov}: " . $e->getMessage() . " Please check your key and try again.", [
                    'provider' => $prov,
                    'error' => $e->getMessage()
                ], 400);
            }
        }
    }

    // If test only, check if we need to test existing key if no raw key was passed
    if ($isTestOnly) {
        $prov = $activeProvider ?: 'google_ai_studio';
        $kVal = $keyTestTargets[$prov] ?? null;
        if (empty($kVal) || strpos($kVal, '•') !== false) {
            // Retrieve existing key from DB to test
            if ($prov === 'google_ai_studio') {
                $stmtK = $db->prepare("SELECT google_key FROM users WHERE id = ?");
                $stmtK->execute([$userId]);
                $encK = $stmtK->fetchColumn();
                if ($encK) {
                    $kVal = decryptData($encK);
                }
            }
        }
        if (empty($kVal)) {
            sendJsonResponse('error', "No API key configured to test. Please enter a valid Google Gemini API Key.", [], 400);
        }
        $testModel = ($prov === 'google_ai_studio') ? $activeModel : null;
        try {
            testAIKeyConnection($prov, $kVal, $testModel);
            sendJsonResponse('success', "API Key connectivity test succeeded! Model '" . ($testModel ?: 'gemini-2.5-flash') . "' is responding normally.");
        } catch (Throwable $e) {
            sendJsonResponse('error', "Connectivity test failed: " . $e->getMessage(), [
                'provider' => $prov,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    // If saving and no new key passed, verify existing saved key against selected model
    if (!$testedAny && ($activeProvider === 'google_ai_studio' || empty($activeProvider)) && !empty($activeModel)) {
        $stmtK = $db->prepare("SELECT google_key FROM users WHERE id = ?");
        $stmtK->execute([$userId]);
        $encK = $stmtK->fetchColumn();
        if ($encK) {
            $existingK = decryptData($encK);
            if ($existingK) {
                try {
                    testAIKeyConnection('google_ai_studio', $existingK, $activeModel);
                } catch (Throwable $e) {
                    sendJsonResponse('error', "Verification failed for model {$activeModel} with saved Gemini key: " . $e->getMessage(), [], 400);
                }
            }
        }
    }

    // 2. Start building update query for users table
    $updates = [];
    $params = [];
    
    // Process OpenRouter Key
    if ($openrouterKey !== null) {
        if ($openrouterKey === '') {
            $updates[] = "openrouter_key = NULL";
        } elseif (strpos($openrouterKey, '•') === false) {
            $updates[] = "openrouter_key = ?";
            $params[] = encryptData($openrouterKey);
            // Upsert into user_ai_keys
            upsertUserAIKey($db, $userId, 'openrouter', $openrouterKey);
        }
    }
    
    // Process GitHub Key
    if ($githubKey !== null) {
        if ($githubKey === '') {
            $updates[] = "github_key = NULL";
        } elseif (strpos($githubKey, '•') === false) {
            $updates[] = "github_key = ?";
            $params[] = encryptData($githubKey);
            upsertUserAIKey($db, $userId, 'github_models', $githubKey);
        }
    }
    
    // Process Google Key
    if ($googleKey !== null) {
        if ($googleKey === '') {
            $updates[] = "google_key = NULL";
        } elseif (strpos($googleKey, '•') === false) {
            $updates[] = "google_key = ?";
            $params[] = encryptData($googleKey);
            upsertUserAIKey($db, $userId, 'google_ai_studio', $googleKey);
        }
    }

    // Process Groq Key
    if ($groqKey !== null) {
        if ($groqKey !== '' && strpos($groqKey, '•') === false) {
            upsertUserAIKey($db, $userId, 'groq', $groqKey);
        }
    }
    
    // Process Active Provider
    if ($activeProvider !== null) {
        if (in_array($activeProvider, ['openrouter', 'github_models', 'google_ai_studio', 'groq'])) {
            $updates[] = "active_ai_provider = ?";
            $params[] = $activeProvider;
        } else {
            sendJsonResponse('error', 'Invalid AI provider selected.', [], 400);
        }
    }
    
    // Process Active Model
    if ($activeModel !== null) {
        if ($activeModel === '') {
            $updates[] = "active_ai_model = NULL";
        } else {
            $updates[] = "active_ai_model = ?";
            $params[] = $activeModel;
        }
    }
    
    if (count($updates) > 0) {
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $userId;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
    
    logActivity($userId, "Updated AI Settings & Configurations (Tested & Verified).");
    
    sendJsonResponse('success', 'AI settings tested and saved successfully.');
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error saving AI settings: ' . $e->getMessage(), [], 500);
}

function upsertUserAIKey(PDO $db, int $userId, string $provider, string $rawKey) {
    try {
        $encrypted = encryptData($rawKey);
        $stmtCheck = $db->prepare("SELECT id FROM user_ai_keys WHERE user_id = ? AND provider = ? LIMIT 1");
        $stmtCheck->execute([$userId, $provider]);
        $existingId = $stmtCheck->fetchColumn();
        if ($existingId) {
            $stmtUp = $db->prepare("UPDATE user_ai_keys SET api_key = ?, status = 'active', error_message = NULL, updated_at = NOW() WHERE id = ?");
            $stmtUp->execute([$encrypted, $existingId]);
        } else {
            $stmtIns = $db->prepare("INSERT INTO user_ai_keys (user_id, provider, api_key, status) VALUES (?, ?, ?, 'active')");
            $stmtIns->execute([$userId, $provider, $encrypted]);
        }
    } catch (Throwable $e) {}
}
