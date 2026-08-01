<?php
// backend/check_keys_decrypt.php
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

$db = Database::getConnection();
try {
    $stmt = $db->query("
        SELECT k.id, k.user_id, u.name as user_name, u.role as user_role, k.provider, k.api_key, k.status, k.error_message 
        FROM user_ai_keys k
        JOIN users u ON k.user_id = u.id
    ");
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "--- LinkPilot AI Key Diagnostic Tool ---\n\n";
    echo "Current ENCRYPTION_KEY length: " . strlen(ENCRYPTION_KEY) . " characters\n";
    echo "Total keys found in database: " . count($keys) . "\n\n";
    
    foreach ($keys as $k) {
        $raw = $k['api_key'];
        $decrypted = decryptData($raw);
        
        echo "Key ID: {$k['id']}\n";
        echo "Owner: ID={$k['user_id']}, Name={$k['user_name']}, Role={$k['user_role']}\n";
        echo "Provider: {$k['provider']}\n";
        echo "Status: {$k['status']}\n";
        echo "Decryption success: " . ($decrypted !== false ? "YES" : "NO") . "\n";
        
        if ($decrypted !== false) {
            $len = strlen($decrypted);
            $prefix = substr($decrypted, 0, 11);
            echo "Decrypted Key Length: $len characters\n";
            echo "Decrypted Key Start: $prefix...\n";
            
            // Format check
            if (strpos($decrypted, 'ghp_') === 0) {
                echo "Format Check: Valid GitHub Classic PAT format (starts with ghp_)\n";
            } elseif (strpos($decrypted, 'github_pat_') === 0) {
                echo "Format Check: Valid GitHub Fine-grained PAT format (starts with github_pat_)\n";
            } else {
                echo "Format Check: WARNING - Does not start with standard GitHub prefixes ('ghp_' or 'github_pat_')!\n";
            }
        } else {
            echo "WARNING: Could not decrypt this key! Falling back to raw value.\n";
            echo "Raw Value Length: " . strlen($raw) . "\n";
            echo "Raw Value Start: " . substr($raw, 0, 15) . "...\n";
        }
        echo "Error message in DB: {$k['error_message']}\n";
        echo "----------------------------------------\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
