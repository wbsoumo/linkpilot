<?php
// backend/config.example.php
// Rename this file to config.php and enter your actual credentials.

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Kolkata');

function sendCorsHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

sendCorsHeaders();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_PORT', '3306');

// JWT Configuration
define('JWT_SECRET', 'replace_this_with_a_long_random_string_for_security');
define('JWT_EXPIRY', 86400 * 7);

// SMTP Password Encryption Configuration
define('ENCRYPTION_KEY', 'replace_this_with_a_secure_32_character_string');
define('ENCRYPTION_METHOD', 'aes-256-cbc');

// OpenRouter API Configuration
define('OPENROUTER_API_KEY', 'your_openrouter_api_key_here');
define('OPENROUTER_MODEL', 'google/gemini-2.0-flash-lite:free');

// Helper function to encrypt sensitive data (SMTP passwords)
function encryptData($data) {
    $key = hash('sha256', ENCRYPTION_KEY);
    $ivSize = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($ivSize);
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, $key, 0, $iv);
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

// Helper function to decrypt sensitive data
function decryptData($encryptedData) {
    $key = hash('sha256', ENCRYPTION_KEY);
    $decoded = base64_decode($encryptedData);
    if (!$decoded) return false;
    
    $parts = explode('::', $decoded);
    if (count($parts) !== 2) return false;
    
    list($encrypted, $ivBase64) = $parts;
    $iv = base64_decode($ivBase64);
    $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, 0, $iv);
    return $decrypted;
}

// Database Connection Singleton
class Database {
    private static $instance = null;
    
    public static function getConnection() {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Database Connection Failed: " . $e->getMessage()
                ]);
                exit;
            }
        }
        return self::$instance;
    }
}

function sendJsonResponse($status, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        "status" => $status,
        "message" => $message
    ], $data));
    exit;
}

function logActivity($userId, $action) {
    try {
        $db = Database::getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $ip]);
    } catch (Exception $e) {}
}

function updateStatistic($userId, $column, $increment = 1) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO user_statistics (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = user_id");
        $stmt->execute([$userId]);
        
        $allowedColumns = ['total_requests', 'emails_generated', 'emails_sent', 'whatsapp_generated', 'comments_generated'];
        if (in_array($column, $allowedColumns)) {
            $sql = "UPDATE user_statistics SET `{$column}` = `{$column}` + :increment WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['increment' => $increment, 'user_id' => $userId]);
        }
    } catch (Exception $e) {}
}

// Call OpenRouter API
function callOpenRouter($systemPrompt, $userPrompt) {
    $apiKey = OPENROUTER_API_KEY;
    $model = OPENROUTER_MODEL;
    
    $headers = [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
        "HTTP-Referer: http://localhost:8000",
        "X-Title: LinkPilot AI"
    ];
    
    $postFields = [
        "model" => $model,
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $userPrompt]
        ]
    ];
    
    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("OpenRouter connection error: " . $error);
    }
    
    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? "Unknown OpenRouter Error";
        throw new Exception("OpenRouter API Error (HTTP {$httpCode}): " . $msg);
    }
    
    $generatedText = $data['choices'][0]['message']['content'] ?? '';
    $tokensUsed = $data['usage']['total_tokens'] ?? 0;
    
    return [
        "text" => trim($generatedText),
        "tokens" => $tokensUsed
    ];
}
