<?php
// backend/api/recharge/webhook.php

require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 1. Fetch Webhook Secret
$db = Database::getConnection();
$stmtPay = $db->query("SELECT * FROM payment_provider_settings WHERE provider_name = 'razorpay' LIMIT 1");
$paySettings = $stmtPay->fetch();

if (!$paySettings) {
    http_response_code(500);
    echo json_encode(['error' => 'Razorpay configuration settings not found']);
    exit;
}

$webhookSecret = decryptData($paySettings['webhook_secret']);
if (empty($webhookSecret)) {
    http_response_code(500);
    echo json_encode(['error' => 'Razorpay Webhook Secret not configured']);
    exit;
}

// 2. Fetch raw request payload
$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($payload) || empty($signatureHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad request. Payload or signature missing.']);
    exit;
}

// 3. Verify Signature
$expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
if (!hash_equals($expectedSignature, $signatureHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature verification']);
    exit;
}

$data = json_decode($payload, true);
$event = $data['event'] ?? '';

// We only process order.paid events to prevent double-crediting
if ($event === 'order.paid') {
    try {
        $orderEntity = $data['payload']['order']['entity'] ?? null;
        $paymentEntity = $data['payload']['payment']['entity'] ?? null;

        if (!$orderEntity) {
            http_response_code(400);
            echo json_encode(['error' => 'Order entity missing from event payload']);
            exit;
        }

        $orderId = $orderEntity['id'] ?? '';
        $paymentId = $paymentEntity ? ($paymentEntity['id'] ?? '') : '';

        if (empty($orderId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Order ID missing']);
            exit;
        }

        // Check if order exists in our DB
        $stmtOrder = $db->prepare("SELECT * FROM recharge_orders WHERE order_id = ? LIMIT 1");
        $stmtOrder->execute([$orderId]);
        $order = $stmtOrder->fetch();

        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Recharge order not found in LinkPilot DB']);
            exit;
        }

        // If already paid, return 200 OK
        if ($order['status'] === 'paid') {
            http_response_code(200);
            echo json_encode(['message' => 'Payment already processed and credited.']);
            exit;
        }

        $userId = $order['user_id'];

        // Begin transaction to allocate credits securely
        $db->beginTransaction();

        // Mark order as paid
        $stmtUpdateOrder = $db->prepare("UPDATE recharge_orders SET status = 'paid' WHERE id = ?");
        $stmtUpdateOrder->execute([$order['id']]);

        // Save payment transaction record
        $stmtPayTx = $db->prepare("INSERT INTO payment_transactions (user_id, order_id, payment_id, signature, amount, status) VALUES (?, ?, ?, ?, ?, 'success')");
        $stmtPayTx->execute([$userId, $orderId, $paymentId, $signatureHeader, $order['amount']]);

        // Increment user credits in user_email_credits (both purchased and remaining)
        $stmtUpdateCredits = $db->prepare("UPDATE user_email_credits SET total_credits = total_credits + ?, purchased_credits = purchased_credits + ?, remaining_credits = remaining_credits + ? WHERE user_id = ?");
        $stmtUpdateCredits->execute([$order['credits'], $order['credits'], $order['credits'], $userId]);

        // Record credit transaction
        $stmtCreditTx = $db->prepare("INSERT INTO email_credit_transactions (user_id, type, credits, amount, payment_id, status) VALUES (?, 'recharge', ?, ?, ?, 'success')");
        $stmtCreditTx->execute([$userId, $order['credits'], $order['amount'], $paymentId]);

        // Commit transaction
        $db->commit();

        // Log Activity
        logActivity($userId, "Recharged account asynchronously via webhook: {$order['credits']} credits.");

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Credits allocated successfully via Webhook']);
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Razorpay Webhook handler exception: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error processing webhook callback: ' . $e->getMessage()]);
        exit;
    }
}

// Return 200 OK for other unhandled events so Razorpay doesn't keep retrying
http_response_code(200);
echo json_encode(['message' => 'Event received, no action taken.']);
exit;
