<?php
require_once '/Users/wbsoumo/Desktop/LinkPilot AI/backend/config.php';
$db = Database::getConnection();
$stmt = $db->query("SELECT id, user_id, display_phone_number, waba_id, status FROM whatsapp_accounts");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
