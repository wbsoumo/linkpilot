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

$openrouterKey = isset($input['openrouter_key']) ? trim($input['openrouter_key']) : null;
$githubKey = isset($input['github_key']) ? trim($input['github_key']) : null;
$googleKey = isset($input['google_key']) ? trim($input['google_key']) : null;
$activeProvider = isset($input['active_ai_provider']) ? trim($input['active_ai_provider']) : null;
$activeModel = isset($input['active_ai_model']) ? trim($input['active_ai_model']) : null;

$db = Database::getConnection();

try {
    // Start building update query
    $updates = [];
    $params = [];
    
    // Process OpenRouter Key
    if ($openrouterKey !== null) {
        if ($openrouterKey === '') {
            $updates[] = "openrouter_key = NULL";
        } elseif ($openrouterKey !== '••••••••') {
            $updates[] = "openrouter_key = ?";
            $params[] = encryptData($openrouterKey);
        }
    }
    
    // Process GitHub Key
    if ($githubKey !== null) {
        if ($githubKey === '') {
            $updates[] = "github_key = NULL";
        } elseif ($githubKey !== '••••••••') {
            $updates[] = "github_key = ?";
            $params[] = encryptData($githubKey);
        }
    }
    
    // Process Google Key
    if ($googleKey !== null) {
        if ($googleKey === '') {
            $updates[] = "google_key = NULL";
        } elseif ($googleKey !== '••••••••') {
            $updates[] = "google_key = ?";
            $params[] = encryptData($googleKey);
        }
    }
    
    // Process Active Provider
    if ($activeProvider !== null) {
        if (in_array($activeProvider, ['openrouter', 'github_models', 'google_ai_studio'])) {
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
    
    logActivity($userId, "Updated AI Settings & Configurations.");
    
    sendJsonResponse('success', 'AI settings saved successfully.');
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error saving AI settings: ' . $e->getMessage(), [], 500);
}
