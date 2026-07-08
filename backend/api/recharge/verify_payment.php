<?php
// backend/api/recharge/verify_payment.php

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

$orderId = trim($input['razorpay_order_id'] ?? '');
$paymentId = trim($input['razorpay_payment_id'] ?? '');
$signature = trim($input['razorpay_signature'] ?? '');

if (empty($orderId) || empty($paymentId) || empty($signature)) {
    sendJsonResponse('error', 'Razorpay Order ID, Payment ID, and Signature are required.', [], 400);
}

$db = Database::getConnection();

try {
    // 1. Fetch Razorpay Secret Key
    $stmtPay = $db->query("SELECT * FROM payment_provider_settings WHERE provider_name = 'razorpay' LIMIT 1");
    $paySettings = $stmtPay->fetch();

    if (!$paySettings) {
        sendJsonResponse('error', 'Razorpay payment settings are not initialized.', [], 500);
    }

    $secretKey = decryptData($paySettings['secret_key']);
    if (empty($secretKey)) {
        sendJsonResponse('error', 'Razorpay Secret Key is not configured.', [], 500);
    }

    // 2. Validate Signature
    $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $secretKey);
    $signatureValid = hash_equals($expectedSignature, $signature);

    if (!$signatureValid) {
        // Log transaction attempt failure
        $stmtFail = $db->prepare("INSERT INTO payment_transactions (user_id, order_id, payment_id, signature, amount, status, error_code, error_description) VALUES (?, ?, ?, ?, 0, 'failed', 'SIG_MISMATCH', 'Generated signature does not match callback signature')");
        $stmtFail->execute([$userId, $orderId, $paymentId, $signature]);
        
        sendJsonResponse('error', 'Payment signature verification failed. The transaction has been blocked.', [], 400);
    }

    // 3. Check Order Record in Database
    $stmtOrder = $db->prepare("SELECT * FROM recharge_orders WHERE order_id = ? LIMIT 1");
    $stmtOrder->execute([$orderId]);
    $order = $stmtOrder->fetch();

    if (!$order) {
        sendJsonResponse('error', 'Order not found in system logs.', [], 404);
    }

    // Check if transaction is already completed
    if ($order['status'] === 'paid') {
        sendJsonResponse('success', 'Payment already verified and credited.', [
            'order_id' => $orderId,
            'credits_allocated' => (int)$order['credits']
        ]);
    }

    // 4. Begin Transaction to Allocate Credits securely
    $db->beginTransaction();

    // Mark order as paid
    $stmtUpdateOrder = $db->prepare("UPDATE recharge_orders SET status = 'paid' WHERE id = ?");
    $stmtUpdateOrder->execute([$order['id']]);

    // Save payment transaction record
    $stmtPayTx = $db->prepare("INSERT INTO payment_transactions (user_id, order_id, payment_id, signature, amount, status) VALUES (?, ?, ?, ?, ?, 'success')");
    $stmtPayTx->execute([$userId, $orderId, $paymentId, $signature, $order['amount']]);

    // Increment user credits in user_email_credits (both purchased and remaining)
    $stmtUpdateCredits = $db->prepare("UPDATE user_email_credits SET total_credits = total_credits + ?, purchased_credits = purchased_credits + ?, remaining_credits = remaining_credits + ? WHERE user_id = ?");
    $stmtUpdateCredits->execute([$order['credits'], $order['credits'], $order['credits'], $userId]);

    // Record credit transaction
    $stmtCreditTx = $db->prepare("INSERT INTO email_credit_transactions (user_id, type, credits, amount, payment_id, status) VALUES (?, 'recharge', ?, ?, ?, 'success')");
    $stmtCreditTx->execute([$userId, $order['credits'], $order['amount'], $paymentId]);

    // Commit Transaction
    $db->commit();

    // Log Activity
    logActivity($userId, "Recharged account with {$order['credits']} email finder credits (Amount: {$order['amount']}).");

    sendJsonResponse('success', 'Payment verified successfully and credits allocated.', [
        'order_id' => $orderId,
        'credits_allocated' => (int)$order['credits']
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Razorpay Verification error: ' . $e->getMessage());
    sendJsonResponse('error', 'Payment verification failed: ' . $e->getMessage(), [], 500);
}
