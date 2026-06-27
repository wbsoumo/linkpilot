<?php
// backend/api/profile/get_credits.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

$db = Database::getConnection();

try {
    // 1. Fetch Wallet Balance
    $stmtWallet = $db->prepare("SELECT total_credits, used_credits, remaining_credits FROM user_email_credits WHERE user_id = ?");
    $stmtWallet->execute([$userId]);
    $wallet = $stmtWallet->fetch();
    
    if (!$wallet) {
        // Initialize if not present
        $stmtInsert = $db->prepare("INSERT INTO user_email_credits (user_id, total_credits, used_credits, remaining_credits) VALUES (?, 0, 0, 0)");
        $stmtInsert->execute([$userId]);
        $wallet = ['total_credits' => 0, 'used_credits' => 0, 'remaining_credits' => 0];
    }

    // 2. Today's Usage Count
    $stmtToday = $db->prepare("SELECT IFNULL(SUM(credits), 0) as today_used FROM email_credit_transactions WHERE user_id = ? AND type = 'usage' AND DATE(created_at) = CURDATE() AND status = 'success'");
    $stmtToday->execute([$userId]);
    $todayUsage = (int)$stmtToday->fetchColumn();

    // 3. Last Recharge details
    $stmtLastRecharge = $db->prepare("SELECT amount, created_at FROM email_credit_transactions WHERE user_id = ? AND type = 'recharge' AND status = 'success' ORDER BY id DESC LIMIT 1");
    $stmtLastRecharge->execute([$userId]);
    $lastRecharge = $stmtLastRecharge->fetch();

    // 4. Provider Usage Stats
    $stmtProviderStats = $db->prepare("SELECT IFNULL(provider_used, 'None') as provider, COUNT(*) as count FROM email_credit_transactions WHERE user_id = ? AND type = 'usage' AND status = 'success' GROUP BY provider_used");
    $stmtProviderStats->execute([$userId]);
    $providerStats = $stmtProviderStats->fetchAll();

    // 5. Recent Search History
    $stmtRecentSearches = $db->prepare("SELECT name, company, email, linkedin_url, provider, credits_used, created_at FROM email_search_history WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $stmtRecentSearches->execute([$userId]);
    $recentSearches = $stmtRecentSearches->fetchAll();

    // 6. Recent Transactions
    $stmtRecentTx = $db->prepare("SELECT id, type, credits, amount, payment_id, provider_used, status, created_at FROM email_credit_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $stmtRecentTx->execute([$userId]);
    $recentTx = $stmtRecentTx->fetchAll();

    sendJsonResponse('success', 'Credit wallet loaded.', [
        'wallet' => [
            'total' => (int)$wallet['total_credits'],
            'used' => (int)$wallet['used_credits'],
            'remaining' => (int)$wallet['remaining_credits']
        ],
        'today_usage' => $todayUsage,
        'last_recharge' => $lastRecharge ? [
            'amount' => (float)$lastRecharge['amount'],
            'date' => $lastRecharge['created_at']
        ] : null,
        'provider_statistics' => $providerStats,
        'recent_searches' => $recentSearches,
        'recent_transactions' => $recentTx
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to fetch credit details: ' . $e->getMessage(), [], 500);
}
