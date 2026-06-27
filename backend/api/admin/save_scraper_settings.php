<?php
// backend/api/admin/save_scraper_settings.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Require Admin Privilege
$user = JWTHelper::requireAdmin();

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$isEnabled = isset($input['is_enabled']) ? (int)$input['is_enabled'] : 0;
$apiUrl = isset($input['api_url']) ? trim($input['api_url']) : 'http://localhost:8000';
$timeout = isset($input['timeout']) ? (int)$input['timeout'] : 15;
$debugLogging = isset($input['debug_logging']) ? (int)$input['debug_logging'] : 0;

if (empty($apiUrl)) {
    sendJsonResponse('error', 'API URL is required.', [], 400);
}

$db = Database::getConnection();

try {
    $stmt = $db->prepare("UPDATE email_provider_settings SET is_enabled = ?, api_key = ?, api_secret = ?, priority = ? WHERE provider_name = ?");
    $stmt->execute([$isEnabled, $apiUrl, $timeout, $debugLogging, 'linkedin_scraper']);

    logActivity($user['id'], "Updated LinkedIn Scraper configurations.");
    sendJsonResponse('success', 'LinkedIn Scraper settings saved successfully.');

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to save scraper settings: ' . $e->getMessage(), [], 500);
}
