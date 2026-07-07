<?php
// backend/api/whatsapp/test_send_tpl.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

header('Content-Type: text/plain; charset=utf-8');

$userId = 1; // Default user ID
$db = Database::getConnection();

echo "--- WHATSAPP DIRECT SEND DIAGNOSTICS ---\n";

try {
    // 1. Fetch connected account
    $stmtAcc = $db->prepare("SELECT * FROM whatsapp_accounts WHERE status = 'connected' ORDER BY id DESC LIMIT 1");
    $stmtAcc->execute();
    $acc = $stmtAcc->fetch();
    
    if (!$acc) {
        $msg = "No connected WhatsApp accounts found in database.";
        echo "ERROR: $msg\n";
        file_put_contents(__DIR__ . '/wa_temp_debug.txt', $msg);
        exit;
    }
    
    $phoneNumberId = $acc['phone_number_id'];
    $encryptedToken = $acc['access_token'];
    $decrypted = decryptData($encryptedToken);
    $accessToken = ($decrypted !== false) ? $decrypted : $encryptedToken;
    
    echo "Using Account ID: " . $acc['id'] . "\n";
    echo "Display Phone: " . $acc['display_phone_number'] . "\n";
    echo "Phone Number ID: " . $phoneNumberId . "\n";
    
    // 2. Fetch templates
    $stmtTpl = $db->prepare("SELECT * FROM whatsapp_templates WHERE name = 'otp_login' LIMIT 1");
    $stmtTpl->execute();
    $tpl = $stmtTpl->fetch();
    
    if (!$tpl) {
        $stmtTplAny = $db->prepare("SELECT * FROM whatsapp_templates LIMIT 1");
        $stmtTplAny->execute();
        $tpl = $stmtTplAny->fetch();
    }
    
    if (!$tpl) {
        $msg = "No templates found in database. Please sync templates first.";
        echo "ERROR: $msg\n";
        file_put_contents(__DIR__ . '/wa_temp_debug.txt', $msg);
        exit;
    }
    
    $templateName = $tpl['name'];
    $lang = $tpl['language'];
    $category = $tpl['category'];
    
    echo "Using Template: $templateName ($lang) | Category: $category\n";
    
    // Define a test recipient number (using the user's phone or a generic test number)
    $recipient = '919242322991'; // Fallback test number
    
    // Look up last contact from crm_contacts to make it live if possible!
    $stmtContact = $db->query("SELECT phone FROM crm_contacts WHERE phone IS NOT NULL AND phone != '' LIMIT 1");
    $dbPhone = $stmtContact->fetchColumn();
    if ($dbPhone) {
        $recipient = preg_replace('/[^0-9]/', '', $dbPhone);
    }
    
    echo "Target Recipient: $recipient\n";
    
    // Prepare variables components
    $components = [
        [
            "type" => "body",
            "parameters" => [
                [
                    "type" => "text",
                    "text" => "123456"
                ]
            ]
        ]
    ];
    
    $isAuth = ($category === 'AUTHENTICATION' || stripos($templateName, 'otp') !== false || stripos($templateName, 'auth') !== false);
    if ($isAuth) {
        $components[] = [
            "type" => "button",
            "sub_type" => "otp",
            "index" => 0,
            "parameters" => [
                [
                    "type" => "text",
                    "text" => "123456"
                ]
            ]
        ];
    }
    
    echo "Sending payload components: " . json_encode($components) . "\n";
    
    // 3. Dispatch to Meta
    $response = WhatsAppMetaService::sendTemplateMessage($userId, $phoneNumberId, $recipient, $templateName, $lang, $components, $accessToken);
    
    $successMsg = "SUCCESS! Message sent successfully. Meta ID: " . ($response['messages'][0]['id'] ?? 'unknown');
    echo "$successMsg\n";
    file_put_contents(__DIR__ . '/wa_temp_debug.txt', $successMsg . "\nResponse: " . json_encode($response));
    
} catch (Exception $e) {
    $errorMsg = "FAILED! Exception: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString();
    echo "ERROR: $errorMsg\n";
    file_put_contents(__DIR__ . '/wa_temp_debug.txt', $errorMsg);
}
