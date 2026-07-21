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

    // 1. GET /me
    try {
        $me = WhatsAppMetaService::executeRequest("me", "GET", null, $token);
        echo "1. GET /me: SUCCESS\n" . json_encode($me, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Throwable $e) {
        echo "1. GET /me: FAILED - " . $e->getMessage() . "\n\n";
    }

    // 2. GET /me/permissions
    try {
        $perms = WhatsAppMetaService::executeRequest("me/permissions", "GET", null, $token);
        echo "2. GET /me/permissions: SUCCESS\n" . json_encode($perms, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Throwable $e) {
        echo "2. GET /me/permissions: FAILED - " . $e->getMessage() . "\n\n";
    }

    // 3. GET /me/whatsapp_business_accounts
    try {
        $wabas = WhatsAppMetaService::executeRequest("me/whatsapp_business_accounts", "GET", null, $token);
        echo "3. GET /me/whatsapp_business_accounts: SUCCESS\n" . json_encode($wabas, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Throwable $e) {
        echo "3. GET /me/whatsapp_business_accounts: FAILED - " . $e->getMessage() . "\n\n";
    }

    // 4. GET /WABA_ID
    if (!empty($acc['waba_id'])) {
        try {
            $waba = WhatsAppMetaService::executeRequest($acc['waba_id'], "GET", null, $token);
            echo "4. GET /" . $acc['waba_id'] . ": SUCCESS\n" . json_encode($waba, JSON_PRETTY_PRINT) . "\n\n";
        } catch (Throwable $e) {
            echo "4. GET /" . $acc['waba_id'] . ": FAILED - " . $e->getMessage() . "\n\n";
        }
    }

    // 5. GET /PHONE_ID
    if (!empty($acc['phone_number_id'])) {
        try {
            $phone = WhatsAppMetaService::executeRequest($acc['phone_number_id'], "GET", null, $token);
            echo "5. GET /" . $acc['phone_number_id'] . ": SUCCESS\n" . json_encode($phone, JSON_PRETTY_PRINT) . "\n\n";
        } catch (Throwable $e) {
            echo "5. GET /" . $acc['phone_number_id'] . ": FAILED - " . $e->getMessage() . "\n\n";
        }
    }

    // 6. GET /WABA_ID/message_templates
    if (!empty($acc['waba_id'])) {
        try {
            $tpls = WhatsAppMetaService::executeRequest($acc['waba_id'] . "/message_templates?limit=5", "GET", null, $token);
            echo "6. GET /" . $acc['waba_id'] . "/message_templates: SUCCESS\n" . json_encode($tpls, JSON_PRETTY_PRINT) . "\n\n";
        } catch (Throwable $e) {
            echo "6. GET /" . $acc['waba_id'] . "/message_templates: FAILED - " . $e->getMessage() . "\n\n";
        }
    }

} catch (Throwable $e) {
    echo "Global Error: " . $e->getMessage() . "\n";
}
