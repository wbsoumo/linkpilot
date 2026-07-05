<?php
// backend/api/crm/email_intelligence/spam_rules.php

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($method === 'GET') {
        // Fetch all spam/promotional filter rules for the user
        $stmt = $db->prepare("SELECT * FROM spam_filters WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJsonResponse('success', 'Filter rules retrieved', ['rules' => $rules]);
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        if ($action === 'mark_spam_promo') {
            $emailId = (int)($input['email_id'] ?? 0);
            $category = trim($input['category'] ?? 'Spam'); // 'Spam' or 'Promotion'
            
            if (!$emailId) {
                sendJsonResponse('error', 'Missing email_id.', [], 400);
            }
            
            // 1. Fetch sender_email from received_emails
            $stmtEmail = $db->prepare("SELECT sender_email FROM received_emails WHERE id = ? AND user_id = ?");
            $stmtEmail->execute([$emailId, $userId]);
            $email = $stmtEmail->fetch();
            
            if (!$email) {
                sendJsonResponse('error', 'Email not found.', [], 404);
            }
            
            $senderEmail = strtolower(trim($email['sender_email']));
            
            $db->beginTransaction();
            
            // 2. Add rule to spam_filters
            $stmtInsertRule = $db->prepare("INSERT INTO spam_filters (user_id, filter_type, filter_value, category) VALUES (?, 'email', ?, ?) ON DUPLICATE KEY UPDATE category = VALUES(category)");
            $stmtInsertRule->execute([$userId, $senderEmail, $category]);
            
            // 3. Update all past emails from this sender to the selected category and mark as spam/processed
            $isSpam = ($category === 'Spam') ? 1 : 0;
            $stmtUpdateEmails = $db->prepare("UPDATE received_emails SET category = ?, is_spam = ?, spam_probability = ? WHERE sender_email = ? AND user_id = ?");
            $stmtUpdateEmails->execute([$category, $isSpam, $isSpam ? 100 : 80, $senderEmail, $userId]);
            
            $db->commit();
            
            sendJsonResponse('success', "Sender '$senderEmail' has been blacklisted, and all their emails marked as $category.");
            
        } elseif ($action === 'add') {
            $type = trim($input['filter_type'] ?? 'email'); // 'email' or 'domain'
            $value = strtolower(trim($input['filter_value'] ?? ''));
            $category = trim($input['category'] ?? 'Spam');
            
            if (empty($value)) {
                sendJsonResponse('error', 'Filter value is required.', [], 400);
            }
            
            $stmt = $db->prepare("INSERT INTO spam_filters (user_id, filter_type, filter_value, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE category = VALUES(category)");
            $stmt->execute([$userId, $type, $value, $category]);
            
            sendJsonResponse('success', 'Filter rule added successfully.');
            
        } else {
            sendJsonResponse('error', 'Invalid action.', [], 400);
        }
        
    } elseif ($method === 'DELETE') {
        $ruleId = (int)($_GET['id'] ?? 0);
        if (!$ruleId) {
            sendJsonResponse('error', 'Rule ID required.', [], 400);
        }
        
        $stmt = $db->prepare("DELETE FROM spam_filters WHERE id = ? AND user_id = ?");
        $stmt->execute([$ruleId, $userId]);
        
        sendJsonResponse('success', 'Filter rule deleted successfully.');
        
    } else {
        sendJsonResponse('error', 'Method not allowed.', [], 405);
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Spam filter service error: ' . $e->getMessage(), [], 500);
}
