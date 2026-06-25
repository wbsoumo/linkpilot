<?php
// backend/api/smtp/test.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../smtp_helper.php';

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

$db = Database::getConnection();

// Check if we are testing inputs provided in real-time or saved credentials
$useSaved = (bool)($input['use_saved'] ?? false);

try {
    if ($useSaved) {
        // Fetch saved credentials
        $id = isset($input['id']) ? (int)$input['id'] : null;
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM smtp_accounts WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("SELECT * FROM smtp_accounts WHERE user_id = ? ORDER BY is_default DESC, id ASC LIMIT 1");
            $stmt->execute([$userId]);
        }
        $smtp = $stmt->fetch();
        
        if (!$smtp) {
            sendJsonResponse('error', 'No SMTP account connected yet.', [], 404);
        }
        
        $host = $smtp['host'];
        $port = $smtp['port'];
        $username = $smtp['username'];
        $password = decryptData($smtp['password']);
        $senderName = $smtp['sender_name'];
        $senderEmail = $smtp['sender_email'];
    } else {
        // Validate inputs
        $host = trim($input['host'] ?? '');
        $port = (int)($input['port'] ?? 0);
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $senderName = trim($input['sender_name'] ?? '');
        $senderEmail = trim($input['sender_email'] ?? '');
        
        if (empty($host) || empty($port) || empty($username) || empty($password) || empty($senderName) || empty($senderEmail)) {
            sendJsonResponse('error', 'All SMTP parameters are required to test connection.', [], 400);
        }
    }
    
    // Perform connection test
    $result = SMTPHelper::testConnection($host, $port, $username, $password, $senderName, $senderEmail);
    
    logActivity($userId, "Executed SMTP Connection test. Status: " . ($result['status'] ? 'Success' : 'Failed'));
    
    if ($result['status']) {
        sendJsonResponse('success', $result['message']);
    } else {
        sendJsonResponse('error', $result['message'], [], 400);
    }
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error testing SMTP: ' . $e->getMessage(), [], 500);
}
