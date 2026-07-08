<?php
// backend/test_api_endpoints.php
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

try {
    $db = Database::getConnection();
    echo "=== Running query simulation test ===\n\n";
    
    // 1. users
    echo "1. Simulating users query...\n";
    $stmt = $db->query("SELECT id, name, email, role, active_ai_provider, active_ai_model, created_at FROM users LIMIT 1");
    print_r($stmt->fetch());
    echo "\n";
    
    // 2. smtp_accounts
    echo "2. Simulating smtp_accounts query...\n";
    $stmt = $db->query("SELECT * FROM smtp_accounts LIMIT 1");
    print_r($stmt->fetch());
    echo "\n";
    
    // 3. user_email_credits
    echo "3. Simulating wallet query...\n";
    $stmt = $db->query("SELECT total_credits, used_credits, remaining_credits, free_credits, purchased_credits FROM user_email_credits LIMIT 1");
    print_r($stmt->fetch());
    echo "\n";
    
    // 4. email_credit_transactions today usage
    echo "4. Simulating today transactions query...\n";
    $stmt = $db->query("SELECT IFNULL(SUM(credits), 0) as today_used FROM email_credit_transactions LIMIT 1");
    print_r($stmt->fetch());
    echo "\n";

    // 5. provider stats
    echo "5. Simulating provider stats query...\n";
    $stmt = $db->query("SELECT IFNULL(provider_used, 'None') as provider, COUNT(*) as count FROM email_credit_transactions GROUP BY provider_used");
    print_r($stmt->fetchAll());
    echo "\n";
    
    // 6. user_ai_keys
    echo "6. Simulating user_ai_keys query...\n";
    $stmt = $db->query("SELECT * FROM user_ai_keys LIMIT 1");
    print_r($stmt->fetch());
    echo "\n";
    
    echo "All queries completed successfully!\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
