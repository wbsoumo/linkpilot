<?php
// backend/api/dump_scraper_logs.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt_helper.php';

header('Content-Type: application/json');

// Require Admin authorization
JWTHelper::requireAdmin();

$db = Database::getConnection();
$stmt = $db->query("SELECT * FROM scraper_requests_log ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_PRETTY_PRINT);
