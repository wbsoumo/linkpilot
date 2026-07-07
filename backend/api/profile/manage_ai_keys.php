<?php
// backend/api/profile/manage_ai_keys.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        // Retrieve keys
        $stmt = $db->prepare("SELECT id, provider, api_key, status, error_message, created_at, updated_at FROM user_ai_keys WHERE user_id = ? ORDER BY id ASC");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $keys = [];
        foreach ($rows as $row) {
            $decrypted = decryptData($row['api_key']);
            $rawKey = ($decrypted !== false) ? $decrypted : $row['api_key'];
            
            // Mask the key (showing only last 4 characters)
            $len = strlen($rawKey);
            $last4 = ($len > 4) ? substr($rawKey, -4) : $rawKey;
            $masked = str_repeat('•', min(8, max(4, $len - 4))) . $last4;

            $keys[] = [
                'id' => $row['id'],
                'provider' => $row['provider'],
                'masked_key' => $masked,
                'status' => $row['status'],
                'error_message' => $row['error_message'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }

        sendJsonResponse('success', 'AI Keys loaded.', ['keys' => $keys]);

    } elseif ($method === 'POST') {
        // Add new key
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            sendJsonResponse('error', 'Invalid JSON input', [], 400);
        }

        $provider = isset($input['provider']) ? trim($input['provider']) : '';
        $apiKey = isset($input['api_key']) ? trim($input['api_key']) : '';

        if (empty($provider) || empty($apiKey)) {
            sendJsonResponse('error', 'Provider and API Key are required fields.', [], 400);
        }

        if (!in_array($provider, ['openrouter', 'github_models', 'google_ai_studio'])) {
            sendJsonResponse('error', 'Invalid AI Provider specified.', [], 400);
        }

        // Encrypt and insert key
        $encrypted = encryptData($apiKey);
        $stmt = $db->prepare("INSERT INTO user_ai_keys (user_id, provider, api_key, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$userId, $provider, $encrypted]);

        logActivity($userId, "Added new API key for provider: {$provider}");
        sendJsonResponse('success', 'API Key added successfully.');

    } elseif ($method === 'DELETE') {
        // Delete key
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['id'])) {
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

    } else {
        sendJsonResponse('error', 'Method not allowed.', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error: ' . $e->getMessage(), [], 500);
}
