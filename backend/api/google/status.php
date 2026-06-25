<?php
// backend/api/google/status.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../google_sheets_helper.php';

// Trigger database check to ensure table exists
GoogleSheetsHelper::checkDatabaseSchema();

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT * FROM google_sheet_connections WHERE user_id = ?");
    $stmt->execute([$userId]);
    $conn = $stmt->fetch();
    
    if (!$conn) {
        sendJsonResponse('success', 'No Google Sheets connection found.', ['connected' => false]);
    }
    
    // Count local leads for estimate
    $stmtCount = $db->prepare("SELECT COUNT(*) as cnt FROM lead_vault WHERE user_id = ?");
    $stmtCount->execute([$userId]);
    $localLeadsCount = (int)$stmtCount->fetch()['cnt'];
    
    $rowsSynced = $localLeadsCount;
    $sheetName = 'LinkPilot CRM Leads';
    
    // Try to get dynamic row count from Google Sheets if connected
    if (!empty($conn['spreadsheet_id'])) {
        $token = GoogleSheetsHelper::getAccessToken($userId);
        if ($token) {
            $spreadsheetId = $conn['spreadsheet_id'];
            $ch = curl_init("https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/Sheet1!A:A");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($res, true);
            if (isset($data['values'])) {
                // Number of rows minus header
                $rowsSynced = max(0, count($data['values']) - 1);
            }
        }
    }
    
    sendJsonResponse('success', 'Google Sheets connection status loaded.', [
        'connected' => true,
        'google_email' => $conn['google_email'],
        'google_name' => $conn['google_name'],
        'spreadsheet_id' => $conn['spreadsheet_id'],
        'spreadsheet_url' => $conn['spreadsheet_url'],
        'sheet_name' => $sheetName,
        'sync_enabled' => (bool)$conn['sync_enabled'],
        'sync_new_leads' => (bool)$conn['sync_new_leads'],
        'sync_update_leads' => (bool)$conn['sync_update_leads'],
        'sync_status' => (bool)$conn['sync_status'],
        'sync_notes' => (bool)$conn['sync_notes'],
        'sync_followups' => (bool)$conn['sync_followups'],
        'sync_lead_score' => (bool)$conn['sync_lead_score'],
        'last_sync_time' => $conn['updated_at'],
        'rows_synced' => $rowsSynced
    ]);
    
} catch (Exception $e) {
    sendJsonResponse('error', 'Error loading connection status: ' . $e->getMessage(), [], 500);
}
