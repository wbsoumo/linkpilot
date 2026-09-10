<?php
// backend/api/tickets/list.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];
$isAdmin = ($user['role'] === 'admin');

$db = Database::getConnection();

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
