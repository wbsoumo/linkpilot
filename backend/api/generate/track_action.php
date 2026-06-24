<?php
// backend/api/generate/track_action.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$eventType = trim($input['event_type'] ?? '');
$details = trim($input['details'] ?? '');

if (empty($eventType)) {
    sendJsonResponse('error', 'Event type is required.', [], 400);
}

$allowedEvents = ['extension_opened', 'popup_opened', 'comment_inserted', 'whatsapp_opened', 'comment_copied', 'whatsapp_copied', 'email_copied'];
if (!in_array($eventType, $allowedEvents)) {
    sendJsonResponse('error', 'Invalid event type.', [], 400);
}

$db = Database::getConnection();

try {
    // 1. Insert into extension_events table
    $stmt = $db->prepare("INSERT INTO extension_events (user_id, event_type, details) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $eventType, empty($details) ? null : $details]);
    
    // 2. Update specific status in the details table if it matches
    // e.g. If whatsapp_opened, update status in whatsapp_generations
    if ($eventType === 'whatsapp_opened') {
        // Find latest whatsapp generation and mark as opened
        $db->exec("UPDATE whatsapp_generations SET status = 'opened' WHERE user_id = {$userId} ORDER BY id DESC LIMIT 1");
    } elseif ($eventType === 'comment_inserted') {
        // Find latest comment generation and mark as inserted
        $db->exec("UPDATE comment_generations SET status = 'inserted' WHERE user_id = {$userId} ORDER BY id DESC LIMIT 1");
    }
    
    logActivity($userId, "Extension action tracked: " . $eventType . " (" . $details . ")");
    
    sendJsonResponse('success', 'Event tracked successfully.');
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Error tracking event: ' . $e->getMessage(), [], 500);
}
