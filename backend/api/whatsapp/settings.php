<?php
// backend/api/whatsapp/settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $db->prepare("SELECT * FROM whatsapp_settings WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch();
        
        if (!$settings) {
            // Default settings record
            $maxNumbers = ($user['role'] === 'admin') ? 999 : 1;
            $db->prepare("
                INSERT INTO whatsapp_settings (user_id, max_numbers)
                VALUES (?, ?)
            ")->execute([$userId, $maxNumbers]);
            
            $stmt->execute([$userId]);
            $settings = $stmt->fetch();
        }
        
        sendJsonResponse('success', 'Settings retrieved.', [
            'settings' => $settings
        ]);
    }
    
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $aiEnabled = isset($input['ai_enabled']) ? (int)$input['ai_enabled'] : 1;
        $autoCrm = isset($input['auto_crm_creation']) ? (int)$input['auto_crm_creation'] : 1;
        $autoLead = isset($input['auto_lead_detection']) ? (int)$input['auto_lead_detection'] : 1;
        $autoContact = isset($input['auto_contact_detection']) ? (int)$input['auto_contact_detection'] : 1;
        $autoCompany = isset($input['auto_company_detection']) ? (int)$input['auto_company_detection'] : 1;
        $autoReply = isset($input['auto_reply_suggestions']) ? (int)$input['auto_reply_suggestions'] : 1;
        $autoSummarize = isset($input['auto_summarize_history']) ? (int)$input['auto_summarize_history'] : 1;
        $mediaLimit = isset($input['media_upload_limit_mb']) ? (int)$input['media_upload_limit_mb'] : 16;
        $fileTypes = trim($input['allowed_file_types'] ?? 'jpg,png,pdf,mp4,mp3,docx');
        
        $stmt = $db->prepare("
            UPDATE whatsapp_settings 
            SET ai_enabled = ?,
                auto_crm_creation = ?,
                auto_lead_detection = ?,
                auto_contact_detection = ?,
                auto_company_detection = ?,
                auto_reply_suggestions = ?,
                auto_summarize_history = ?,
                media_upload_limit_mb = ?,
                allowed_file_types = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $aiEnabled, $autoCrm, $autoLead, $autoContact, $autoCompany, $autoReply, $autoSummarize, $mediaLimit, $fileTypes, $userId
        ]);
        
        sendJsonResponse('success', 'WhatsApp settings updated successfully.');
    }
    
    else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Settings update failed: ' . $e->getMessage(), [], 500);
}
