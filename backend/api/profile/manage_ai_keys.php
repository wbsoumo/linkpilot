<?php
// backend/api/profile/manage_ai_keys.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../wallet_helper.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        // Retrieve keys safely
        $rows = [];
        try {
            $stmt = $db->prepare("
                SELECT k.id, k.provider, k.api_key, k.status, k.error_message, k.created_at, k.updated_at
                FROM user_ai_keys k
                WHERE k.user_id = ?
                ORDER BY k.id ASC
            ");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {}

        $keys = [];
        foreach ($rows as $row) {
            $decrypted = decryptData($row['api_key']);
            $rawKey = ($decrypted !== false) ? $decrypted : $row['api_key'];
            
            // Mask the key (showing only last 4 characters)
            $len = strlen($rawKey);
            $last4 = ($len > 4) ? substr($rawKey, -4) : $rawKey;
            $masked = str_repeat('•', min(8, max(4, $len - 4))) . $last4;

            $totalCalls = 0;
            $calls24h = 0;
            try {
                $stmtLogs = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN created_at >= NOW() - INTERVAL 1 DAY THEN 1 ELSE 0 END) as c24h FROM user_ai_key_logs WHERE key_id = ?");
                $stmtLogs->execute([$row['id']]);
                $logRow = $stmtLogs->fetch();
                if ($logRow) {
                    $totalCalls = (int)$logRow['total'];
                    $calls24h = (int)$logRow['c24h'];
                }
            } catch (Exception $ex) {}

            $keys[] = [
                'id' => $row['id'],
                'provider' => $row['provider'],
                'masked_key' => $masked,
                'status' => $row['status'],
                'error_message' => $row['error_message'],
                'total_calls' => $totalCalls,
                'calls_24h' => $calls24h,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }

        sendJsonResponse('success', 'AI Keys loaded.', ['keys' => $keys]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            sendJsonResponse('error', 'Invalid JSON input', [], 400);
        }

        $action = isset($input['action']) ? trim($input['action']) : 'add';

        if ($action === 'add') {
            $provider = isset($input['provider']) ? trim($input['provider']) : '';
            $apiKey = isset($input['api_key']) ? trim($input['api_key']) : '';

            if (empty($provider) || empty($apiKey)) {
                sendJsonResponse('error', 'Provider and API Key are required fields.', [], 400);
            }

            if (!in_array($provider, ['openrouter', 'github_models', 'google_ai_studio', 'groq'])) {
                sendJsonResponse('error', 'Invalid AI Provider specified.', [], 400);
            }

            // Test and Validate key before saving ("always check while taking an API key, like test and save")
            try {
                testAIKeyConnection($provider, $apiKey);
            } catch (Throwable $e) {
                sendJsonResponse('error', "API Key verification failed for {$provider}: " . $e->getMessage() . " The key was not saved.", [
                    'provider' => $provider,
                    'error' => $e->getMessage()
                ], 400);
            }

            // Encrypt and insert key
            $encrypted = encryptData($apiKey);
            $stmt = $db->prepare("INSERT INTO user_ai_keys (user_id, provider, api_key, status, error_message) VALUES (?, ?, ?, 'active', NULL)");
            $stmt->execute([$userId, $provider, $encrypted]);

            logActivity($userId, "Added and verified new API key for provider: {$provider}");
            sendJsonResponse('success', "API Key verified and saved successfully! Provider is now active.");

        } elseif ($action === 'toggle_provider') {
            $provider = isset($input['provider']) ? trim($input['provider']) : '';
            $enabled = !empty($input['enabled']); // boolean true (ON) or false (OFF)
            $newStatus = $enabled ? 'active' : 'paused';

            if (!in_array($provider, ['openrouter', 'github_models', 'google_ai_studio', 'groq'])) {
                sendJsonResponse('error', 'Invalid provider.', [], 400);
            }

            // If enabling, test existing keys for this provider first!
            if ($enabled) {
                $stmtCheck = $db->prepare("SELECT id, api_key FROM user_ai_keys WHERE user_id = ? AND provider = ? ORDER BY id DESC LIMIT 1");
                $stmtCheck->execute([$userId, $provider]);
                $keyRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$keyRow) {
                    sendJsonResponse('error', "Cannot turn ON {$provider}: No API key configured. Please add an API key first.", [], 400);
                }

                $decrypted = decryptData($keyRow['api_key']);
                $apiKey = ($decrypted !== false) ? $decrypted : $keyRow['api_key'];

                try {
                    testAIKeyConnection($provider, $apiKey);
                } catch (Throwable $e) {
                    // Update error message and keep paused
                    $stmtErr = $db->prepare("UPDATE user_ai_keys SET status = 'paused', error_message = ? WHERE id = ?");
                    $stmtErr->execute([$e->getMessage(), $keyRow['id']]);
                    sendJsonResponse('error', "Cannot turn ON {$provider}: Verification test failed: " . $e->getMessage(), [], 400);
                }
            }

            // Update status
            $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = ?, error_message = NULL WHERE user_id = ? AND provider = ?");
            $stmtUpdate->execute([$newStatus, $userId, $provider]);

            logActivity($userId, "Toggled provider {$provider} to {$newStatus}");
            sendJsonResponse('success', "Provider {$provider} turned " . ($enabled ? 'ON (Active)' : 'OFF (Paused)') . ".");

        } elseif ($action === 'update' || $action === 'toggle_status') {
            if (empty($input['id'])) {
                sendJsonResponse('error', 'Invalid input parameters.', [], 400);
            }

            $keyId = (int)$input['id'];

            // Verify key ownership
            $stmtCheck = $db->prepare("SELECT provider, api_key, status FROM user_ai_keys WHERE id = ? AND user_id = ?");
            $stmtCheck->execute([$keyId, $userId]);
            $keyRow = $stmtCheck->fetch();
            if (!$keyRow) {
                sendJsonResponse('error', 'Key not found or access denied.', [], 404);
            }

            $updates = [];
            $params = [];

            // Toggle status (Pause/Active)
            if (isset($input['status'])) {
                $status = trim($input['status']);
                if (in_array($status, ['active', 'paused', 'limit_exceeded', 'invalid'])) {
                    // If activating, test connection first!
                    if ($status === 'active') {
                        $decrypted = decryptData($keyRow['api_key']);
                        $apiKey = ($decrypted !== false) ? $decrypted : $keyRow['api_key'];
                        try {
                            testAIKeyConnection($keyRow['provider'], $apiKey);
                            $updates[] = "error_message = NULL";
                        } catch (Throwable $e) {
                            sendJsonResponse('error', "Cannot activate key: Verification test failed: " . $e->getMessage(), [], 400);
                        }
                    }
                    $updates[] = "status = ?";
                    $params[] = $status;
                } else {
                    sendJsonResponse('error', 'Invalid status specified.', [], 400);
                }
            }

            // Edit key value
            if (isset($input['api_key'])) {
                $apiKey = trim($input['api_key']);
                if (!empty($apiKey)) {
                    // Test key before saving
                    try {
                        testAIKeyConnection($keyRow['provider'], $apiKey);
                    } catch (Throwable $e) {
                        sendJsonResponse('error', "Cannot save key: Verification failed: " . $e->getMessage(), [], 400);
                    }

                    $updates[] = "api_key = ?";
                    $params[] = encryptData($apiKey);
                    $updates[] = "status = 'active'";
                    $updates[] = "error_message = NULL";
                } else {
                    sendJsonResponse('error', 'API key value cannot be empty.', [], 400);
                }
            }

            if (count($updates) > 0) {
                $sql = "UPDATE user_ai_keys SET " . implode(", ", $updates) . " WHERE id = ? AND user_id = ?";
                $params[] = $keyId;
                $params[] = $userId;

                $stmtUpdate = $db->prepare($sql);
                $stmtUpdate->execute($params);

                logActivity($userId, "Updated API key details for key ID: {$keyId}");
                sendJsonResponse('success', 'API Key updated and verified successfully.');
            } else {
                sendJsonResponse('error', 'No updates specified.', [], 400);
            }

        } elseif ($action === 'delete') {
            if (empty($input['id'])) {
                sendJsonResponse('error', 'Invalid input parameters.', [], 400);
            }

            $keyId = (int)$input['id'];

            // Get key provider for logs before deleting
            $stmtCheck = $db->prepare("SELECT provider FROM user_ai_keys WHERE id = ? AND user_id = ?");
            $stmtCheck->execute([$keyId, $userId]);
            $keyRow = $stmtCheck->fetch();

            if (!$keyRow) {
                sendJsonResponse('error', 'Key not found or access denied.', [], 404);
            }

            $stmtDel = $db->prepare("DELETE FROM user_ai_keys WHERE id = ? AND user_id = ?");
            $stmtDel->execute([$keyId, $userId]);

            logActivity($userId, "Deleted API key for provider: {$keyRow['provider']}");
            sendJsonResponse('success', 'API Key deleted successfully.');

        } elseif ($action === 'test') {
            if (empty($input['id'])) {
                sendJsonResponse('error', 'Invalid input parameters.', [], 400);
            }

            $keyId = (int)$input['id'];

            // Verify key ownership
            $stmtCheck = $db->prepare("SELECT provider, api_key FROM user_ai_keys WHERE id = ? AND user_id = ?");
            $stmtCheck->execute([$keyId, $userId]);
            $keyRow = $stmtCheck->fetch();
            if (!$keyRow) {
                sendJsonResponse('error', 'Key not found or access denied.', [], 404);
            }

            $decrypted = decryptData($keyRow['api_key']);
            $apiKey = ($decrypted !== false) ? $decrypted : $keyRow['api_key'];

            try {
                // Test the key connection
                testAIKeyConnection($keyRow['provider'], $apiKey);

                // Success: mark as active
                $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = ?");
                $stmtUpdate->execute([$keyId]);

                sendJsonResponse('success', "API Key connection test succeeded! Key is active and working.");
            } catch (Throwable $ex) {
                $errMessage = $ex->getMessage();
                
                // Requirement: "and presaved always test, if not working pause"
                $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'paused', error_message = ? WHERE id = ?");
                $stmtUpdate->execute([$errMessage, $keyId]);

                sendJsonResponse('error', "API Key test failed and has been automatically PAUSED: " . $errMessage, [
                    'status' => 'paused',
                    'error' => $errMessage
                ]);
            }

        } elseif ($action === 'test_all') {
            // Retrieve all keys for the current user
            $stmt = $db->prepare("SELECT id, provider, api_key, status FROM user_ai_keys WHERE user_id = ?");
            $stmt->execute([$userId]);
            $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            $activeCount = 0;
            $pausedCount = 0;

            foreach ($keys as $k) {
                $keyId = $k['id'];
                $decrypted = decryptData($k['api_key']);
                $apiKey = ($decrypted !== false) ? $decrypted : $k['api_key'];

                try {
                    testAIKeyConnection($k['provider'], $apiKey);

                    // Update DB to active
                    $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'active', error_message = NULL WHERE id = ?");
                    $stmtUpdate->execute([$keyId]);
                    $results[$keyId] = ['success' => true, 'provider' => $k['provider'], 'status' => 'active'];
                    $activeCount++;
                } catch (Throwable $ex) {
                    $errMessage = $ex->getMessage();

                    // If not working, automatically pause it!
                    $stmtUpdate = $db->prepare("UPDATE user_ai_keys SET status = 'paused', error_message = ? WHERE id = ?");
                    $stmtUpdate->execute([$errMessage, $keyId]);
                    $results[$keyId] = ['success' => false, 'provider' => $k['provider'], 'status' => 'paused', 'error' => $errMessage];
                    $pausedCount++;
                }
            }

            $summaryMsg = "Sync complete: {$activeCount} active, {$pausedCount} paused due to errors.";
            sendJsonResponse('success', $summaryMsg, [
                'active_count' => $activeCount,
                'paused_count' => $pausedCount,
                'results' => $results
            ]);

        } else {
            sendJsonResponse('error', 'Invalid action specified.', [], 400);
        }

    } else {
        sendJsonResponse('error', 'Method not allowed.', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error: ' . $e->getMessage(), [], 500);
}
