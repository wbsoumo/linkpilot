<?php
// backend/api/clear_cache.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt_helper.php';

header('Content-Type: application/json');

// Require Admin authorization
JWTHelper::requireAdmin();

$db = Database::getConnection();

try {
    $db->exec("DELETE FROM email_cache");
    echo json_encode(["status" => "success", "message" => "Email cache cleared completely!"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
