<?php
// backend/api/tickets/reply.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];
$userRole = $user['role'];
$isAdmin = ($userRole === 'admin');

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
$ticketId = (int)($input['ticket_id'] ?? 0);
$message = trim($input['message'] ?? '');
$attachments = $input['attachments'] ?? [];
$statusUpdate = trim($input['status_update'] ?? '');

if (!$ticketId || empty($message)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ticket ID and message body are required.']);
    exit;
}

try {
    // Check permission
    if ($isAdmin) {
        $stmtCheck = $db->prepare("SELECT id, status FROM support_tickets WHERE id = ?");
        $stmtCheck->execute([$ticketId]);
    } else {
        $stmtCheck = $db->prepare("SELECT id, status FROM support_tickets WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$ticketId, $userId]);
    }
    $ticket = $stmtCheck->fetch();

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Ticket not found or access denied.']);
        exit;
    }

    $senderType = $isAdmin ? 'admin' : 'user';

    $stmtMsg = $db->prepare("INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_id, message, attachments_json) VALUES (?, ?, ?, ?, ?)");
    $stmtMsg->execute([$ticketId, $senderType, $userId, $message, json_encode($attachments)]);

    // Update status
    $newStatus = $ticket['status'];
    if ($statusUpdate && in_array($statusUpdate, ['open', 'in_progress', 'closed'])) {
        $newStatus = $statusUpdate;
    } else if ($isAdmin && $ticket['status'] === 'open') {
        $newStatus = 'in_progress';
    } else if (!$isAdmin && $ticket['status'] === 'closed') {
        $newStatus = 'open';
    }

    $stmtUpd = $db->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmtUpd->execute([$newStatus, $ticketId]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Reply posted successfully.',
        'ticket_status' => $newStatus
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to post reply: ' . $e->getMessage()]);
}
