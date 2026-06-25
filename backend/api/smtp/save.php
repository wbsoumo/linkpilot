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

$id = isset($input['id']) ? (int)$input['id'] : null;
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
    if ($id) {
        // Edit Mode: Verify ownership
        $stmtCheck = $db->prepare("SELECT id, password FROM smtp_accounts WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$id, $userId]);
        $existing = $stmtCheck->fetch();
        
        if (!$existing) {
            sendJsonResponse('error', 'SMTP account not found or unauthorized.', [], 404);
        }
        
        if ($password !== '••••••••') {
            $encryptedPassword = encryptData($password);
            $stmtUpdate = $db->prepare("
                UPDATE smtp_accounts 
                SET host = :host, port = :port, username = :username, password = :password, sender_name = :sender_name, sender_email = :sender_email, smtp_type = :smtp_type
                WHERE id = :id AND user_id = :user_id
            ");
            $stmtUpdate->execute([
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $encryptedPassword,
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'smtp_type' => $smtpType,
                'id' => $id,
                'user_id' => $userId
            ]);
        } else {
            // Keep existing password
            $stmtUpdate = $db->prepare("
                UPDATE smtp_accounts 
                SET host = :host, port = :port, username = :username, sender_name = :sender_name, sender_email = :sender_email, smtp_type = :smtp_type
                WHERE id = :id AND user_id = :user_id
            ");
            $stmtUpdate->execute([
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'smtp_type' => $smtpType,
                'id' => $id,
                'user_id' => $userId
            ]);
        }
        logActivity($userId, "Updated SMTP configuration ID: " . $id);
        sendJsonResponse('success', 'SMTP account updated successfully.');
    } else {
        // Create Mode: Check if there are other SMTP accounts
        $stmtCount = $db->prepare("SELECT COUNT(*) as cnt FROM smtp_accounts WHERE user_id = ?");
        $stmtCount->execute([$userId]);
        $hasAccounts = ($stmtCount->fetch()['cnt'] > 0);
        $isDefault = $hasAccounts ? 0 : 1; // First account is default
        
        $encryptedPassword = encryptData($password);
        $stmtInsert = $db->prepare("
            INSERT INTO smtp_accounts (user_id, host, port, username, password, sender_name, sender_email, smtp_type, is_default)
            VALUES (:user_id, :host, :port, :username, :password, :sender_name, :sender_email, :smtp_type, :is_default)
        ");
        $stmtInsert->execute([
            'user_id' => $userId,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $encryptedPassword,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'smtp_type' => $smtpType,
            'is_default' => $isDefault
        ]);
        $newId = $db->lastInsertId();
        logActivity($userId, "Created new SMTP configuration. ID: " . $newId);
        sendJsonResponse('success', 'SMTP account saved successfully.', ['id' => $newId]);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error saving SMTP settings: ' . $e->getMessage(), [], 500);
}
