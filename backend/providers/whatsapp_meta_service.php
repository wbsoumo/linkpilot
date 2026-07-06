<?php
// backend/providers/whatsapp_meta_service.php

require_once __DIR__ . '/../config.php';

class WhatsAppMetaService {
    
    private static $graphVersion = 'v20.0';

    /**
     * Log trace, error, or debug info to unified file.
     */
    public static function logDebug($message, $data = null) {
        $logFile = __DIR__ . '/../api/whatsapp/whatsapp_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $dataStr = '';
        if ($data !== null) {
            $dataStr = ' | DATA: ' . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        @mkdir(dirname($logFile), 0777, true);
        file_put_contents($logFile, "[{$timestamp}] {$message}{$dataStr}\n", FILE_APPEND);
    }

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
    public static function executeRequest($endpoint, $method = 'GET', $payload = null, $token = null) {
        $db = Database::getConnection();
        
        // 1. Resolve custom Graph API version
        try {
            $stmtVer = $db->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_meta_api_version' LIMIT 1");
            $ver = $stmtVer->fetchColumn();
            if (!empty($ver)) {
                self::$graphVersion = trim($ver);
            }
        } catch (Exception $e) {}
        
        // 2. Resolve timeout and retries limits
        $timeout = 15;
        $maxRetries = 3;
        try {
            $stmtTimeout = $db->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_global_api_timeout' LIMIT 1");
            $tVal = $stmtTimeout->fetchColumn();
            if (!empty($tVal)) $timeout = (int) $tVal;
            
            $stmtRetry = $db->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'whatsapp_retry_attempts' LIMIT 1");
            $rVal = $stmtRetry->fetchColumn();
            if (!empty($rVal)) $maxRetries = (int) $rVal;
        } catch (Exception $e) {}
        if ($maxRetries < 1) $maxRetries = 1;
        if ($timeout < 1) $timeout = 15;
        
        $url = "https://graph.facebook.com/" . self::$graphVersion . "/" . $endpoint;
        
        $headers = [
            "Content-Type: application/json"
        ];
        if ($token) {
            $headers[] = "Authorization: Bearer " . $token;
        }
        
        $response = null;
        $httpCode = 0;
        $err = null;
        
        self::logDebug("Meta Graph API Request: {$method} {$url}" . ($payload ? " | Payload: " . json_encode($payload) : ""));
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            
            if ($method === 'POST' && $payload) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            
            if (!$err) {
                break;
            }
            
            self::logDebug("Meta API cURL error on attempt {$attempt}: {$err}");
            
            if ($attempt === $maxRetries) {
                self::logDebug("Meta API cURL failed completely: {$err}");
                throw new Exception("Graph API cURL Error (After {$attempt} attempts): " . $err);
            }
            
            usleep(150000 * $attempt); // Exponential backoff sleep
        }
        
        self::logDebug("Meta Graph API Response: HTTP {$httpCode} | Response: {$response}");
        
        $data = json_decode($response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $data['error']['message'] ?? "Unknown Meta Graph API Error (HTTP {$httpCode})";
            self::logDebug("Meta Graph API error details: {$msg}");
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
     * Retrieve phone numbers for a WABA with specific profile fields.
     */
    public static function getPhoneNumbers($wabaId, $accessToken) {
        return self::executeRequest("{$wabaId}/phone_numbers?fields=id,display_phone_number,verified_name,quality_rating,status,code_verification_status", "GET", null, $accessToken);
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

    /**
     * Get business details by ID.
     */
    public static function getBusinessDetails($businessId, $accessToken) {
        return self::executeRequest("{$businessId}?fields=id,name", "GET", null, $accessToken);
    }

    /**
     * Get owned WABA accounts for a business.
     */
    public static function getOwnedWabas($businessId, $accessToken) {
        return self::executeRequest("{$businessId}/owned_whatsapp_business_accounts?fields=id,name,timezone_id,status", "GET", null, $accessToken);
    }

    /**
     * Get client WABA accounts for a business.
     */
    public static function getClientWabas($businessId, $accessToken) {
        return self::executeRequest("{$businessId}/client_whatsapp_business_accounts?fields=id,name,timezone_id,status", "GET", null, $accessToken);
    }

    /**
     * Get token permissions.
     */
    public static function getTokenPermissions($accessToken) {
        return self::executeRequest("me/permissions", "GET", null, $accessToken);
    }

    /**
     * Get detailed phone number info.
     */
    public static function getPhoneNumberDetails($phoneNumberId, $accessToken) {
        return self::executeRequest("{$phoneNumberId}?fields=id,display_phone_number,verified_name,quality_rating,messaging_limit_tier,status,code_verification_status", "GET", null, $accessToken);
    }

    /**
     * Get owned WABA accounts directly from user token.
     */
    public static function getWabasDirectly($accessToken) {
        return self::executeRequest("me/whatsapp_business_accounts?fields=id,name,status", "GET", null, $accessToken);
    }

    /**
     * Debug user access token using the global App access token.
     */
    public static function debugToken($accessToken, $appAccessToken) {
        return self::executeRequest("debug_token?input_token={$accessToken}", "GET", null, $appAccessToken);
    }
}
