<?php
// backend/api/google/connect_sheet.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../google_sheets_helper.php';

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

$url = trim($input['spreadsheet_url'] ?? '');

if (empty($url)) {
    sendJsonResponse('error', 'Spreadsheet URL is required.', [], 400);
}

// Extract Spreadsheet ID from URL
// Match standard formats like: https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit#gid=0
$spreadsheetId = '';
if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
    $spreadsheetId = $matches[1];
} else {
    // If not a full URL, check if they passed the raw ID directly
    if (preg_match('/^[a-zA-Z0-9-_]+$/', $url)) {
        $spreadsheetId = $url;
    }
}

if (empty($spreadsheetId)) {
    sendJsonResponse('error', 'Invalid Google Spreadsheet URL or ID format.', [], 400);
}

try {
    $res = GoogleSheetsHelper::connectExistingSpreadsheet($userId, $spreadsheetId);
    
    logActivity($userId, "Linked existing Google Spreadsheet ID: " . $spreadsheetId);
    
    sendJsonResponse('success', 'Spreadsheet connected successfully!', [
        'spreadsheet_id' => $res['spreadsheet_id'],
        'spreadsheet_url' => $res['spreadsheet_url']
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', $e->getMessage(), [], 400);
}
