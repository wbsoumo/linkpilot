<?php
// backend/api/auth/complete_setup.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];

$db = Database::getConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $completed = isset($input['setup_completed']) ? (int)$input['setup_completed'] : 1;
    $step = isset($input['setup_step']) ? (int)$input['setup_step'] : 5;

    // Ensure columns exist
    try {
        $stmtCol1 = $db->query("SHOW COLUMNS FROM `user_profiles` LIKE 'setup_completed'");
        if (!$stmtCol1->fetch()) {
            $db->exec("ALTER TABLE `user_profiles` ADD COLUMN `setup_completed` TINYINT(1) DEFAULT 0");
        }
        $stmtCol2 = $db->query("SHOW COLUMNS FROM `user_profiles` LIKE 'setup_step'");
        if (!$stmtCol2->fetch()) {
            $db->exec("ALTER TABLE `user_profiles` ADD COLUMN `setup_step` INT DEFAULT 1");
        }
    } catch (Exception $ex) {}

    // Check if profile exists
    $stmtProfileCheck = $db->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
    $stmtProfileCheck->execute([$userId]);
    if ($stmtProfileCheck->fetch()) {
        $stmtUpdate = $db->prepare("UPDATE user_profiles SET setup_completed = ?, setup_step = ? WHERE user_id = ?");
        $stmtUpdate->execute([$completed, $step, $userId]);
    } else {
        $stmtInsert = $db->prepare("INSERT INTO user_profiles (user_id, user_type, setup_completed, setup_step) VALUES (?, '', ?, ?)");
        $stmtInsert->execute([$userId, $completed, $step]);
    }

    sendJsonResponse('success', 'Setup completion status updated successfully.');
} catch (Exception $e) {
    sendJsonResponse('error', 'Failed updating setup status: ' . $e->getMessage(), [], 500);
}
