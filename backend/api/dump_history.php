<?php
// backend/api/dump_history.php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$db = Database::getConnection();
$stmt = $db->query("SELECT h.*, c.domain as cache_domain, c.status as cache_status FROM email_search_history h LEFT JOIN email_cache c ON h.linkedin_url = c.linkedin_url ORDER BY h.id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_PRETTY_PRINT);
