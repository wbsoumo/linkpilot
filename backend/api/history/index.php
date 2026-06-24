<?php
// backend/api/history/index.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

$db = Database::getConnection();

// Parameters
$tab = trim($_GET['tab'] ?? 'all'); // 'emails', 'whatsapp', 'comments', 'all'
$search = trim($_GET['search'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 10);
$offset = ($page - 1) * $limit;

try {
    $sql = "FROM ai_generations WHERE user_id = :user_id";
    $params = ['user_id' => $userId];
    
    // Tab Type Filter
    if ($tab === 'emails') {
        $sql .= " AND type = 'email'";
    } elseif ($tab === 'whatsapp') {
        $sql .= " AND type = 'whatsapp'";
    } elseif ($tab === 'comments') {
        $sql .= " AND type = 'comment'";
    }
    
    // Keyword Search
    if ($search !== '') {
        $sql .= " AND (post_content LIKE :search OR generated_content LIKE :search)";
        $params['search'] = "%{$search}%";
    }
    
    // Date Range Filters
    if ($startDate !== '') {
        $sql .= " AND created_at >= :start_date";
        $params['start_date'] = $startDate . ' 00:00:00';
    }
    
    if ($endDate !== '') {
        $sql .= " AND created_at <= :end_date";
        $params['end_date'] = $endDate . ' 23:59:59';
    }
    
    // 1. Get total count
    $countSql = "SELECT COUNT(*) as total " . $sql;
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute($params);
    $totalCount = (int)$stmtCount->fetch()['total'];
    
    // 2. Fetch history records
    $dataSql = "SELECT id, type, post_content, generated_content, tokens_used, created_at " . $sql . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmtData = $db->prepare($dataSql);
    
    // Bind parameters
    $stmtData->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($tab === 'emails') {
        // already hardcoded in SQL
    } elseif ($tab === 'whatsapp') {
        // already hardcoded in SQL
    } elseif ($tab === 'comments') {
        // already hardcoded in SQL
    }
    
    if ($search !== '') $stmtData->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
    if ($startDate !== '') $stmtData->bindValue(':start_date', $startDate . ' 00:00:00', PDO::PARAM_STR);
    if ($endDate !== '') $stmtData->bindValue(':end_date', $endDate . ' 23:59:59', PDO::PARAM_STR);
    $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmtData->execute();
    $rawHistory = $stmtData->fetchAll();
    
    // 3. Format history records with appropriate details and status
    $history = [];
    foreach ($rawHistory as $row) {
        $formatted = [
            'id' => $row['id'],
            'type' => $row['type'],
            'post_content' => $row['post_content'],
            'created_at' => $row['created_at'],
            'tokens_used' => $row['tokens_used'],
            'status' => 'Generated'
        ];
        
        // Parse generated details
        if ($row['type'] === 'email') {
            $emailJson = json_decode($row['generated_content'], true);
            $formatted['subject'] = $emailJson['subject'] ?? '';
            $formatted['body'] = $emailJson['body'] ?? $row['generated_content'];
            
            // Check status (if it was sent, we can look up sent_emails using user_id and approximate content or subject match)
            // But to make it simple and reliable, we can just say "Generated" or check if a sent email exists for this subject
            if (isset($emailJson['subject'])) {
                $stmtCheck = $db->prepare("SELECT status FROM sent_emails WHERE user_id = ? AND subject = ? ORDER BY id DESC LIMIT 1");
                $stmtCheck->execute([$userId, $emailJson['subject']]);
                $sentCheck = $stmtCheck->fetch();
                if ($sentCheck) {
                    $formatted['status'] = ucfirst($sentCheck['status']); // 'Sent' or 'Failed'
                }
            }
        } elseif ($row['type'] === 'whatsapp') {
            $formatted['body'] = $row['generated_content'];
            // Check status in whatsapp_generations
            $stmtCheck = $db->prepare("SELECT status, phone_number FROM whatsapp_generations WHERE user_id = ? AND message = ? ORDER BY id DESC LIMIT 1");
            $stmtCheck->execute([$userId, $row['generated_content']]);
            $waCheck = $stmtCheck->fetch();
            if ($waCheck) {
                $formatted['status'] = ucfirst($waCheck['status']); // 'Generated' or 'Opened'
                $formatted['phone_number'] = $waCheck['phone_number'];
            }
        } elseif ($row['type'] === 'comment') {
            $formatted['body'] = $row['generated_content'];
            // Check status in comment_generations
            $stmtCheck = $db->prepare("SELECT status FROM comment_generations WHERE user_id = ? AND comment = ? ORDER BY id DESC LIMIT 1");
            $stmtCheck->execute([$userId, $row['generated_content']]);
            $commCheck = $stmtCheck->fetch();
            if ($commCheck) {
                $formatted['status'] = ucfirst($commCheck['status']); // 'Generated' or 'Inserted'
            }
        }
        
        $history[] = $formatted;
    }
    
    sendJsonResponse('success', 'History loaded successfully.', [
        'history' => $history,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalCount / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Error loading history: ' . $e->getMessage(), [], 500);
}
