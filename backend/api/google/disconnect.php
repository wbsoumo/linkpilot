<?php
// backend/api/google/disconnect.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../google_sheets_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

try {
    $db = Database::getConnection();
    
    // Fetch connection to optionally revoke token
    $stmtSelect = $db->prepare("SELECT access_token FROM google_sheet_connections WHERE user_id = ?");
    $stmtSelect->execute([$userId]);
    $conn = $stmtSelect->fetch();
    
    if ($conn) {
        $token = decryptData($conn['access_token']);
        if ($token) {
            // Revoke Google OAuth Token
            $ch = curl_init('https://oauth2.googleapis.com/revoke?token=' . urlencode($token));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    }
    
    // Delete connection row
    $stmtDelete = $db->prepare("DELETE FROM google_sheet_connections WHERE user_id = ?");
    $stmtDelete->execute([$userId]);
    
    logActivity($userId, "Disconnected Google Sheets Integration");
    
    sendJsonResponse('success', 'Disconnected from Google Sheets successfully.');
} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to disconnect: ' . $e->getMessage(), [], 500);
}
