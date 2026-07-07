<?php
require_once '/Users/wbsoumo/Desktop/LinkPilot AI/backend/config.php';
require_once '/Users/wbsoumo/Desktop/LinkPilot AI/backend/providers/whatsapp_meta_service.php';

$db = Database::getConnection();
$stmt = $db->query("SELECT * FROM whatsapp_accounts ORDER BY id DESC LIMIT 1");
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    echo "No whatsapp account found in DB.\n";
    exit;
}

$accessToken = decryptData($account['access_token']);
$phoneId = $account['phone_number_id'];

echo "Phone Number ID: $phoneId\n";
try {
    $url = "$phoneId?fields=id,display_phone_number,verified_name,quality_rating,messaging_limit_tier,status,code_verification_status";
    $res = WhatsAppMetaService::executeRequest($url, "GET", null, $accessToken);
    echo "\n--- META RESPONSE ---\n";
    print_r($res);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
