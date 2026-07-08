<?php
// backend/api/crm/email_intelligence/emails.php

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../wallet_helper.php';
require_once __DIR__ . '/../../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            // Get single email details
            $emailId = (int)$_GET['id'];
            
            $stmt = $db->prepare("SELECT * FROM received_emails WHERE id = ? AND user_id = ?");
            $stmt->execute([$emailId, $userId]);
            $email = $stmt->fetch();
            
            if (!$email) {
                sendJsonResponse('error', 'Email not found', [], 404);
            }
            
            // Mark as read automatically when opened
            if (!$email['is_read']) {
                $db->prepare("UPDATE received_emails SET is_read = 1 WHERE id = ?")->execute([$emailId]);
                $email['is_read'] = 1;
            }
            
            // Fetch attachments
            $stmtAtt = $db->prepare("SELECT id, filename, file_path, file_size, file_type FROM email_attachments WHERE received_email_id = ?");
            $stmtAtt->execute([$emailId]);
            $email['attachments'] = $stmtAtt->fetchAll();
            
            // Fetch replies thread
            $stmtReplies = $db->prepare("SELECT * FROM received_emails WHERE parent_id = ? AND user_id = ? ORDER BY received_date ASC");
            $stmtReplies->execute([$emailId, $userId]);
            $email['replies'] = $stmtReplies->fetchAll();
            
            sendJsonResponse('success', 'Email retrieved successfully', ['email' => $email]);
            
        } else {
            // Query inbox listing
            $search = trim($_GET['search'] ?? '');
            $category = trim($_GET['category'] ?? '');
            $priority = trim($_GET['priority'] ?? '');
            $sentiment = trim($_GET['sentiment'] ?? '');
            
            $isSpam = isset($_GET['is_spam']) ? (int)$_GET['is_spam'] : 0;
            $isRead = isset($_GET['is_read']) ? (int)$_GET['is_read'] : null;
            $isStarred = isset($_GET['is_starred']) ? (int)$_GET['is_starred'] : null;
            $isArchived = isset($_GET['is_archived']) ? (int)$_GET['is_archived'] : 0; // hide archived by default
            
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 20));
            $offset = ($page - 1) * $limit;
            
            $query = "FROM received_emails WHERE user_id = :user_id AND is_spam = :is_spam AND is_archived = :is_archived AND parent_id IS NULL";
            $params = [
                'user_id' => $userId,
                'is_spam' => $isSpam,
                'is_archived' => $isArchived
            ];
            
            if ($search !== '') {
                $query .= " AND (sender_name LIKE :search1 OR sender_email LIKE :search2 OR subject LIKE :search3 OR body_text LIKE :search4)";
                $params['search1'] = '%' . $search . '%';
                $params['search2'] = '%' . $search . '%';
                $params['search3'] = '%' . $search . '%';
                $params['search4'] = '%' . $search . '%';
            }
            if ($category !== '') {
                $query .= " AND category = :category";
                $params['category'] = $category;
            }
            if ($priority !== '') {
                $query .= " AND priority = :priority";
                $params['priority'] = $priority;
            }
            if ($sentiment !== '') {
                $query .= " AND sentiment = :sentiment";
                $params['sentiment'] = $sentiment;
            }
            if ($isRead !== null) {
                $query .= " AND is_read = :is_read";
                $params['is_read'] = $isRead;
            }
            if ($isStarred !== null) {
                $query .= " AND is_starred = :is_starred";
                $params['is_starred'] = $isStarred;
            }
            
            // Get Total Count
            $countStmt = $db->prepare("SELECT COUNT(*) " . $query);
            $countStmt->execute($params);
            $totalCount = (int)$countStmt->fetchColumn();
            
            // Fetch records
            $dataStmt = $db->prepare("SELECT id, sender_name, sender_email, subject, category, received_date, is_read, is_starred, is_archived, priority, sentiment, spam_probability, ai_summary " . $query . " ORDER BY received_date DESC LIMIT :limit OFFSET :offset");
            
            $dataStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $dataStmt->bindValue(':is_spam', $isSpam, PDO::PARAM_INT);
            $dataStmt->bindValue(':is_archived', $isArchived, PDO::PARAM_INT);
            if ($search !== '') {
                $dataStmt->bindValue(':search1', '%' . $search . '%', PDO::PARAM_STR);
                $dataStmt->bindValue(':search2', '%' . $search . '%', PDO::PARAM_STR);
                $dataStmt->bindValue(':search3', '%' . $search . '%', PDO::PARAM_STR);
                $dataStmt->bindValue(':search4', '%' . $search . '%', PDO::PARAM_STR);
            }
            if ($category !== '') $dataStmt->bindValue(':category', $category, PDO::PARAM_STR);
            if ($priority !== '') $dataStmt->bindValue(':priority', $priority, PDO::PARAM_STR);
            if ($sentiment !== '') $dataStmt->bindValue(':sentiment', $sentiment, PDO::PARAM_STR);
            if ($isRead !== null) $dataStmt->bindValue(':is_read', $isRead, PDO::PARAM_INT);
            if ($isStarred !== null) $dataStmt->bindValue(':is_starred', $isStarred, PDO::PARAM_INT);
            $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $dataStmt->execute();
            
            $emails = $dataStmt->fetchAll();
            
            // Get unread counts
            $unreadCountStmt = $db->prepare("SELECT COUNT(*) FROM received_emails WHERE user_id = ? AND is_read = 0 AND is_spam = 0 AND is_archived = 0");
            $unreadCountStmt->execute([$userId]);
            $unreadCount = (int)$unreadCountStmt->fetchColumn();
            
            sendJsonResponse('success', 'Emails list retrieved', [
                'emails' => $emails,
                'total' => $totalCount,
                'unread_count' => $unreadCount,
                'page' => $page,
                'limit' => $limit
            ]);
        }
    } 
    
    elseif ($method === 'POST' || $method === 'PUT') {
        // Toggle read/starred/archived states
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $emailId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($emailId <= 0) {
            sendJsonResponse('error', 'Email ID is required.', [], 400);
        }
        
        // Check ownership
        $stmtCheck = $db->prepare("SELECT id FROM received_emails WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$emailId, $userId]);
        if (!$stmtCheck->fetch()) {
            sendJsonResponse('error', 'Email not found or access denied.', [], 404);
        }
        
        if ($action === 'mark_read') {
            $state = isset($input['is_read']) ? (int)$input['is_read'] : 1;
            $db->prepare("UPDATE received_emails SET is_read = ? WHERE id = ?")->execute([$state, $emailId]);
            sendJsonResponse('success', $state ? 'Email marked as read.' : 'Email marked as unread.');
        } 
        
        elseif ($action === 'star') {
            $state = isset($input['is_starred']) ? (int)$input['is_starred'] : 1;
            $db->prepare("UPDATE received_emails SET is_starred = ? WHERE id = ?")->execute([$state, $emailId]);
            sendJsonResponse('success', $state ? 'Email starred.' : 'Email unstarred.');
        } 
        
        elseif ($action === 'archive') {
            $state = isset($input['is_archived']) ? (int)$input['is_archived'] : 1;
            $db->prepare("UPDATE received_emails SET is_archived = ? WHERE id = ?")->execute([$state, $emailId]);
            sendJsonResponse('success', $state ? 'Email archived.' : 'Email moved to inbox.');
        } 
        
        elseif ($action === 'delete') {
            // Delete record
            $db->prepare("DELETE FROM received_emails WHERE id = ? AND user_id = ?")->execute([$emailId, $userId]);
            sendJsonResponse('success', 'Email deleted permanently.');
        } 
        
        elseif ($action === 'generate_reply') {
            // Fetch email details
            $stmtEmail = $db->prepare("SELECT * FROM received_emails WHERE id = ? AND user_id = ?");
            $stmtEmail->execute([$emailId, $userId]);
            $email = $stmtEmail->fetch();
            
            if (!$email) {
                sendJsonResponse('error', 'Email not found.', [], 404);
            }
            
            $tone = trim($input['tone'] ?? 'Professional');
            
            // Get business settings for context
            $stmtSettings = $db->prepare("SELECT business_type, industry FROM email_intelligence_settings WHERE user_id = ?");
            $stmtSettings->execute([$userId]);
            $settings = $stmtSettings->fetch();
            $businessType = $settings['business_type'] ?? 'Software Company';
            $industry = $settings['industry'] ?? 'Technology';

            // Generate reply using AI
            $systemPrompt = "You are an AI Email Assistant. Your job is to draft a clean, professional, and context-aware email reply.
User's Business Profile: Type: '$businessType', Industry: '$industry'.
Tone required: '$tone'.
Do NOT include any greetings like 'Subject:' or subject lines. Just output the body of the reply email. Keep it concise.";
            
            $bodyText = $email['body_text'] ?: strip_tags($email['body_html']);
            $userPrompt = "Email details:\nSender Name: {$email['sender_name']}\nSender Email: {$email['sender_email']}\nSubject: {$email['subject']}\n\nEmail Content:\n$bodyText";
            
            require_once __DIR__ . '/../../../smtp_helper.php'; // holds callAI
            $ai = callAI($systemPrompt, $userPrompt, $userId);
            $replyText = trim($ai['text']);
            
            // Save reply to database
            $db->prepare("UPDATE received_emails SET ai_suggested_reply = ? WHERE id = ?")->execute([$replyText, $emailId]);
            
            sendJsonResponse('success', 'AI reply generated successfully.', ['reply' => $replyText]);
        } 
        
        else {
            sendJsonResponse('error', 'Invalid action query parameter.', [], 400);
        }
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage() . ' | Query: ' . ($query ?? 'none') . ' | Params: ' . json_encode($params ?? []), [], 500);
}
