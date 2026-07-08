<?php
// backend/api/whatsapp/embedded_signup.php
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
    // Retrieve global Meta App ID from admin_settings
    $stmtAppId = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_meta_app_id' LIMIT 1");
    $stmtAppId->execute();
    $metaAppId = $stmtAppId->fetchColumn() ?: '';
    
    // Generate secure CSRF token for this signup session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $csrfToken = bin2hex(random_bytes(16));
    $_SESSION['wa_oauth_state'] = $csrfToken;
    
    sendJsonResponse('success', 'Meta App config retrieved successfully.', [
        'app_id' => $metaAppId,
        'scopes' => 'whatsapp_business_management,whatsapp_business_messaging,public_profile',
        'state' => $csrfToken
    ]);
} catch (Throwable $e) {
    sendJsonResponse('error', 'Failed retrieving signup configurations: ' . $e->getMessage(), [], 500);
}
