<?php
// backend/api/whatsapp/fetch_business.php
function clean_non_json_output_filter($buffer) {
    $start = strpos($buffer, '{');
    $end = strrpos($buffer, '}');
    if ($start !== false && $end !== false && $end > $start) {
        return substr($buffer, $start, $end - $start + 1);
    }
    return $buffer;
}
ob_start("clean_non_json_output_filter");
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

try {
    $stmtAcc = $db->prepare("SELECT * FROM whatsapp_accounts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtAcc->execute([$userId]);
    $account = $stmtAcc->fetch(PDO::FETCH_ASSOC);
    
    if (!$account || $account['status'] !== 'connected') {
        sendJsonResponse('success', 'No WhatsApp account is connected.', [
            'connected' => false,
            'business' => null
        ]);
    }
    
    // Security check: NEVER expose tokens to the frontend
    unset($account['access_token']);
    
    sendJsonResponse('success', 'WhatsApp business account details retrieved.', [
        'connected' => true,
        'business' => [
            'business_name' => $account['business_name'] ?? '',
            'business_id' => $account['business_id'] ?? '',
            'waba_id' => $account['waba_id'] ?? '',
            'waba_name' => $account['waba_name'] ?? '',
            'phone_number_id' => $account['phone_number_id'] ?? '',
            'display_phone_number' => $account['display_phone_number'] ?? '',
            'display_name' => $account['display_name'] ?? $account['business_name'] ?? '',
            'quality_rating' => $account['quality_rating'] ?? 'unknown',
            'messaging_limit' => $account['messaging_limit'] ?? '',
            'connection_type' => $account['connection_type'] ?? 'manual',
            'verification_status' => $account['verification_status'] ?? '',
            'status' => $account['status'] ?? 'disconnected'
        ]
    ]);
} catch (Throwable $e) {
    sendJsonResponse('error', 'Failed retrieving account status: ' . $e->getMessage(), [], 500);
}
