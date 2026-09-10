<?php
// backend/api/tickets/list.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];
$isAdmin = ($user['role'] === 'admin');

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

$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    if ($ticketId) {
        // Single ticket details + messages
        if ($isAdmin) {
            $stmt = $db->prepare("SELECT t.*, u.name as user_name, u.email as user_email FROM support_tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ?");
            $stmt->execute([$ticketId]);
        } else {
            $stmt = $db->prepare("SELECT t.*, u.name as user_name, u.email as user_email FROM support_tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.user_id = ?");
            $stmt->execute([$ticketId, $userId]);
        }
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Ticket not found']);
            exit;
        }

        $stmtMsgs = $db->prepare("SELECT m.*, u.name as sender_name FROM support_ticket_messages m LEFT JOIN users u ON m.sender_id = u.id WHERE m.ticket_id = ? ORDER BY m.created_at ASC");
        $stmtMsgs->execute([$ticketId]);
        $messages = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'ticket' => $ticket,
            'messages' => $messages
        ]);
        exit;
    }

    // List tickets
    if ($isAdmin && isset($_GET['admin_view']) && $_GET['admin_view'] === '1') {
        $stmt = $db->prepare("SELECT t.*, u.name as user_name, u.email as user_email, (SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id = t.id) as message_count FROM support_tickets t LEFT JOIN users u ON t.user_id = u.id ORDER BY FIELD(t.status, 'open', 'in_progress', 'closed'), t.updated_at DESC");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT t.*, u.name as user_name, u.email as user_email, (SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id = t.id) as message_count FROM support_tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.user_id = ? ORDER BY t.updated_at DESC");
        $stmt->execute([$userId]);
    }
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'tickets' => $tickets
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch tickets: ' . $e->getMessage()]);
}
