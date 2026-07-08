<?php
// backend/api/whatsapp/disconnect.php
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
    // Soft disconnect: set status to disconnected, remove tokens
    $stmt = $db->prepare("UPDATE whatsapp_accounts SET status = 'disconnected', access_token = NULL, token_status = 'disconnected' WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    logActivity($userId, "Disconnected WhatsApp Business account");
    
    sendJsonResponse('success', 'WhatsApp account disconnected successfully.', [
        'disconnected' => true
    ]);
} catch (Throwable $e) {
    sendJsonResponse('error', 'Failed to disconnect WhatsApp account: ' . $e->getMessage(), [], 500);
}
