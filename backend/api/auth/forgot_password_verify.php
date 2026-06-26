<?php
// backend/api/auth/forgot_password_verify.php

require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$phoneNumber = trim($input['phone_number'] ?? '');
$otp = trim($input['otp'] ?? '');
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

// Validation
if (empty($phoneNumber) || empty($otp) || empty($password) || empty($confirmPassword)) {
    sendJsonResponse('error', 'Phone number, OTP, and new password details are required.', [], 400);
}

if (!preg_match('/^[6-9]\d{9}$/', $phoneNumber)) {
    sendJsonResponse('error', 'Invalid phone number. Please enter a valid 10-digit mobile number.', [], 400);
}

if ($password !== $confirmPassword) {
    sendJsonResponse('error', 'Passwords do not match.', [], 400);
}

if (strlen($password) < 6) {
    sendJsonResponse('error', 'Password must be at least 6 characters long.', [], 400);
}

$db = Database::getConnection();

try {
    // 1. Verify if user exists with this phone number
    $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
    $stmt->execute([$phoneNumber]);
    $user = $stmt->fetch();
    if (!$user) {
        sendJsonResponse('error', 'This phone number is not registered on LinkPilot AI.', [], 404);
    }
    
    $userId = $user['id'];

    // Begin transaction
    $db->beginTransaction();
    
    // 2. Get latest OTP verification record
    $stmt = $db->prepare("SELECT * FROM otp_verifications WHERE phone_number = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$phoneNumber]);
    $otpRecord = $stmt->fetch();

    if (!$otpRecord) {
        $db->rollBack();
        sendJsonResponse('error', 'Invalid or expired OTP. Please request a new OTP.', [], 400);
    }

    if ($otpRecord['attempts'] >= 3) {
        $db->rollBack();
        sendJsonResponse('error', 'Too many verification attempts. Please request a new OTP.', [], 400);
    }

    // Increment attempts
    $stmtUpdateAttempts = $db->prepare("UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = ?");
    $stmtUpdateAttempts->execute([$otpRecord['id']]);

    // Verify OTP code
    if (!password_verify($otp, $otpRecord['otp_hash'])) {
        $remaining = 2 - $otpRecord['attempts'];
        $msg = "Incorrect OTP. ";
        if ($remaining > 0) {
            $msg .= "Remaining attempts: " . $remaining;
        } else {
            $msg .= "No attempts remaining. Please request a new OTP.";
        }
        $db->commit(); // Commit the attempts increment
        sendJsonResponse('error', $msg, [], 400);
    }

    // OTP is correct! Delete OTP records for this phone number
    $stmtDelete = $db->prepare("DELETE FROM otp_verifications WHERE phone_number = ?");
    $stmtDelete->execute([$phoneNumber]);
    
    // Hash new password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update user's password
    $stmtUser = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmtUser->execute([$passwordHash, $userId]);
    
    // Commit transaction
    $db->commit();
    
    // Log Activity
    logActivity($userId, "User reset password successfully via WhatsApp OTP.");
    
    sendJsonResponse('success', 'Password reset successfully. You can now log in with your new password.', [], 200);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Server error during password reset: ' . $e->getMessage(), [], 500);
}
