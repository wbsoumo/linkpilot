<?php
// backend/api/google/sync.php

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
    $rowsSynced = GoogleSheetsHelper::bulkSyncLeads($userId);
    
    logActivity($userId, "Manually synchronized CRM leads to Google Sheets. Rows affected: " . $rowsSynced);
    
    sendJsonResponse('success', 'Sync completed successfully.', [
        'rows_synced' => $rowsSynced
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'Sync failed: ' . $e->getMessage(), [], 400);
}
