<?php
// backend/api/clear_cache.php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$db = Database::getConnection();

try {
    $db->exec("DELETE FROM email_cache");
    echo json_encode(["status" => "success", "message" => "Email cache cleared completely!"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
