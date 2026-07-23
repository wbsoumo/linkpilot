<?php
// backend/api/auth/check_email.php

require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$email = strtolower(trim($input['email'] ?? ''));

if (empty($email)) {
    sendJsonResponse('error', 'Email is required.', [], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'Invalid email address format.', [], 400);
}

$db = Database::getConnection();

try {
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 1 LIMIT 1");
    $stmt->execute([$email]);
    $exists = $stmt->fetch() ? true : false;

    sendJsonResponse('success', 'Email checked successfully.', [
        'exists' => $exists
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'Database error: ' . $e->getMessage(), [], 500);
}
