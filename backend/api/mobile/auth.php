<?php
// backend/api/mobile/auth.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'verify';

try {
    if ($method === 'POST' && $action === 'login') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            sendJsonResponse('error', 'Email and password are required.', [], 400);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            sendJsonResponse('error', 'Invalid email or password.', [], 401);
        }

        $token = JWTHelper::generateToken($user);
        logActivity($user['id'], "Mobile App: Logged in successfully.");

        sendJsonResponse('success', 'Login successful.', [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'user'
            ]
        ]);
    } else {
        // Default: Verify token / session restoration
        $user = JWTHelper::requireAuth();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, name, email, role, phone_number, is_verified, created_at FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();

        sendJsonResponse('success', 'Token is valid.', [
            'user' => $profile ?: $user
        ]);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Authentication error: ' . $e->getMessage(), [], 500);
}
