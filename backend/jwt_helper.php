<?php
// backend/jwt_helper.php

require_once __DIR__ . '/config.php';

class JWTHelper {
    
    /**
     * Base64URL Encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64URL Decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Generate JWT Token
     */
    public static function generateToken($userData) {
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);
        
        $issuedAt = time();
        $expirationTime = $issuedAt + JWT_EXPIRY;
        
        $payload = json_encode([
            'iss' => 'linkpilot_api',
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'user' => [
                'id' => $userData['id'],
                'email' => $userData['email'],
                'name' => $userData['name'],
                'role' => $userData['role'] ?? 'user'
            ]
        ]);
        
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    /**
     * Validate JWT Token
     */
    public static function validateToken($token) {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            return false;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $tokenParts;
        
        $signature = self::base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        $payload = json_encode(json_decode(self::base64UrlDecode($base64UrlPayload), true));
        $payloadData = json_decode($payload, true);
        
        if (!$payloadData) {
            return false;
        }
        
        // Check Expiry
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData['user'] ?? false;
    }
    
    /**
     * Get Authenticated User from Request Headers
     */
    public static function getAuthenticatedUser() {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        if (!$headers) {
            // Check query param as fallback for files/scripts/extension downloads
            if (isset($_GET['token'])) {
                $headers = "Bearer " . $_GET['token'];
            } else {
                return false;
            }
        }
        
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            $token = $matches[1];
            return self::validateToken($token);
        }
        
        return false;
    }
    
    /**
     * Authenticate Request Middleware
     */
    public static function requireAuth() {
        $user = self::getAuthenticatedUser();
        if (!$user) {
            sendJsonResponse('error', 'Unauthorized access. Invalid or expired token.', [], 401);
        }
        return $user;
    }
    
    /**
     * Authenticate Admin Request Middleware
     */
    public static function requireAdmin() {
        $user = self::requireAuth();
        if (($user['role'] ?? 'user') !== 'admin') {
            sendJsonResponse('error', 'Forbidden. Admin privileges required.', [], 403);
        }
        return $user;
    }
}
