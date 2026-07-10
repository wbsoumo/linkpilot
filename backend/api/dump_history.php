<?php
// backend/api/dump_history.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt_helper.php';

header('Content-Type: application/json');

// Require Admin authorization
JWTHelper::requireAdmin();

$db = Database::getConnection();
$stmt = $db->query("SELECT h.*, c.domain as cache_domain, c.status as cache_status FROM email_search_history h LEFT JOIN email_cache c ON h.linkedin_url = c.linkedin_url ORDER BY h.id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_PRETTY_PRINT);
