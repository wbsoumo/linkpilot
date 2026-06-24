<?php
// backend/api/api/auth/login.php -> wait, path should be backend/api/auth/login.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';
$rememberMe = (bool)($input['remember_me'] ?? false);

if (empty($email) || empty($password)) {
    sendJsonResponse('error', 'Email and password are required.', [], 400);
}

$db = Database::getConnection();

try {
    // Retrieve User
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        sendJsonResponse('error', 'Invalid email or password.', [], 401);
    }
    
    // Generate JWT Token
    $token = JWTHelper::generateToken($user);
    
    // Log Activity
    logActivity($user['id'], "User logged in successfully.");
    
    sendJsonResponse('success', 'Login successful.', [
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ], 200);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Server error during login: ' . $e->getMessage(), [], 500);
}
