<?php
// backend/copy_keys.php
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

$db = Database::getConnection();
try {
    // 1. Fetch Key ID 4 details
    $stmt = $db->prepare("SELECT api_key, provider FROM user_ai_keys WHERE id = 4");
    $stmt->execute();
    $keyDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$keyDetails) {
        throw new Exception("Key ID 4 not found in database.");
    }
    
    $encryptedKey = $keyDetails['api_key'];
    $provider = $keyDetails['provider'];
    
    // 2. Insert or update this key for Admin (User ID 1)
    // First, let's delete any invalid/paused github_models keys for User ID 1 to clean up
    $stmtDel = $db->prepare("DELETE FROM user_ai_keys WHERE user_id = 1 AND provider = ?");
    $stmtDel->execute([$provider]);
    
    // Insert the new key for User ID 1
    $stmtIns = $db->prepare("INSERT INTO user_ai_keys (user_id, provider, api_key, status) VALUES (1, ?, ?, 'active')");
    $stmtIns->execute([$provider, $encryptedKey]);
    
    // 3. Set Admin active AI provider to github_models
    $stmtUser = $db->prepare("UPDATE users SET active_ai_provider = ?, active_ai_model = 'gpt-4o-mini' WHERE id = 1");
    $stmtUser->execute([$provider]);
    
    echo "Success!\n";
    echo "1. Copied the valid working key to Admin (User ID 1).\n";
    echo "2. Deleted old invalid keys under User ID 1.\n";
    echo "3. Set Admin active provider to 'github_models' with model 'gpt-4o-mini'.\n";
    echo "\nYou can now try generating the email in your extension!";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
