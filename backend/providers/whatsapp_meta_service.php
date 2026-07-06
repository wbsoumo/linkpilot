<?php
// backend/providers/whatsapp_meta_service.php

require_once __DIR__ . '/../config.php';

class WhatsAppMetaService {
    
    private static $graphVersion = 'v20.0';
    
    /**
     * Get decrypted access token for a user.
     */
    private static function getAccessToken($userId, $overrideToken = null) {
        if (!empty($overrideToken)) {
            return $overrideToken;
        }
        
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT access_token FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $acc = $stmt->fetch();
        
        if (!$acc || empty($acc['access_token'])) {
            throw new Exception("WhatsApp Business Account not connected or access token missing.");
        }
        
        $decrypted = decryptData($acc['access_token']);
        return ($decrypted !== false) ? $decrypted : $acc['access_token'];
    }
    
    /**
     * Execute generic Meta Graph API Request.
     */
    private static function executeRequest($endpoint, $method = 'GET', $payload = null, $token = null) {
        $url = "https://graph.facebook.com/" . self::$graphVersion . "/" . $endpoint;
        
        $headers = [
            "Content-Type: application/json"
        ];
        if ($token) {
            $headers[] = "Authorization: Bearer " . $token;
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($method === 'POST' && $payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            throw new Exception("Graph API cURL Error: " . $err);
        }
        
        $data = json_decode($response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $data['error']['message'] ?? "Unknown Meta Graph API Error (HTTP {$httpCode})";
            throw new Exception("Meta API Error: " . $msg);
        }
        
        return $data;
    }
    
    /**
     * Retrieve WhatsApp Business Accounts details.
     */
    public static function getBusinessAccounts($accessToken) {
        return self::executeRequest("me/accounts", "GET", null, $accessToken);
    }
    
    /**
     * Retrieve phone numbers for a WABA.
     */
    public static function getPhoneNumbers($wabaId, $accessToken) {
        return self::executeRequest("{$wabaId}/phone_numbers", "GET", null, $accessToken);
    }
    
    /**
     * Get Phone Number Business Profile info.
     */
    public static function getBusinessProfile($phoneNumberId, $accessToken) {
        return self::executeRequest("{$phoneNumberId}?fields=display_phone_number,verified_name,quality_rating", "GET", null, $accessToken);
    }
    
    /**
     * Send text message.
     */
    public static function sendTextMessage($userId, $phoneNumberId, $to, $text, $overrideToken = null) {
        $token = self::getAccessToken($userId, $overrideToken);
        
        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => "text",
            "text" => [
                "preview_url" => false,
                "body" => $text
            ]
        ];
        
        return self::executeRequest("{$phoneNumberId}/messages", "POST", $payload, $token);
    }
    
    /**
     * Send template message.
     * components parameters format: array of header, body parameters
     */
    public static function sendTemplateMessage($userId, $phoneNumberId, $to, $templateName, $languageCode = 'en', $components = [], $overrideToken = null) {
        $token = self::getAccessToken($userId, $overrideToken);
        
        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => "template",
            "template" => [
                "name" => $templateName,
                "language" => [
                    "code" => $languageCode
                ]
            ]
        ];
        
        if (!empty($components)) {
            $payload["template"]["components"] = $components;
        }
        
        return self::executeRequest("{$phoneNumberId}/messages", "POST", $payload, $token);
    }
    
    /**
     * Send media message (image, video, document, audio).
     */
    public static function sendMediaMessage($userId, $phoneNumberId, $to, $mediaType, $mediaId, $filename = null, $overrideToken = null) {
        $token = self::getAccessToken($userId, $overrideToken);
        
        $mediaPayload = [
            "id" => $mediaId
        ];
        if ($mediaType === 'document' && $filename) {
            $mediaPayload["filename"] = $filename;
        }
        
        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => $mediaType,
            $mediaType => $mediaPayload
        ];
        
        return self::executeRequest("{$phoneNumberId}/messages", "POST", $payload, $token);
    }
    
    /**
     * Sync approved templates from WABA.
     */
    public static function getTemplates($userId, $wabaId, $overrideToken = null) {
        $token = self::getAccessToken($userId, $overrideToken);
        return self::executeRequest("{$wabaId}/message_templates?limit=100", "GET", null, $token);
    }
    
    /**
     * Subscribe app to WABA Webhooks.
     */
    public static function subscribeWebhook($wabaId, $overrideToken) {
        // Post subscription request to Graph API
        return self::executeRequest("{$wabaId}/subscribed_apps", "POST", null, $overrideToken);
    }
    
    /**
     * Download media from Meta to local storage.
     */
    public static function downloadMedia($userId, $mediaId, $overrideToken = null) {
        $token = self::getAccessToken($userId, $overrideToken);
        
        // 1. Get media meta (URL and MIME type)
        $meta = self::executeRequest($mediaId, "GET", null, $token);
        $url = $meta['url'] ?? null;
        $mimeType = $meta['mime_type'] ?? '';
        
        if (!$url) {
            throw new Exception("Failed to retrieve media URL from Meta ID {$mediaId}");
        }
        
        // 2. Download the binary stream
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $token
        ]);
        
        $fileData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$fileData) {
            throw new Exception("Failed downloading media content stream from Meta.");
        }
        
        // 3. Save to local media uploads folder
        $uploadsDir = __DIR__ . '/../../uploads/whatsapp';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        
        // Resolve extension
        $ext = 'bin';
        $parts = explode('/', $mimeType);
        if (count($parts) === 2) {
            $ext = $parts[1];
            // Normalize common types
            if ($ext === 'jpeg') $ext = 'jpg';
            if ($ext === 'plain') $ext = 'txt';
        }
        
        $filename = "wa_" . $mediaId . "_" . time() . "." . $ext;
        $localPath = 'uploads/whatsapp/' . $filename;
        $absolutePath = $uploadsDir . '/' . $filename;
        
        file_put_contents($absolutePath, $fileData);
        
        return [
            "local_path" => $localPath,
            "mime_type" => $mimeType,
            "file_size" => strlen($fileData)
        ];
    }
}
