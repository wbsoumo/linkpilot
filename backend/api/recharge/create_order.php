<?php
// backend/api/recharge/create_order.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$amount = isset($input['amount']) ? (float)$input['amount'] : 0.00;

if ($amount < 49.00) {
    sendJsonResponse('error', 'Minimum recharge amount is ₹49.', [], 400);
}

$db = Database::getConnection();

try {
    // 1. Fetch Razorpay configuration settings
    $stmtPay = $db->query("SELECT * FROM payment_provider_settings WHERE provider_name = 'razorpay' LIMIT 1");
    $paySettings = $stmtPay->fetch();

    if (!$paySettings || !(int)$paySettings['is_enabled']) {
        sendJsonResponse('error', 'Razorpay payments are currently disabled. Please contact support.', [], 503);
    }

    $keyId = decryptData($paySettings['key_id']);
    $secretKey = decryptData($paySettings['secret_key']);
    $currency = $paySettings['currency'] ?: 'INR';

    if (empty($keyId) || empty($secretKey)) {
        sendJsonResponse('error', 'Razorpay API credentials are not configured in admin settings.', [], 500);
    }

    // 2. Calculate credits to allocate: credits = floor((amount / 49) * 101)
    $credits = floor(($amount / 49) * 101);

    // 3. Request Razorpay Order Creation via API
    $receipt = 'rcpt_u' . $userId . '_' . time();
    $payload = [
        'amount' => round($amount * 100), // Razorpay accepts amounts in the smallest currency unit (paisa for INR)
        'currency' => $currency,
        'receipt' => $receipt
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $secretKey);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot/1.0');
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $errorMsg = curl_error($ch);
        curl_close($ch);
        throw new Exception('Razorpay order creation connection failed: ' . $errorMsg);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Razorpay API order request returned status: ' . $httpCode . '. Body: ' . $response);
    }

    $rzOrder = json_decode($response, true);
    if (!isset($rzOrder['id'])) {
        throw new Exception('Invalid order response structure from Razorpay API.');
    }

    $orderId = $rzOrder['id'];

    // 4. Save order record in local database
    $stmtInsert = $db->prepare("INSERT INTO recharge_orders (user_id, order_id, amount, currency, credits, status) VALUES (?, ?, ?, ?, ?, 'created')");
    $stmtInsert->execute([$userId, $orderId, $amount, $currency, $credits]);

    sendJsonResponse('success', 'Order created successfully.', [
        'order_id' => $orderId,
        'amount' => $amount,
        'currency' => $currency,
        'key_id' => $keyId,
        'user' => [
            'name' => $user['name'],
            'email' => $user['email']
        ]
    ]);

} catch (Exception $e) {
    error_log('Razorpay Order creation error: ' . $e->getMessage());
    sendJsonResponse('error', 'Recharge order generation failed: ' . $e->getMessage(), [], 500);
}
