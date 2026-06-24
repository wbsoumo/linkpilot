<?php
// backend/api/smtp/save.php

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

$host = trim($input['host'] ?? '');
$port = (int)($input['port'] ?? 0);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$senderName = trim($input['sender_name'] ?? '');
$senderEmail = trim($input['sender_email'] ?? '');
$smtpType = trim($input['smtp_type'] ?? 'custom');

if (empty($host) || empty($port) || empty($username) || empty($password) || empty($senderName) || empty($senderEmail)) {
    sendJsonResponse('error', 'All fields (host, port, username, password, sender_name, sender_email) are required.', [], 400);
}

if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'Invalid sender email address.', [], 400);
}

$db = Database::getConnection();

try {
    if ($password !== '••••••••') {
        $encryptedPassword = encryptData($password);
        $stmt = $db->prepare("
            INSERT INTO smtp_accounts (user_id, host, port, username, password, sender_name, sender_email, smtp_type) 
            VALUES (:user_id, :host, :port, :username, :password, :sender_name, :sender_email, :smtp_type)
            ON DUPLICATE KEY UPDATE 
                host = VALUES(host),
                port = VALUES(port),
                username = VALUES(username),
                password = VALUES(password),
                sender_name = VALUES(sender_name),
                sender_email = VALUES(sender_email),
                smtp_type = VALUES(smtp_type)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $encryptedPassword,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'smtp_type' => $smtpType
        ]);
    } else {
        $stmt = $db->prepare("
            UPDATE smtp_accounts 
            SET host = ?, port = ?, username = ?, sender_name = ?, sender_email = ?, smtp_type = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$host, $port, $username, $senderName, $senderEmail, $smtpType, $userId]);
    }

    logActivity($userId, "Updated SMTP Configuration settings.");
    
    sendJsonResponse('success', 'SMTP Configuration saved successfully.');
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error saving SMTP settings: ' . $e->getMessage(), [], 500);
}
