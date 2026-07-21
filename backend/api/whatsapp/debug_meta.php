<?php
// backend/api/whatsapp/debug_meta.php
header('Content-Type: text/plain');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT * FROM whatsapp_accounts ORDER BY id DESC LIMIT 1");
    $acc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$acc) {
        die("No WhatsApp account record found in database.\n");
    }

    echo "--- Database Record ---\n";
    echo "ID: " . $acc['id'] . "\n";
    echo "WABA ID: " . $acc['waba_id'] . "\n";
    echo "Phone ID: " . $acc['phone_number_id'] . "\n";
    echo "Business ID: " . $acc['business_id'] . "\n";
    echo "Connection Type: " . $acc['connection_type'] . "\n";
    echo "Status: " . $acc['status'] . "\n\n";

    $token = decryptData($acc['access_token']);
    if (!$token) {
        $token = $acc['access_token'];
    }

    echo "--- Meta API Debug Requests ---\n";

    // 1. GET /4532079027068465/assigned_whatsapp_business_accounts
    try {
        $assigned = WhatsAppMetaService::executeRequest("4532079027068465/assigned_whatsapp_business_accounts", "GET", null, $token);
        echo "1. GET /4532079027068465/assigned_whatsapp_business_accounts: SUCCESS\n" . json_encode($assigned, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Throwable $e) {
        echo "1. GET /4532079027068465/assigned_whatsapp_business_accounts: FAILED - " . $e->getMessage() . "\n\n";
    }

} catch (Throwable $e) {
    echo "Global Error: " . $e->getMessage() . "\n";
}
