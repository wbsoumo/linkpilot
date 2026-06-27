<?php
// backend/api/auth/send_otp.php

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
$email = strtolower(trim($input['email'] ?? ''));

// Validate inputs
if (empty($phoneNumber) || empty($email)) {
    sendJsonResponse('error', 'Phone number and email are required.', [], 400);
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'Invalid email address.', [], 400);
}

// Validate phone number format (must be 10-digit number without country code, e.g. starting with 6-9)
if (!preg_match('/^[6-9]\d{9}$/', $phoneNumber)) {
    sendJsonResponse('error', 'Invalid phone number. Please enter a valid 10-digit mobile number.', [], 400);
}

$db = Database::getConnection();

try {
    // 1. Check if email or phone number is already registered by a verified user
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse('error', 'An account with this email address already exists.', [], 409);
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ? AND is_verified = 1");
    $stmt->execute([$phoneNumber]);
    if ($stmt->fetch()) {
        sendJsonResponse('error', 'An account with this phone number already exists.', [], 409);
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // 2. Check phone number rate limit: Max 3 OTPs in the last 1 hour
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM otp_verifications WHERE phone_number = ? AND created_at > NOW() - INTERVAL 1 HOUR");
    $stmt->execute([$phoneNumber]);
    $phoneCount = $stmt->fetch();
    if ($phoneCount && $phoneCount['count'] >= 3) {
        sendJsonResponse('error', 'Too many OTP requests. Maximum 3 OTPs per hour allowed.', [], 429);
    }

    // 3. Check IP rate limit: Max 10 OTPs in the last 1 hour (defense against brute force / credit drain)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM otp_verifications WHERE ip_address = ? AND created_at > NOW() - INTERVAL 1 HOUR");
    $stmt->execute([$ipAddress]);
    $ipCount = $stmt->fetch();
    if ($ipCount && $ipCount['count'] >= 10) {
        sendJsonResponse('error', 'Too many requests from this IP. Please try again later.', [], 429);
    }

    // 4. Generate 6-digit OTP
    $otp = (string)random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    
    // Set expiration time to 10 minutes from now
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    // 5. Store OTP in DB
    $stmt = $db->prepare("INSERT INTO otp_verifications (phone_number, otp_hash, ip_address, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$phoneNumber, $otpHash, $ipAddress, $expiresAt]);

    // 6. Send OTP via Fast2SMS WhatsApp API
    $apiKey = FAST2SMS_API_KEY;
    $messageId = FAST2SMS_MESSAGE_ID;
    $phoneId = FAST2SMS_PHONE_NUMBER_ID;

    // In local development or if FAST2SMS_API_KEY is not set/placeholder, we mock the success response to allow testing.
    if (empty($apiKey) || $apiKey === 'your_fast2sms_api_key_here' || $apiKey === 'YOUR_FAST2SMS_API_KEY') {
        // Return a mock success response with the OTP in development mode for easier debugging/testing
        sendJsonResponse('success', 'OTP sent successfully (Development Mode Mock).', [
            'dev_mode' => true,
            'mock_otp' => $otp
        ], 200);
    }

    // Call Fast2SMS API
    $url = "https://www.fast2sms.com/dev/whatsapp?" . http_build_query([
        'authorization' => $apiKey,
        'message_id' => $messageId,
        'phone_number_id' => $phoneId,
        'numbers' => $phoneNumber,
        'variables_values' => $otp
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("Fast2SMS API connection failed: " . $error);
    }

    $resData = json_decode($response, true);

    // Fast2SMS returns json. Check if status/success is correct
    if ($httpCode !== 200 || !(isset($resData['return']) && $resData['return'] === true)) {
        $msg = $resData['message'][0] ?? ($resData['message'] ?? 'Unknown Fast2SMS Error');
        throw new Exception("Fast2SMS API returned error: " . $msg);
    }

    sendJsonResponse('success', 'OTP sent successfully via WhatsApp.', [], 200);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to send OTP: ' . $e->getMessage(), [], 500);
}
