<?php
// backend/api/admin/financial_ledger.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Requires Admin privileges
$admin = JWTHelper::requireAdmin();

$db = Database::getConnection();

// Handle POST request for issuing Manual Top-up Invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $targetUserId = (int)($input['user_id'] ?? 0);
    $credits = (int)($input['credits'] ?? 0);
    $amount = (float)($input['amount'] ?? 0.0);
    $notes = trim($input['notes'] ?? 'Manual Admin Top-up Invoice');

    if ($targetUserId <= 0 || $credits <= 0) {
        sendJsonResponse('error', 'Please provide a valid user ID and credit amount.', [], 400);
    }

    // Verify user exists
    $stmtU = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmtU->execute([$targetUserId]);
    $user = $stmtU->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        sendJsonResponse('error', 'User not found.', [], 404);
    }

    // Credit user's wallet
    $stmtCheck = $db->prepare("SELECT id, total_credits, remaining_credits FROM user_email_credits WHERE user_id = ?");
    $stmtCheck->execute([$targetUserId]);
    $wallet = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($wallet) {
        $stmtUp = $db->prepare("UPDATE user_email_credits SET remaining_credits = remaining_credits + ?, total_credits = total_credits + ? WHERE user_id = ?");
        $stmtUp->execute([$credits, $credits, $targetUserId]);
    } else {
        $stmtIns = $db->prepare("INSERT INTO user_email_credits (user_id, total_credits, remaining_credits) VALUES (?, ?, ?)");
        $stmtIns->execute([$targetUserId, $credits, $credits]);
    }

    // Generate Invoice ID
    $invoiceNum = 'INV-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $paymentId = 'MANUAL_' . strtoupper(substr(md5(microtime()), 0, 10));

    // Log transaction
    $stmtTxIns = $db->prepare("
        INSERT INTO email_credit_transactions (user_id, type, credits, amount, payment_id, status, created_at)
        VALUES (?, 'recharge', ?, ?, ?, 'completed', NOW())
    ");
    $stmtTxIns->execute([$targetUserId, $credits, $amount, $invoiceNum]);

    sendJsonResponse('success', "Invoice {$invoiceNum} generated and {$credits} credits credited to {$user['name']}!", [
        'invoice_number' => $invoiceNum,
        'user' => $user,
        'credits_added' => $credits,
        'amount' => $amount,
        'notes' => $notes,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

try {
    $limit = 20;
    $txPage = isset($_GET['tx_page']) ? max(1, (int)$_GET['tx_page']) : 1;
    $ordersPage = isset($_GET['orders_page']) ? max(1, (int)$_GET['orders_page']) : 1;
    
    $txOffset = ($txPage - 1) * $limit;
    $ordersOffset = ($ordersPage - 1) * $limit;

    // 1. Calculate Revenue Analytics Metrics
    $totRevStmt = $db->query("SELECT COALESCE(SUM(amount), 0) AS total_rev, COUNT(*) AS count_paid FROM recharge_orders WHERE status IN ('paid', 'completed', 'success')");
    $totRevData = $totRevStmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = (float)$totRevData['total_rev'];
    $paidCount = (int)$totRevData['count_paid'];
    $avgOrderValue = $paidCount > 0 ? round($totalRevenue / $paidCount, 2) : 0;

    $refundStmt = $db->query("SELECT COALESCE(SUM(amount), 0) AS refund_amt, COUNT(*) as count_refund FROM recharge_orders WHERE status = 'refunded'");
    $refundData = $refundStmt->fetch(PDO::FETCH_ASSOC);
    $refundedRevenue = (float)$refundData['refund_amt'];
    $refundedCount = (int)$refundData['count_refund'];

    $failedStmt = $db->query("SELECT COUNT(*) as count_failed, COALESCE(SUM(amount), 0) as failed_amt FROM recharge_orders WHERE status IN ('failed', 'cancelled')");
    $failedData = $failedStmt->fetch(PDO::FETCH_ASSOC);
    $failedCount = (int)$failedData['count_failed'];
    $failedAmount = (float)$failedData['failed_amt'];

    // 2. Transaction logs count & paginated query
    $totalTxCount = (int)$db->query("SELECT COUNT(*) FROM email_credit_transactions")->fetchColumn();
    $totalTxPages = max(1, ceil($totalTxCount / $limit));

    $stmtTx = $db->prepare("
        SELECT tx.id, tx.user_id, tx.type, tx.credits, tx.amount, tx.payment_id, tx.status, tx.created_at,
               u.name AS user_name, u.email AS user_email
        FROM email_credit_transactions tx
        LEFT JOIN users u ON tx.user_id = u.id
        ORDER BY tx.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmtTx->bindValue(1, $limit, PDO::PARAM_INT);
    $stmtTx->bindValue(2, $txOffset, PDO::PARAM_INT);
    $stmtTx->execute();
    $transactions = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

    // 3. Razorpay orders count & paginated query
    $totalOrdersCount = (int)$db->query("SELECT COUNT(*) FROM recharge_orders")->fetchColumn();
    $totalOrdersPages = max(1, ceil($totalOrdersCount / $limit));

    $stmtOrders = $db->prepare("
        SELECT o.id, o.user_id, o.order_id, o.amount, o.currency, o.credits, o.status, o.created_at,
               u.name AS user_name, u.email AS user_email
        FROM recharge_orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmtOrders->bindValue(1, $limit, PDO::PARAM_INT);
    $stmtOrders->bindValue(2, $ordersOffset, PDO::PARAM_INT);
    $stmtOrders->execute();
    $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse('success', 'Financial records loaded.', [
        'analytics' => [
            'total_revenue' => $totalRevenue,
            'avg_order_value' => $avgOrderValue,
            'refunded_amount' => $refundedRevenue,
            'refunded_count' => $refundedCount,
            'failed_count' => $failedCount,
            'failed_amount' => $failedAmount,
            'total_orders' => $paidCount
        ],
        'transactions' => $transactions,
        'tx_pagination' => [
            'page' => $txPage,
            'limit' => $limit,
            'total_count' => $totalTxCount,
            'total_pages' => $totalTxPages
        ],
        'orders' => $orders,
        'orders_pagination' => [
            'page' => $ordersPage,
            'limit' => $limit,
            'total_count' => $totalOrdersCount,
            'total_pages' => $totalOrdersPages
        ]
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to load ledger: ' . $e->getMessage(), [], 500);
}
