<?php
// scratch/test_emails_api.php

$_GET['token'] = 'dummy'; // bypass token check if we hardcode user

require_once __DIR__ . '/../backend/config.php';
$db = Database::getConnection();

// Mock Auth User ID 1
$userId = 1;

// Call emails.php logic
$search = '';
$category = '';
$priority = '';
$sentiment = '';
$isSpam = 0;
$isRead = null;
$isStarred = null;
$isArchived = 0;
$page = 1;
$limit = 20;
$offset = 0;

$query = "FROM received_emails WHERE user_id = :user_id AND is_spam = :is_spam AND is_archived = :is_archived AND parent_id IS NULL";
$params = [
    'user_id' => $userId,
    'is_spam' => $isSpam,
    'is_archived' => $isArchived
];

$countStmt = $db->prepare("SELECT COUNT(*) " . $query);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();

$dataStmt = $db->prepare("SELECT id, sender_name, sender_email, subject, category, received_date, is_read, is_starred, is_archived, priority, sentiment, spam_probability, ai_summary " . $query . " ORDER BY received_date DESC LIMIT :limit OFFSET :offset");
$dataStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$dataStmt->bindValue(':is_spam', $isSpam, PDO::PARAM_INT);
$dataStmt->bindValue(':is_archived', $isArchived, PDO::PARAM_INT);
$dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();

$emails = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

echo "TOTAL COUNT: $totalCount\n";
echo "EMAILS RETURNED BY API (LIMIT $limit):\n";
foreach ($emails as $idx => $m) {
    echo "[" . ($idx + 1) . "] ID: {$m['id']} | Subject: {$m['subject']} | Date: {$m['received_date']} | Category: {$m['category']} | Spam: {$m['is_spam']} | Archived: {$m['is_archived']}\n";
}
