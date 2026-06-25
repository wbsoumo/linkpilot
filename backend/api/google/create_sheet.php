<?php
// backend/api/google/create_sheet.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../google_sheets_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

try {
    $res = GoogleSheetsHelper::createSpreadsheet($userId);
    
    logActivity($userId, "Created new Google Spreadsheet: LinkPilot CRM Leads");
    
    sendJsonResponse('success', 'New spreadsheet created successfully!', [
        'spreadsheet_id' => $res['spreadsheet_id'],
        'spreadsheet_url' => $res['spreadsheet_url']
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to create spreadsheet: ' . $e->getMessage(), [], 500);
}
