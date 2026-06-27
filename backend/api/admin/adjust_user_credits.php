<?php
// backend/api/admin/adjust_user_credits.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Require Admin Privilege
$adminUser = JWTHelper::requireAdmin();

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input.', [], 400);
}

$targetUserId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$adjType = trim($input['adjustment_type'] ?? ''); // 'recharge', 'refund', 'deduction'
$credits = isset($input['credits']) ? (int)$input['credits'] : 0;
$reason = trim($input['reason'] ?? 'Admin Adjustment');
$amount = isset($input['amount']) ? (float)$input['amount'] : 0.00;

if ($targetUserId <= 0 || $credits <= 0 || !in_array($adjType, ['recharge', 'refund', 'deduction'])) {
    sendJsonResponse('error', 'Valid User ID, Credit value, and adjustment type are required.', [], 400);
}

$db = Database::getConnection();

try {
    // 1. Verify target user exists
    $stmtCheck = $db->prepare("SELECT id, name FROM users WHERE id = ? LIMIT 1");
    $stmtCheck->execute([$targetUserId]);
    $targetUser = $stmtCheck->fetch();

    if (!$targetUser) {
        sendJsonResponse('error', 'Target user does not exist.', [], 404);
    }

    // Verify target user has user_email_credits row, if not create one
    $stmtCreditsCheck = $db->prepare("SELECT COUNT(*) FROM user_email_credits WHERE user_id = ?");
    $stmtCreditsCheck->execute([$targetUserId]);
    if ((int)$stmtCreditsCheck->fetchColumn() === 0) {
        $stmtInsert = $db->prepare("INSERT INTO user_email_credits (user_id, total_credits, used_credits, remaining_credits) VALUES (?, 0, 0, 0)");
        $stmtInsert->execute([$targetUserId]);
    }

    // 2. Begin adjustment transaction
    $db->beginTransaction();

    $creditChange = $credits;
    if ($adjType === 'deduction') {
        $creditChange = -$credits;
    }

    // Update wallet
    $updateSql = "";
    if ($adjType === 'recharge' || $adjType === 'refund') {
        // Increment both total and remaining
        $updateSql = "UPDATE user_email_credits SET total_credits = total_credits + :credits, remaining_credits = remaining_credits + :credits WHERE user_id = :user_id";
    } else {
        // Deduction: Decrement remaining, increment used
        $updateSql = "UPDATE user_email_credits SET remaining_credits = remaining_credits - :credits, used_credits = used_credits + :credits WHERE user_id = :user_id AND remaining_credits >= :credits";
    }

    $stmtUpdate = $db->prepare($updateSql);
    $stmtUpdate->bindValue(':credits', $credits, PDO::PARAM_INT);
    $stmtUpdate->bindValue(':user_id', $targetUserId, PDO::PARAM_INT);
    $stmtUpdate->execute();

    if ($stmtUpdate->rowCount() === 0 && $adjType === 'deduction') {
        $db->rollBack();
        sendJsonResponse('error', 'Failed to deduct credits. User does not have enough remaining credits.', [], 400);
    }

    // Log transaction
    $stmtTx = $db->prepare("INSERT INTO email_credit_transactions (user_id, type, credits, amount, payment_id, status) VALUES (?, 'adjustment', ?, ?, ?, 'success')");
    $stmtTx->execute([$targetUserId, $creditChange, $amount, $reason]);

    $db->commit();

    logActivity($adminUser['id'], "Manually adjusted credit wallet balance of user {$targetUser['name']} (ID: {$targetUserId}) by {$creditChange} credits. Reason: {$reason}");

    sendJsonResponse('success', 'User credit wallet balance adjusted successfully.');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Adjustment failed: ' . $e->getMessage(), [], 500);
}
