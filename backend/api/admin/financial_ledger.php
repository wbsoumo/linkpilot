<?php
// backend/api/admin/financial_ledger.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

$db = Database::getConnection();

try {
    // Fetch recent transaction logs across the entire system
    $stmtTx = $db->query("
        SELECT tx.id, tx.user_id, tx.type, tx.credits, tx.amount, tx.payment_id, tx.status, tx.created_at,
               u.name AS user_name, u.email AS user_email
        FROM email_credit_transactions tx
        LEFT JOIN users u ON tx.user_id = u.id
        ORDER BY tx.id DESC
        LIMIT 200
    ");
    $transactions = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Razorpay orders feed
    $stmtOrders = $db->query("
        SELECT o.id, o.user_id, o.order_id, o.amount, o.currency, o.credits, o.status, o.created_at,
               u.name AS user_name, u.email AS user_email
        FROM recharge_orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.id DESC
        LIMIT 200
    ");
    $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse('success', 'Financial records loaded.', [
        'transactions' => $transactions,
        'orders' => $orders
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to load ledger: ' . $e->getMessage(), [], 500);
}
