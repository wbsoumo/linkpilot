<?php
// backend/api/whatsapp/templates.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'GET') {
        // List local approved templates
        $stmt = $db->prepare("SELECT * FROM whatsapp_templates WHERE user_id = ? ORDER BY name ASC");
        $stmt->execute([$userId]);
        $templates = $stmt->fetchAll();
        
        sendJsonResponse('success', 'Templates loaded.', [
            'templates' => $templates
        ]);
    }
    
    elseif ($method === 'SYNC') {
        // Trigger sync with Meta Business Cloud API
        $stmtAcc = $db->prepare("SELECT waba_id, access_token FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
        $stmtAcc->execute([$userId]);
        $acc = $stmtAcc->fetch();
        
        if (!$acc) {
            sendJsonResponse('error', 'No connected WhatsApp Business Account found. Connect account first.', [], 400);
        }
        
        $wabaId = $acc['waba_id'];
        $encryptedToken = $acc['access_token'];
        $decrypted = decryptData($encryptedToken);
        $accessToken = ($decrypted !== false) ? $decrypted : $encryptedToken;
        
        $isMock = (strpos($accessToken, 'Mock') !== false || $accessToken === 'EAAGemini' || $accessToken === 'EAAGeminiTest');
        
        try {
            $tplData = [];
            if (!$isMock) {
                $metaTemplates = WhatsAppMetaService::getTemplates($userId, $wabaId, $accessToken);
                $tplData = $metaTemplates['data'] ?? [];
            } else {
                $tplData = [
                    [
                        'name' => 'welcome_message',
                        'category' => 'UTILITY',
                        'language' => 'en',
                        'status' => 'APPROVED',
                        'components' => [
                            ['type' => 'BODY', 'text' => 'Hello {{1}}, thank you for connecting with us! We have received your inquiry regarding our CRM services and will get back to you shortly.']
                        ]
                    ],
                    [
                        'name' => 'discount_promo',
                        'category' => 'MARKETING',
                        'language' => 'en',
                        'status' => 'APPROVED',
                        'components' => [
                            ['type' => 'BODY', 'text' => 'Hey {{1}}, check out this exclusive offer! Get 20% off all LinkPilot CRM plans this month. Use code: OUTREACH20.']
                        ]
                    ],
                    [
                        'name' => 'followup_reminder',
                        'category' => 'UTILITY',
                        'language' => 'en',
                        'status' => 'APPROVED',
                        'components' => [
                            ['type' => 'BODY', 'text' => 'Hi {{1}}, just following up on our chat. Let us know if you have any questions about the proposal we sent. Have a great day!']
                        ]
                    ]
                ];
            }
            
            $db->beginTransaction();
            try {
                // Insert/Update templates locally
                $stmtUpsert = $db->prepare("
                    INSERT INTO whatsapp_templates (user_id, name, category, language, status, components_json)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        category = VALUES(category),
                        status = VALUES(status),
                        components_json = VALUES(components_json),
                        last_sync_at = CURRENT_TIMESTAMP
                ");
                
                foreach ($tplData as $tpl) {
                    $name = $tpl['name'] ?? '';
                    $category = $tpl['category'] ?? '';
                    $language = $tpl['language'] ?? 'en';
                    $status = $tpl['status'] ?? 'APPROVED';
                    $componentsJson = json_encode($tpl['components'] ?? []);
                    
                    $stmtUpsert->execute([
                        $userId, $name, $category, $language, $status, $componentsJson
                    ]);
                }
                
                // Save Sync Milestone
                $db->prepare("
                    INSERT INTO whatsapp_template_sync (user_id, waba_id, last_sync)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE last_sync = NOW()
                ")->execute([$userId, $wabaId]);
                
                $db->commit();
            } catch (Exception $tx) {
                $db->rollBack();
                throw $tx;
            }
            
            sendJsonResponse('success', 'WhatsApp templates synced successfully.', [
                'count' => count($tplData)
            ]);
            
        } catch (Exception $e) {
            sendJsonResponse('error', 'Meta template sync failed: ' . $e->getMessage(), [], 400);
        }
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Template operation failed: ' . $e->getMessage(), [], 500);
}
