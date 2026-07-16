<?php
// backend/api/admin/impersonate.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['user_id'])) {
    sendJsonResponse('error', 'User ID is required', [], 400);
}

$targetUserId = (int)$input['user_id'];

// Prevent impersonating yourself
if ($targetUserId === (int)$admin['id']) {
    sendJsonResponse('error', 'You cannot impersonate your own administrator account.', [], 400);
}

$db = Database::getConnection();

try {
    // Verify target user exists
    $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendJsonResponse('error', 'Target user not found.', [], 404);
    }

    // Generate JWT token for target user
    $impersonatedToken = JWTHelper::generateToken($user);

    logActivity($admin['id'], "Admin impersonated user: {$user['name']} (ID: {$targetUserId})");

    sendJsonResponse('success', 'Impersonation token generated successfully.', [
        'token' => $impersonatedToken,
        'user' => $user
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Impersonation failed: ' . $e->getMessage(), [], 500);
}
