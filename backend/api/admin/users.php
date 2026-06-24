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
    if ($method === 'GET') {
        // List users
        $stmt = $db->query("
            SELECT u.id, u.name, u.email, u.role, u.created_at, 
                   p.user_type, p.job_title, p.company_name,
                   s.total_requests, s.emails_generated, s.emails_sent, s.whatsapp_generated, s.comments_generated
            FROM users u
            LEFT JOIN user_profiles p ON u.id = p.user_id
            LEFT JOIN user_statistics s ON u.id = s.user_id
            ORDER BY u.id DESC
        ");
        $users = $stmt->fetchAll();
        sendJsonResponse('success', 'Users loaded.', ['users' => $users]);
        
    } elseif ($method === 'POST') {
        // Parse input
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            sendJsonResponse('error', 'Invalid JSON input', [], 400);
        }
        
        $action = trim($input['action'] ?? '');
        $targetUserId = (int)($input['user_id'] ?? 0);
        
        if ($targetUserId <= 0) {
            sendJsonResponse('error', 'Invalid Target User ID.', [], 400);
        }
        
        // Prevent action on self (cannot demote/delete yourself)
        if ($targetUserId === $adminId) {
            sendJsonResponse('error', 'You cannot perform admin actions on your own account.', [], 400);
        }
        
        if ($action === 'toggle_role') {
            // Check current role
            $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $currentRole = $stmt->fetchColumn();
            
            if (!$currentRole) {
                sendJsonResponse('error', 'User not found.', [], 404);
            }
            
            $newRole = ($currentRole === 'admin') ? 'user' : 'admin';
            $stmtUpdate = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmtUpdate->execute([$newRole, $targetUserId]);
            
            logActivity($adminId, "Toggled role for user ID {$targetUserId} to: {$newRole}");
            sendJsonResponse('success', "User role updated successfully to {$newRole}.");
            
        } elseif ($action === 'delete') {
            // Delete user (cascade will clean up user_profiles, stats, logs, etc.)
            $stmtDelete = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmtDelete->execute([$targetUserId]);
            
            logActivity($adminId, "Deleted user ID {$targetUserId}");
            sendJsonResponse('success', 'User account deleted successfully.');
            
        } else {
            sendJsonResponse('error', 'Unsupported action.', [], 400);
        }
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'User management operation failed: ' . $e->getMessage(), [], 500);
}
