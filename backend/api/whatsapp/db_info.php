<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json');

$output = [];
try {
    $db = Database::getConnection();
    $user = $db->query("SELECT id, name, email, active_ai_provider, active_ai_model, 
                               (github_key IS NOT NULL AND github_key != '') as has_github_key,
                               (google_key IS NOT NULL AND google_key != '') as has_google_key,
                               (openrouter_key IS NOT NULL AND openrouter_key != '') as has_openrouter_key
                        FROM users WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $output['user'] = $user;
} catch (Throwable $e) {
    $output['error'] = $e->getMessage();
}

echo json_encode($output, JSON_PRETTY_PRINT);
