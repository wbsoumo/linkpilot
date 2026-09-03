<?php
// backend/api/waitlist.php

require_once __DIR__ . '/../config.php';

// Enable CORS
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

function getClientIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$db = Database::getConnection();

// Self-healing migration for waitlist table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `waitlist` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `industry` VARCHAR(100) NOT NULL,
        `pricing_preference` VARCHAR(100) NOT NULL,
        `ip_address` VARCHAR(50) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_waitlist_ip` (`ip_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Exception $e) {
    // Capture migration errors silently
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$clientIp = getClientIP();

try {
    if ($method === 'GET') {
        // Check if current IP has already submitted the waitlist form
        $stmt = $db->prepare("SELECT COUNT(*) FROM `waitlist` WHERE `ip_address` = ?");
        $stmt->execute([$clientIp]);
        $hasSubmitted = ((int)$stmt->fetchColumn() > 0);

        echo json_encode([
            'status' => 'success',
            'ip' => $clientIp,
            'submitted' => $hasSubmitted,
            'show_popup' => !$hasSubmitted
        ]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $name = trim($input['name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $email = trim($input['email'] ?? '');
        $industry = trim($input['industry'] ?? '');
        $pricingPreference = trim($input['pricing_preference'] ?? '');

        if (empty($name) || empty($phone) || empty($email) || empty($industry) || empty($pricingPreference)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'All fields (Name, Phone, Email, Industry, and Payment Preference) are required.'
            ]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Please enter a valid email address.'
            ]);
            exit;
        }

        // Check if IP already submitted
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM `waitlist` WHERE `ip_address` = ?");
        $stmtCheck->execute([$clientIp]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'You have already joined the LinkPilot Ecosystem waiting list from this IP.',
                'already_submitted' => true
            ]);
            exit;
        }

        // Insert new waitlist entry
        $stmtIns = $db->prepare("INSERT INTO `waitlist` (name, phone, email, industry, pricing_preference, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([$name, $phone, $email, $industry, $pricingPreference, $clientIp]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Thank you for joining the LinkPilot Ecosystem Waitlist! We will notify you as soon as your access is ready.'
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
