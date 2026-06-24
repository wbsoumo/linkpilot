<?php
// backend/api/profile/save_openrouter.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

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

$openrouterKey = trim($input['openrouter_key'] ?? '');

$db = Database::getConnection();

try {
    if ($openrouterKey !== '') {
        if ($openrouterKey !== '••••••••') {
            $encryptedKey = encryptData($openrouterKey);
            $stmt = $db->prepare("UPDATE users SET openrouter_key = ? WHERE id = ?");
            $stmt->execute([$encryptedKey, $userId]);
        }
    } else {
        $stmt = $db->prepare("UPDATE users SET openrouter_key = NULL WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    logActivity($userId, "Updated OpenRouter API Key.");
    
    sendJsonResponse('success', 'OpenRouter API Key saved successfully.');
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error saving OpenRouter API Key: ' . $e->getMessage(), [], 500);
}
