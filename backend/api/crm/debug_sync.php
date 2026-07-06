<?php
// backend/api/crm/debug_sync.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

try {
    $user = JWTHelper::requireAuth();
    $userId = $user['id'];
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized: ' . $e->getMessage()
    ]);
    exit;
}

$db = Database::getConnection();

header('Content-Type: text/plain');

echo "--- LINKPILOT CRM SYNC DEBUGGER ---\n\n";
echo "CURRENT AUTHENTICATED USER:\n";
echo "  ID: $userId\n";
echo "  Email: {$user['email']}\n";
echo "  Name: {$user['name']}\n\n";

echo "ALL REGISTERED USERS IN DATABASE:\n";
$stmtUsers = $db->query("SELECT id, email, name FROM users");
while ($u = $stmtUsers->fetch()) {
    echo "  - User ID: {$u['id']} | Email: {$u['email']} | Name: {$u['name']}\n";
}
echo "\n";

echo "LEAD VAULT RECORDS BY USER:\n";
$stmtLV = $db->query("SELECT user_id, COUNT(*) as cnt FROM lead_vault GROUP BY user_id");
while ($r = $stmtLV->fetch()) {
    echo "  - User ID: {$r['user_id']} has {$r['cnt']} leads in lead_vault\n";
}
$stmtLVDetail = $db->query("SELECT user_id, name, company_name, email FROM lead_vault ORDER BY id DESC LIMIT 10");
echo "  Last 10 leads in lead_vault:\n";
while ($r = $stmtLVDetail->fetch()) {
    echo "    * User: {$r['user_id']} | Name: {$r['name']} | Company: {$r['company_name']} | Email: {$r['email']}\n";
}
echo "\n";

echo "CRM CONTACTS BY USER:\n";
$stmtC = $db->query("SELECT user_id, COUNT(*) as cnt FROM crm_contacts GROUP BY user_id");
while ($r = $stmtC->fetch()) {
    echo "  - User ID: {$r['user_id']} has {$r['cnt']} contacts\n";
}
$stmtCDetail = $db->query("SELECT user_id, name, email FROM crm_contacts ORDER BY id DESC LIMIT 10");
echo "  Last 10 contacts in crm_contacts:\n";
while ($r = $stmtCDetail->fetch()) {
    echo "    * User: {$r['user_id']} | Name: {$r['name']} | Email: {$r['email']}\n";
}
echo "\n";

echo "CRM COMPANIES BY USER:\n";
$stmtCo = $db->query("SELECT user_id, COUNT(*) as cnt FROM crm_companies GROUP BY user_id");
while ($r = $stmtCo->fetch()) {
    echo "  - User ID: {$r['user_id']} has {$r['cnt']} companies\n";
}
$stmtCoDetail = $db->query("SELECT user_id, name FROM crm_companies ORDER BY id DESC LIMIT 10");
echo "  Last 10 companies in crm_companies:\n";
while ($r = $stmtCoDetail->fetch()) {
    echo "    * User: {$r['user_id']} | Name: {$r['name']}\n";
}
echo "\n";
