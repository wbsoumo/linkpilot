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

    // 1. GET /1336994508576785/owned_whatsapp_business_accounts
    try {
        $owned = WhatsAppMetaService::executeRequest($acc['waba_id'] . "/owned_whatsapp_business_accounts", "GET", null, $token);
        echo "1. GET /" . $acc['waba_id'] . "/owned_whatsapp_business_accounts: SUCCESS\n" . json_encode($owned, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Throwable $e) {
        echo "1. GET /" . $acc['waba_id'] . "/owned_whatsapp_business_accounts: FAILED - " . $e->getMessage() . "\n\n";
    }

    // 2. GET /1336994508576785/client_whatsapp_business_accounts
    try {
        $client = WhatsAppMetaService::executeRequest($acc['waba_id'] . "/client_whatsapp_business_accounts", "GET", null, $token);
        echo "2. GET /" . $acc['waba_id'] . "/client_whatsapp_business_accounts: SUCCESS\n" . json_encode($client, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Throwable $e) {
        echo "2. GET /" . $acc['waba_id'] . "/client_whatsapp_business_accounts: FAILED - " . $e->getMessage() . "\n\n";
    }

    // 3. GET /1207566022437332?fields=whatsapp_business_account
    try {
        $parentWaba = WhatsAppMetaService::executeRequest($acc['phone_number_id'] . "?fields=id,whatsapp_business_account", "GET", null, $token);
        echo "3. GET /" . $acc['phone_number_id'] . "?fields=id,whatsapp_business_account: SUCCESS\n" . json_encode($parentWaba, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Throwable $e) {
        echo "3. GET /" . $acc['phone_number_id'] . "?fields=id,whatsapp_business_account: FAILED - " . $e->getMessage() . "\n\n";
    }

    // 4. GET /WABA_ID/message_templates (with dynamic fallback if we find a WABA ID)
    echo "4. Attempting to list message_templates using WABA account dynamically if resolved...\n";

} catch (Throwable $e) {
    echo "Global Error: " . $e->getMessage() . "\n";
}
