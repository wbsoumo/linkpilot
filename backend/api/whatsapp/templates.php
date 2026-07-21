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
        $stmtAcc = $db->prepare("SELECT waba_id, phone_number_id, access_token FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
        $stmtAcc->execute([$userId]);
        $acc = $stmtAcc->fetch();
        
        if (!$acc) {
            sendJsonResponse('error', 'No connected WhatsApp Business Account found. Connect account first.', [], 400);
        }
        
        $wabaId = $acc['waba_id'];
        $phoneId = $acc['phone_number_id'] ?? '';
        $encryptedToken = $acc['access_token'];
        $decrypted = decryptData($encryptedToken);
        $accessToken = ($decrypted !== false) ? $decrypted : $encryptedToken;
        
        $isMock = (strpos($accessToken, 'Mock') !== false || $accessToken === 'EAAGemini' || $accessToken === 'EAAGeminiTest');
        
        try {
            $tplData = [];
            if (!$isMock) {
                try {
                    $metaTemplates = WhatsAppMetaService::getTemplates($userId, $wabaId, $accessToken);
                    $tplData = $metaTemplates['data'] ?? [];
                } catch (Throwable $e) {
                    $healed = false;
                    
                    // Fallback 1: Fetch WABAs assigned to the System User (most common for manual tokens)
                    try {
                        $me = WhatsAppMetaService::executeRequest("me", "GET", null, $accessToken);
                        $sysUserId = $me['id'] ?? '';
                        if (!empty($sysUserId)) {
                            $assignedRes = WhatsAppMetaService::executeRequest("{$sysUserId}/assigned_whatsapp_business_accounts?fields=id,name,status", "GET", null, $accessToken);
                            $assignedWabas = $assignedRes['data'] ?? [];
                            foreach ($assignedWabas as $w) {
                                $possibleWabaId = $w['id'] ?? '';
                                if (!empty($possibleWabaId) && $possibleWabaId !== $wabaId) {
                                    try {
                                        $metaTemplates = WhatsAppMetaService::getTemplates($userId, $possibleWabaId, $accessToken);
                                        $tplData = $metaTemplates['data'] ?? [];
                                        
                                        // Auto-correct database
                                        $stmtUpdate = $db->prepare("UPDATE whatsapp_accounts SET waba_id = ? WHERE user_id = ?");
                                        $stmtUpdate->execute([$possibleWabaId, $userId]);
                                        $healed = true;
                                        break;
                                    } catch (Throwable $ignore) {}
                                }
                            }
                        }
                    } catch (Throwable $ignore) {}
                    
                    // Fallback 2: If stored WABA ID is actually a Business Manager ID, fetch WABAs owned by/shared with it
                    if (!$healed && !empty($wabaId)) {
                        try {
                            $wabasList = [];
                            try {
                                $ownedRes = WhatsAppMetaService::executeRequest("{$wabaId}/owned_whatsapp_business_accounts?fields=id,name,status", "GET", null, $accessToken);
                                if (!empty($ownedRes['data'])) $wabasList = array_merge($wabasList, $ownedRes['data']);
                            } catch (Throwable $ignore) {}
                            
                            try {
                                $clientRes = WhatsAppMetaService::executeRequest("{$wabaId}/client_whatsapp_business_accounts?fields=id,name,status", "GET", null, $accessToken);
                                if (!empty($clientRes['data'])) $wabasList = array_merge($wabasList, $clientRes['data']);
                            } catch (Throwable $ignore) {}
                            
                            foreach ($wabasList as $w) {
                                $possibleWabaId = $w['id'] ?? '';
                                if (!empty($possibleWabaId) && $possibleWabaId !== $wabaId) {
                                    try {
                                        $metaTemplates = WhatsAppMetaService::getTemplates($userId, $possibleWabaId, $accessToken);
                                        $tplData = $metaTemplates['data'] ?? [];
                                        
                                        // Auto-correct database
                                        $stmtUpdate = $db->prepare("UPDATE whatsapp_accounts SET waba_id = ? WHERE user_id = ?");
                                        $stmtUpdate->execute([$possibleWabaId, $userId]);
                                        $healed = true;
                                        break;
                                    } catch (Throwable $ignore) {}
                                }
                            }
                        } catch (Throwable $ignore) {}
                    }
                    
                    // Fallback 3: Get parent WABA ID from the Phone Number ID directly (whatsapp_business_account field)
                    if (!$healed && !empty($phoneId)) {
                        try {
                            $phoneDetails = WhatsAppMetaService::executeRequest("{$phoneId}?fields=whatsapp_business_account", "GET", null, $accessToken);
                            $parentWabaId = $phoneDetails['whatsapp_business_account']['id'] ?? '';
                            if (!empty($parentWabaId) && $parentWabaId !== $wabaId) {
                                try {
                                    $metaTemplates = WhatsAppMetaService::getTemplates($userId, $parentWabaId, $accessToken);
                                    $tplData = $metaTemplates['data'] ?? [];
                                    
                                    // Auto-correct database
                                    $stmtUpdate = $db->prepare("UPDATE whatsapp_accounts SET waba_id = ? WHERE user_id = ?");
                                    $stmtUpdate->execute([$parentWabaId, $userId]);
                                    $healed = true;
                                } catch (Throwable $ignore) {}
                            }
                        } catch (Throwable $ignore) {}
                    }
                    
                    // Fallback 4: Query all global WABA accounts associated with token (standard list)
                    if (!$healed) {
                        try {
                            $wabaRes = WhatsAppMetaService::getWabasDirectly($accessToken);
                            $wabas = $wabaRes['data'] ?? [];
                            foreach ($wabas as $w) {
                                $possibleWabaId = $w['id'] ?? '';
                                if (!empty($possibleWabaId) && $possibleWabaId !== $wabaId) {
                                    try {
                                        $metaTemplates = WhatsAppMetaService::getTemplates($userId, $possibleWabaId, $accessToken);
                                        $tplData = $metaTemplates['data'] ?? [];
                                        
                                        // Auto-correct database
                                        $stmtUpdate = $db->prepare("UPDATE whatsapp_accounts SET waba_id = ? WHERE user_id = ?");
                                        $stmtUpdate->execute([$possibleWabaId, $userId]);
                                        $healed = true;
                                        break;
                                    } catch (Throwable $ignore) {}
                                }
                            }
                        } catch (Throwable $ignore) {}
                    }
                    
                    // Fallback 4: If still not healed, try swapping WABA ID and Phone ID (swapped fields fallback)
                    if (!$healed) {
                        if (!empty($phoneId) && $phoneId !== $wabaId) {
                            try {
                                $metaTemplates = WhatsAppMetaService::getTemplates($userId, $phoneId, $accessToken);
                                $tplData = $metaTemplates['data'] ?? [];
                                
                                // Swap WABA and Phone ID in database
                                $stmtSwap = $db->prepare("UPDATE whatsapp_accounts SET waba_id = ?, phone_number_id = ? WHERE user_id = ?");
                                $stmtSwap->execute([$phoneId, $wabaId, $userId]);
                                $healed = true;
                            } catch (Throwable $fallbackEx) {
                                throw new Exception($e->getMessage() . " (Tried WABA ID: {$wabaId}, Phone ID: {$phoneId})");
                            }
                        } else {
                            throw new Exception($e->getMessage() . " (Tried WABA ID: {$wabaId})");
                        }
                    }
                }
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
