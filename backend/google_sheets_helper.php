<?php
// backend/google_sheets_helper.php

require_once __DIR__ . '/config.php';

class GoogleSheetsHelper {

    /**
     * Self-healing database check. Automatically creates connection table and adds missing columns.
     */
    public static function checkDatabaseSchema() {
        $db = Database::getConnection();
        try {
            // 1. Create google_sheet_connections table
            $stmt = $db->query("SHOW TABLES LIKE 'google_sheet_connections'");
            if ($stmt->rowCount() === 0) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `google_sheet_connections` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT UNIQUE NOT NULL,
                        `google_email` VARCHAR(255) DEFAULT NULL,
                        `google_name` VARCHAR(255) DEFAULT NULL,
                        `access_token` TEXT DEFAULT NULL,
                        `refresh_token` TEXT DEFAULT NULL,
                        `expires_at` TIMESTAMP NULL DEFAULT NULL,
                        `spreadsheet_id` VARCHAR(255) DEFAULT NULL,
                        `spreadsheet_url` VARCHAR(500) DEFAULT NULL,
                        `sync_enabled` TINYINT DEFAULT 1,
                        `sync_new_leads` TINYINT DEFAULT 1,
                        `sync_update_leads` TINYINT DEFAULT 1,
                        `sync_status` TINYINT DEFAULT 1,
                        `sync_notes` TINYINT DEFAULT 1,
                        `sync_followups` TINYINT DEFAULT 1,
                        `sync_lead_score` TINYINT DEFAULT 1,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        CONSTRAINT `fk_google_sheet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB;
                ");
            }

            // 2. Append missing CRM fields to lead_vault
            $alterQueries = [
                "ALTER TABLE `lead_vault` ADD COLUMN `current_status` VARCHAR(100) DEFAULT 'New'",
                "ALTER TABLE `lead_vault` ADD COLUMN `current_stage` VARCHAR(100) DEFAULT 'Lead'",
                "ALTER TABLE `lead_vault` ADD COLUMN `remarks` TEXT DEFAULT NULL",
                "ALTER TABLE `lead_vault` ADD COLUMN `lead_score` INT DEFAULT NULL",
                "ALTER TABLE `lead_vault` ADD COLUMN `last_contact_date` TIMESTAMP NULL DEFAULT NULL",
                "ALTER TABLE `lead_vault` ADD COLUMN `next_followup_date` TIMESTAMP NULL DEFAULT NULL",
                "ALTER TABLE `lead_vault` ADD COLUMN `post_summary` TEXT DEFAULT NULL",
                "ALTER TABLE `lead_vault` ADD COLUMN `generated_email` TEXT DEFAULT NULL",
                "ALTER TABLE `lead_vault` ADD COLUMN `generated_whatsapp` TEXT DEFAULT NULL",
                "ALTER TABLE `lead_vault` ADD COLUMN `generated_comment` TEXT DEFAULT NULL",
                "ALTER TABLE `lead_vault` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
            ];

            foreach ($alterQueries as $q) {
                try {
                    $db->exec($q);
                } catch (Exception $e) {
                    // Ignore column duplicate errors
                }
            }
        } catch (Exception $e) {
            error_log("GoogleSheetsHelper Database Setup Error: " . $e->getMessage());
        }
    }

    /**
     * Helper to make HTTP cURL requests.
     */
    private static function makeCurlRequest($url, $method = 'GET', $body = null, $headers = []) {
        $ch = curl_init($url);
        
        $curlHeaders = [];
        foreach ($headers as $key => $val) {
            $curlHeaders[] = "$key: $val";
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => $response,
            'data' => json_decode($response, true)
        ];
    }

    /**
     * Fetches, decrypts, and verifies access tokens. Refreshes automatically if expired.
     */
    public static function getAccessToken($userId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM google_sheet_connections WHERE user_id = ?");
        $stmt->execute([$userId]);
        $conn = $stmt->fetch();

        if (!$conn) {
            return false;
        }

        $expiresAt = strtotime($conn['expires_at']);
        $accessToken = decryptData($conn['access_token']);

        // Check if token is expired or expires in the next 60 seconds
        if ($expiresAt <= (time() + 60)) {
            $refreshToken = decryptData($conn['refresh_token']);
            if (!$refreshToken) {
                return false;
            }

            // Refresh Token Request
            $url = 'https://oauth2.googleapis.com/token';
            $creds = self::getClientCredentials();
            $payload = [
                'client_id' => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token'
            ];

            $res = self::makeCurlRequest($url, 'POST', $payload, [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]);

            if ($res['code'] === 200 && isset($res['data']['access_token'])) {
                $newAccessToken = $res['data']['access_token'];
                $newExpiresIn = $res['data']['expires_in'];
                $newExpiresAt = date('Y-m-d H:i:s', time() + $newExpiresIn);

                $encryptedAccess = encryptData($newAccessToken);
                
                // Update database
                $stmtUpdate = $db->prepare("UPDATE google_sheet_connections SET access_token = ?, expires_at = ? WHERE user_id = ?");
                $stmtUpdate->execute([$encryptedAccess, $newExpiresAt, $userId]);

                return $newAccessToken;
            } else {
                error_log("Failed to refresh Google OAuth token: " . $res['body']);
                return false;
            }
        }

        return $accessToken;
    }

    /**
     * Create a new Google Spreadsheet and initialize headers.
     */
    public static function createSpreadsheet($userId) {
        $token = self::getAccessToken($userId);
        if (!$token) {
            throw new Exception("Google account not connected or session expired.");
        }

        // 1. Create Spreadsheet
        $url = 'https://sheets.googleapis.com/v4/spreadsheets';
        $body = json_encode([
            'properties' => [
                'title' => 'LinkPilot CRM Leads'
            ]
        ]);

        $res = self::makeCurlRequest($url, 'POST', $body, [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ]);

        if ($res['code'] !== 200 || !isset($res['data']['spreadsheetId'])) {
            throw new Exception("Google Sheets API spreadsheet creation failed: " . ($res['data']['error']['message'] ?? $res['body']));
        }

        $spreadsheetId = $res['data']['spreadsheetId'];
        $spreadsheetUrl = $res['data']['spreadsheetUrl'] ?? "https://docs.google.com/spreadsheets/d/$spreadsheetId/edit";

        // 2. Initialize Columns Header Row
        self::initializeHeaders($token, $spreadsheetId);

        // 3. Save to Connection table
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE google_sheet_connections SET spreadsheet_id = ?, spreadsheet_url = ? WHERE user_id = ?");
        $stmt->execute([$spreadsheetId, $spreadsheetUrl, $userId]);

        return [
            'spreadsheet_id' => $spreadsheetId,
            'spreadsheet_url' => $spreadsheetUrl
        ];
    }

    /**
     * Set up headers in a spreadsheet.
     */
    private static function initializeHeaders($token, $spreadsheetId) {
        $headers = [
            'Lead ID', 'Lead Name', 'Company', 'LinkedIn Profile', 'Email', 
            'Phone Number', 'Source', 'Generated Email', 'Generated WhatsApp', 
            'Generated Comment', 'Lead Status', 'Lead Score', 'Notes', 
            'Created At', 'Last Updated'
        ];

        $url = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/Sheet1!A1:O1?valueInputOption=USER_ENTERED";
        $body = json_encode([
            'range' => 'Sheet1!A1:O1',
            'majorDimension' => 'ROWS',
            'values' => [$headers]
        ]);

        $res = self::makeCurlRequest($url, 'PUT', $body, [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ]);

        if ($res['code'] !== 200) {
            error_log("Failed to initialize Google Sheet headers: " . $res['body']);
        }
    }

    /**
     * Validates and links an existing spreadsheet ID.
     */
    public static function connectExistingSpreadsheet($userId, $spreadsheetId) {
        $token = self::getAccessToken($userId);
        if (!$token) {
            throw new Exception("Google account not connected.");
        }

        // Try reading spreadsheet headers to validate existence and access permissions
        $url = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/Sheet1!A1:A1";
        $res = self::makeCurlRequest($url, 'GET', null, [
            'Authorization' => "Bearer $token"
        ]);

        if ($res['code'] !== 200) {
            throw new Exception("Unable to access spreadsheet. Please make sure the URL is correct and you have permission to edit it.");
        }

        $spreadsheetUrl = "https://docs.google.com/spreadsheets/d/$spreadsheetId/edit";

        // Initialize sheet headers if it is currently blank
        $data = $res['data'];
        if (empty($data['values'])) {
            self::initializeHeaders($token, $spreadsheetId);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE google_sheet_connections SET spreadsheet_id = ?, spreadsheet_url = ? WHERE user_id = ?");
        $stmt->execute([$spreadsheetId, $spreadsheetUrl, $userId]);

        return [
            'spreadsheet_id' => $spreadsheetId,
            'spreadsheet_url' => $spreadsheetUrl
        ];
    }

    /**
     * Core Lead Row Synchronization.
     */
    public static function syncLead($userId, $leadId) {
        $db = Database::getConnection();

        // 1. Fetch connection details
        $stmtConn = $db->prepare("SELECT * FROM google_sheet_connections WHERE user_id = ?");
        $stmtConn->execute([$userId]);
        $conn = $stmtConn->fetch();

        if (!$conn || !$conn['sync_enabled'] || empty($conn['spreadsheet_id'])) {
            return false; // Sync disabled or connection not set up
        }

        $spreadsheetId = $conn['spreadsheet_id'];
        $token = self::getAccessToken($userId);
        if (!$token) {
            return false; // Authentication issues
        }

        // 2. Fetch Lead details
        $stmtLead = $db->prepare("SELECT * FROM lead_vault WHERE id = ? AND user_id = ?");
        $stmtLead->execute([$leadId, $userId]);
        $lead = $stmtLead->fetch();

        if (!$lead) {
            return false; // Lead does not exist
        }

        // 3. Search for Lead ID in Column A of the spreadsheet to locate existing row
        $urlRead = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/Sheet1!A:A";
        $resRead = self::makeCurlRequest($urlRead, 'GET', null, [
            'Authorization' => "Bearer $token"
        ]);

        $rowNumber = -1;
        if ($resRead['code'] === 200 && isset($resRead['data']['values'])) {
            $rows = $resRead['data']['values'];
            foreach ($rows as $index => $row) {
                if (isset($row[0]) && (string)$row[0] === (string)$leadId) {
                    $rowNumber = $index + 1; // 1-indexed row number
                    break;
                }
            }
        }

        // Respect Toggles & Map Fields
        $isExisting = ($rowNumber !== -1);
        if ($isExisting && !$conn['sync_update_leads']) {
            return false; // Sync updates is disabled
        }
        if (!$isExisting && !$conn['sync_new_leads']) {
            return false; // Sync new leads is disabled
        }

        // Build data structure aligned with settings
        $leadName = $lead['name'];
        $companyName = $lead['company_name'];
        $linkedinUrl = $lead['linkedin_url'];
        $email = $lead['email'];
        $phone = $lead['phone_number'];
        $source = $lead['source'];

        $genEmail = $lead['generated_email'];
        $genWhatsApp = $lead['generated_whatsapp'];
        $genComment = $lead['generated_comment'];

        $status = ($conn['sync_status']) ? $lead['current_status'] : 'N/A';
        $score = ($conn['sync_lead_score']) ? $lead['lead_score'] : '';
        $notes = ($conn['sync_notes']) ? $lead['remarks'] : '';
        
        $createdAt = $lead['created_at'];
        $updatedAt = $lead['updated_at'] ?? $lead['created_at'];

        // If updating an existing row, we read the current row details first to avoid overwriting toggled-off fields
        if ($isExisting) {
            $urlGetRow = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/Sheet1!A$rowNumber:O$rowNumber";
            $resGetRow = self::makeCurlRequest($urlGetRow, 'GET', null, [
                'Authorization' => "Bearer $token"
            ]);
            if ($resGetRow['code'] === 200 && isset($resGetRow['data']['values'][0])) {
                $existingRow = $resGetRow['data']['values'][0];
                if (!$conn['sync_status']) {
                    $status = $existingRow[10] ?? '';
                }
                if (!$conn['sync_lead_score']) {
                    $score = $existingRow[11] ?? '';
                }
                if (!$conn['sync_notes']) {
                    $notes = $existingRow[12] ?? '';
                }
            }
        }

        $rowValues = [
            $leadId,
            $leadName,
            $companyName,
            $linkedinUrl,
            $email,
            $phone,
            $source,
            $genEmail,
            $genWhatsApp,
            $genComment,
            $status,
            $score,
            $notes,
            $createdAt,
            $updatedAt
        ];

        if ($isExisting) {
            // Update Row
            $urlWrite = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/Sheet1!A$rowNumber:O$rowNumber?valueInputOption=USER_ENTERED";
            $bodyWrite = json_encode([
                'range' => "Sheet1!A$rowNumber:O$rowNumber",
                'majorDimension' => 'ROWS',
                'values' => [$rowValues]
            ]);
            $resWrite = self::makeCurlRequest($urlWrite, 'PUT', $bodyWrite, [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json'
            ]);
            return ($resWrite['code'] === 200);
        } else {
            // Append Row
            $urlAppend = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/Sheet1!A:O:append?valueInputOption=USER_ENTERED";
            $bodyAppend = json_encode([
                'range' => "Sheet1!A:O",
                'majorDimension' => 'ROWS',
                'values' => [$rowValues]
            ]);
            $resAppend = self::makeCurlRequest($urlAppend, 'POST', $bodyAppend, [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json'
            ]);
            return ($resAppend['code'] === 200);
        }
    }

    /**
     * Batch syncs all leads inside lead_vault for a specific user.
     */
    public static function bulkSyncLeads($userId) {
        $db = Database::getConnection();

        // 1. Fetch connection details
        $stmtConn = $db->prepare("SELECT * FROM google_sheet_connections WHERE user_id = ?");
        $stmtConn->execute([$userId]);
        $conn = $stmtConn->fetch();

        if (!$conn || empty($conn['spreadsheet_id'])) {
            throw new Exception("Google Sheet connection not configured.");
        }

        $spreadsheetId = $conn['spreadsheet_id'];
        $token = self::getAccessToken($userId);
        if (!$token) {
            throw new Exception("Google account validation failed.");
        }

        // 2. Fetch all user leads from database
        $stmtLeads = $db->prepare("SELECT * FROM lead_vault WHERE user_id = ? ORDER BY id ASC");
        $stmtLeads->execute([$userId]);
        $leads = $stmtLeads->fetchAll();

        if (empty($leads)) {
            return 0; // No leads to sync
        }

        // 3. Retrieve all existing Row IDs currently in Google Sheet
        $urlRead = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/Sheet1!A:O";
        $resRead = self::makeCurlRequest($urlRead, 'GET', null, [
            'Authorization' => "Bearer $token"
        ]);

        $existingRows = [];
        $headerOffset = 1;
        if ($resRead['code'] === 200 && isset($resRead['data']['values'])) {
            $existingRows = $resRead['data']['values'];
        }

        // Maps Lead ID to Row Index (1-indexed) in spreadsheet
        $idMap = [];
        foreach ($existingRows as $index => $row) {
            if (isset($row[0]) && $index > 0) { // Skip header row
                $idMap[(string)$row[0]] = [
                    'row_num' => $index + 1,
                    'data' => $row
                ];
            }
        }

        $syncedCount = 0;
        $batchUpdates = [];
        $appendRows = [];

        foreach ($leads as $lead) {
            $leadId = (string)$lead['id'];
            $isExisting = isset($idMap[$leadId]);

            if ($isExisting && !$conn['sync_update_leads']) {
                continue;
            }
            if (!$isExisting && !$conn['sync_new_leads']) {
                continue;
            }

            $leadName = $lead['name'];
            $companyName = $lead['company_name'];
            $linkedinUrl = $lead['linkedin_url'];
            $email = $lead['email'];
            $phone = $lead['phone_number'];
            $source = $lead['source'];

            $genEmail = $lead['generated_email'];
            $genWhatsApp = $lead['generated_whatsapp'];
            $genComment = $lead['generated_comment'];

            $status = ($conn['sync_status']) ? $lead['current_status'] : 'N/A';
            $score = ($conn['sync_lead_score']) ? $lead['lead_score'] : '';
            $notes = ($conn['sync_notes']) ? $lead['remarks'] : '';
            
            $createdAt = $lead['created_at'];
            $updatedAt = $lead['updated_at'] ?? $lead['created_at'];

            if ($isExisting) {
                $existingRowData = $idMap[$leadId]['data'];
                if (!$conn['sync_status']) {
                    $status = $existingRowData[10] ?? '';
                }
                if (!$conn['sync_lead_score']) {
                    $score = $existingRowData[11] ?? '';
                }
                if (!$conn['sync_notes']) {
                    $notes = $existingRowData[12] ?? '';
                }
            }

            $rowValues = [
                $lead['id'],
                $leadName,
                $companyName,
                $linkedinUrl,
                $email,
                $phone,
                $source,
                $genEmail,
                $genWhatsApp,
                $genComment,
                $status,
                $score,
                $notes,
                $createdAt,
                $updatedAt
            ];

            if ($isExisting) {
                $rowNumber = $idMap[$leadId]['row_num'];
                $batchUpdates[] = [
                    'range' => "Sheet1!A$rowNumber:O$rowNumber",
                    'values' => [$rowValues]
                ];
            } else {
                $appendRows[] = $rowValues;
            }
            $syncedCount++;
        }

        // Execute batch updates for existing rows
        if (!empty($batchUpdates)) {
            $urlBatch = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values:batchUpdate";
            $bodyBatch = json_encode([
                'valueInputOption' => 'USER_ENTERED',
                'data' => $batchUpdates
            ]);
            $resBatch = self::makeCurlRequest($urlBatch, 'POST', $bodyBatch, [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json'
            ]);
            if ($resBatch['code'] !== 200) {
                throw new Exception("Batch update failed: " . $resBatch['body']);
            }
        }

        // Execute appends for new rows
        if (!empty($appendRows)) {
            $urlAppend = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/Sheet1!A:O:append?valueInputOption=USER_ENTERED";
            $bodyAppend = json_encode([
                'range' => "Sheet1!A:O",
                'majorDimension' => 'ROWS',
                'values' => $appendRows
            ]);
            $resAppend = self::makeCurlRequest($urlAppend, 'POST', $bodyAppend, [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json'
            ]);
            if ($resAppend['code'] !== 200) {
                throw new Exception("Append row failed: " . $resAppend['body']);
            }
        }

        return $syncedCount;
    }

    /**
     * Resolves Google Sheets Client credentials dynamically from database settings
     * or falls back to system configuration constants.
     */
    public static function getClientCredentials() {
        $db = Database::getConnection();
        $clientId = '';
        $clientSecret = '';
        try {
            $stmt = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1");
            
            $stmt->execute(['google_sheets_client_id']);
            $clientId = trim($stmt->fetchColumn() ?: '');
            
            $stmt->execute(['google_sheets_client_secret']);
            $clientSecret = trim($stmt->fetchColumn() ?: '');
        } catch (Exception $e) {}
        
        if (empty($clientId)) {
            $clientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
        }
        if (empty($clientSecret)) {
            $clientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
        }
        
        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ];
    }
}
