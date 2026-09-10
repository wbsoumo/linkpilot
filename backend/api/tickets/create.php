<?php
// backend/api/tickets/create.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];
$db = Database::getConnection();

// Ensure tickets and ticket_messages tables exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `support_tickets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `ticket_number` VARCHAR(30) NOT NULL UNIQUE,
        `subject` VARCHAR(255) NOT NULL,
        `category` VARCHAR(50) DEFAULT 'general',
        `priority` VARCHAR(20) DEFAULT 'medium',
        `status` VARCHAR(20) DEFAULT 'open',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `user_id_idx` (`user_id`),
        KEY `status_idx` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `support_ticket_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ticket_id` INT NOT NULL,
        `sender_type` ENUM('user', 'admin') NOT NULL,
        `sender_id` INT NOT NULL,
        `message` TEXT NOT NULL,
        `attachments_json` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY `ticket_id_idx` (`ticket_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $ex) {}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');
$category = trim($input['category'] ?? 'general');
$priority = trim($input['priority'] ?? 'medium');
$attachments = $input['attachments'] ?? [];

if (empty($subject) || empty($message)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Subject and message are required.']);
    exit;
}

// Generate unique ticket number (e.g. TCK-894210)
$ticketNumber = 'TCK-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

try {
    $stmt = $db->prepare("INSERT INTO support_tickets (user_id, ticket_number, subject, category, priority, status) VALUES (?, ?, ?, ?, ?, 'open')");
    $stmt->execute([$userId, $ticketNumber, $subject, $category, $priority]);
    $ticketId = $db->lastInsertId();

    $stmtMsg = $db->prepare("INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_id, message, attachments_json) VALUES (?, 'user', ?, ?, ?)");
    $stmtMsg->execute([$ticketId, $userId, $message, json_encode($attachments)]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Support ticket created successfully.',
        'ticket_id' => $ticketId,
        'ticket_number' => $ticketNumber
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to create support ticket: ' . $e->getMessage()]);
}
