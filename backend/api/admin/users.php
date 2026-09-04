<?php
// backend/api/admin/users.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Admin privileges
$admin = JWTHelper::requireAdmin();
$adminId = $admin['id'];

$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Ensure status column exists in users table
    try {
        $cCheck = $db->query("SHOW COLUMNS FROM `users` LIKE 'status'");
        if (!$cCheck->fetch()) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `status` VARCHAR(20) DEFAULT 'active'");
        }
    } catch (Exception $ex) {}

    if ($method === 'GET') {
        if (isset($_GET['user_id'])) {
            $targetUserId = (int)$_GET['user_id'];

            // Fetch user info
            $stmtUser = $db->prepare("
                SELECT u.id, u.name, u.email, u.phone_number, u.role, u.is_verified, COALESCE(u.status, 'active') as status, u.created_at,
                       u.active_ai_provider, u.active_ai_model,
                       p.user_type, p.job_title, p.company_name, p.website, p.linkedin_url, p.about_me
                FROM users u
                LEFT JOIN user_profiles p ON u.id = p.user_id
                WHERE u.id = ?
            ");
            $stmtUser->execute([$targetUserId]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$userData) {
                sendJsonResponse('error', 'User not found.', [], 404);
            }

            // Fetch credits
            $stmtCredits = $db->prepare("SELECT * FROM user_email_credits WHERE user_id = ?");
            $stmtCredits->execute([$targetUserId]);
            $credits = $stmtCredits->fetch(PDO::FETCH_ASSOC) ?: [
                'remaining_credits' => 200,
                'free_credits' => 200,
                'purchased_credits' => 0,
                'total_credits' => 200,
                'used_credits' => 0
            ];

            // Fetch WhatsApp connection status
            $stmtWa = $db->prepare("SELECT id, phone_number_id, display_phone_number, is_active, created_at FROM whatsapp_accounts WHERE user_id = ? LIMIT 1");
            $stmtWa->execute([$targetUserId]);
            $waData = $stmtWa->fetch(PDO::FETCH_ASSOC);

            // Fetch active AI Keys
            $stmtAiKeys = $db->prepare("SELECT id, provider, status, created_at FROM user_ai_keys WHERE user_id = ?");
            $stmtAiKeys->execute([$targetUserId]);
            $aiKeys = $stmtAiKeys->fetchAll(PDO::FETCH_ASSOC);

            // Fetch SMTP config
            $stmtSmtp = $db->prepare("SELECT id, smtp_host, smtp_port, sender_email, created_at FROM smtp_settings WHERE user_id = ? LIMIT 1");
            $stmtSmtp->execute([$targetUserId]);
            $smtpData = $stmtSmtp->fetch(PDO::FETCH_ASSOC);

            // Fetch activity logs & credit transactions
            $stmtLogs = $db->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 25");
            $stmtLogs->execute([$targetUserId]);
            $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            $stmtTx = $db->prepare("SELECT * FROM email_credit_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 25");
            $stmtTx->execute([$targetUserId]);
            $transactions = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse('success', 'User profile inspector loaded.', [
                'user' => $userData,
                'credits' => $credits,
                'whatsapp' => $waData,
                'ai_keys' => $aiKeys,
                'smtp' => $smtpData,
                'logs' => $logs,
                'transactions' => $transactions
            ]);

        } else {
            // List users with Search & Filter
            $search = trim($_GET['search'] ?? '');
            $roleFilter = trim($_GET['role'] ?? '');
            $statusFilter = trim($_GET['status'] ?? '');
            
            $whereClauses = [];
            $params = [];

            if (!empty($search)) {
                $whereClauses[] = "(u.id = ? OR u.name LIKE ? OR u.email LIKE ?)";
                $params[] = is_numeric($search) ? (int)$search : 0;
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }

            if (!empty($roleFilter)) {
                $whereClauses[] = "u.role = ?";
                $params[] = $roleFilter;
            }

            if (!empty($statusFilter)) {
                $whereClauses[] = "COALESCE(u.status, 'active') = ?";
                $params[] = $statusFilter;
            }

            $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

            $sql = "
                SELECT u.id, u.name, u.email, u.phone_number, u.role, u.is_verified, COALESCE(u.status, 'active') as status, u.created_at, 
                       p.user_type, p.job_title, p.company_name,
                       COALESCE(c.remaining_credits, 200) as remaining_credits,
                       s.total_requests, s.emails_generated, s.emails_sent, s.whatsapp_generated, s.comments_generated
                FROM users u
                LEFT JOIN user_profiles p ON u.id = p.user_id
                LEFT JOIN user_email_credits c ON u.id = c.user_id
                LEFT JOIN user_statistics s ON u.id = s.user_id
                {$whereSql}
                ORDER BY u.id DESC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse('success', 'Users loaded.', ['users' => $users]);
        }
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            sendJsonResponse('error', 'Invalid JSON input', [], 400);
        }
        
        $action = trim($input['action'] ?? '');
        $userIds = $input['user_ids'] ?? [];
        $targetUserId = (int)($input['user_id'] ?? 0);
        if ($targetUserId > 0 && empty($userIds)) {
            $userIds = [$targetUserId];
        }

        if (empty($userIds)) {
            sendJsonResponse('error', 'No user IDs specified.', [], 400);
        }

        // Bulk Actions
        if ($action === 'bulk_grant_credits') {
            $amount = (int)($input['amount'] ?? 100);
            if ($amount <= 0) $amount = 100;

            foreach ($userIds as $uid) {
                $uid = (int)$uid;
                $db->prepare("
                    INSERT INTO user_email_credits (user_id, remaining_credits, free_credits, total_credits) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        remaining_credits = remaining_credits + VALUES(remaining_credits),
                        total_credits = total_credits + VALUES(total_credits)
                ")->execute([$uid, $amount, $amount, $amount]);

                $db->prepare("INSERT INTO email_credit_transactions (user_id, amount, credits, type, description, status) VALUES (?, 0, ?, 'recharge', 'Admin Bulk Credit Grant', 'success')")->execute([$uid, $amount]);
            }

            logActivity($adminId, "Granted {$amount} credits to users: " . implode(',', $userIds));
            sendJsonResponse('success', "Granted {$amount} credits to " . count($userIds) . " user(s).");

        } elseif ($action === 'bulk_reset_passwords') {
            $defaultPassword = password_hash("LinkPilot2026!", PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            foreach ($userIds as $uid) {
                $stmt->execute([$defaultPassword, (int)$uid]);
            }

            logActivity($adminId, "Reset passwords to default for users: " . implode(',', $userIds));
            sendJsonResponse('success', "Reset password to 'LinkPilot2026!' for " . count($userIds) . " user(s).");

        } elseif ($action === 'bulk_suspend' || $action === 'toggle_status') {
            $targetStatus = $action === 'toggle_status' ? trim($input['status'] ?? 'suspended') : 'suspended';
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND id != ?");
            $updated = 0;
            foreach ($userIds as $uid) {
                if ((int)$uid !== $adminId) {
                    $stmt->execute([$targetStatus, (int)$uid, $adminId]);
                    $updated++;
                }
            }

            logActivity($adminId, "Updated status to '{$targetStatus}' for users: " . implode(',', $userIds));
            sendJsonResponse('success', "Updated status to '{$targetStatus}' for {$updated} user(s).");

        } elseif ($action === 'toggle_role') {
            $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $currentRole = $stmt->fetchColumn();
            
            if (!$currentRole) {
                sendJsonResponse('error', 'User not found.', [], 404);
            }
            
            if ($currentRole !== 'admin') {
                sendJsonResponse('error', 'Promoting users to Admin privilege is disabled for platform security.', [], 403);
            }
            
            $stmtUpdate = $db->prepare("UPDATE users SET role = 'user' WHERE id = ?");
            $stmtUpdate->execute([$targetUserId]);
            
            logActivity($adminId, "Demoted admin user ID {$targetUserId} to regular user.");
            sendJsonResponse('success', "User role updated successfully to regular user.");

        } elseif ($action === 'delete') {
            $stmtDelete = $db->prepare("DELETE FROM users WHERE id = ? AND id != ?");
            foreach ($userIds as $uid) {
                $stmtDelete->execute([(int)$uid, $adminId]);
            }
            
            logActivity($adminId, "Deleted user accounts: " . implode(',', $userIds));
            sendJsonResponse('success', 'Selected user account(s) deleted successfully.');
        }
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'User management operation failed: ' . $e->getMessage(), [], 500);
}
