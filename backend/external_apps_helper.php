<?php
// backend/external_apps_helper.php

require_once __DIR__ . '/config.php';

class ExternalAppsHelper {

    /**
     * Self-healing database check. Automatically creates connection table and adds missing columns.
     */
    public static function checkDatabaseSchema() {
        $db = Database::getConnection();
        try {
            // 1. Create external_app_connections table if missing
            $stmt = $db->query("SHOW TABLES LIKE 'external_app_connections'");
            if ($stmt->rowCount() === 0) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `external_app_connections` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NOT NULL,
                        `provider` VARCHAR(50) NOT NULL,
                        `email` VARCHAR(255) DEFAULT NULL,
                        `access_token` TEXT DEFAULT NULL,
                        `refresh_token` TEXT DEFAULT NULL,
                        `expires_at` TIMESTAMP NULL DEFAULT NULL,
                        `status` VARCHAR(50) DEFAULT 'connected',
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        CONSTRAINT `fk_external_app_user_helper` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                        UNIQUE KEY `idx_user_provider` (`user_id`, `provider`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // 2. Append missing CRM fields to crm_meetings
            try {
                $stmt = $db->query("SHOW COLUMNS FROM `crm_meetings` LIKE 'google_event_id'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `crm_meetings` ADD COLUMN `google_event_id` VARCHAR(255) DEFAULT NULL AFTER `status`");
                }
            } catch (Exception $e) {}

            try {
                $stmt = $db->query("SHOW COLUMNS FROM `crm_meetings` LIKE 'meet_link'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `crm_meetings` ADD COLUMN `meet_link` VARCHAR(500) DEFAULT NULL AFTER `google_event_id`");
                }
            } catch (Exception $e) {}

            // 3. Append missing CRM fields to crm_tasks
            try {
                $stmt = $db->query("SHOW COLUMNS FROM `crm_tasks` LIKE 'sync_to_calendar'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `crm_tasks` ADD COLUMN `sync_to_calendar` TINYINT(1) DEFAULT 0 AFTER `status`");
                }
            } catch (Exception $e) {}

            try {
                $stmt = $db->query("SHOW COLUMNS FROM `crm_tasks` LIKE 'google_event_id'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `crm_tasks` ADD COLUMN `google_event_id` VARCHAR(255) DEFAULT NULL AFTER `sync_to_calendar`");
                }
            } catch (Exception $e) {}

        } catch (Exception $e) {
            error_log("ExternalAppsHelper Database Setup Error: " . $e->getMessage());
        }
    }

    /**
     * Fetch Google Client ID and Secret from admin_settings, fallback to config constants
     */
    public static function getGoogleCredentials() {
        self::checkDatabaseSchema();
        $db = Database::getConnection();
        
        $stmt = $db->query("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key IN ('google_external_enabled', 'google_external_client_id', 'google_external_client_secret', 'google_external_scopes')");
        $rows = $stmt->fetchAll();
        
        $settings = [
            'enabled' => '1',
            'client_id' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '',
            'client_secret' => defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '',
            'scopes' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/gmail.compose https://www.googleapis.com/auth/gmail.modify'
        ];

        foreach ($rows as $r) {
            if ($r['setting_key'] === 'google_external_enabled') $settings['enabled'] = $r['setting_value'];
            if ($r['setting_key'] === 'google_external_client_id') $settings['client_id'] = $r['setting_value'];
            if ($r['setting_key'] === 'google_external_client_secret') $settings['client_secret'] = $r['setting_value'];
            if ($r['setting_key'] === 'google_external_scopes') $settings['scopes'] = $r['setting_value'];
        }

        return $settings;
    }

    /**
     * Checks if user has a connected Google account
     */
    public static function isGoogleConnected($userId) {
        self::checkDatabaseSchema();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT status FROM external_app_connections WHERE user_id = ? AND provider = 'google' LIMIT 1");
        $stmt->execute([$userId]);
        $status = $stmt->fetchColumn();
        return ($status === 'connected');
    }

    /**
     * Helper to perform cURL requests to Google API
     */
    public static function makeCurlRequest($url, $method = 'GET', $body = null, $headers = []) {
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Keep false for dev environments

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
     * Gets and auto-refreshes Google access token
     */
    public static function getGoogleAccessToken($userId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM external_app_connections WHERE user_id = ? AND provider = 'google' LIMIT 1");
        $stmt->execute([$userId]);
        $conn = $stmt->fetch();

        if (!$conn || $conn['status'] !== 'connected') {
            return false;
        }

        $expiresAt = strtotime($conn['expires_at']);
        $accessToken = decryptData($conn['access_token']);

        // Refresh token if expired or expiring in 60s
        if ($expiresAt <= (time() + 60)) {
            $refreshToken = decryptData($conn['refresh_token']);
            if (!$refreshToken) {
                return false;
            }

            $creds = self::getGoogleCredentials();
            
            $url = 'https://oauth2.googleapis.com/token';
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
                $newAccess = $res['data']['access_token'];
                $newExpiresIn = $res['data']['expires_in'] ?? 3600;
                $newExpiresAt = date('Y-m-d H:i:s', time() + $newExpiresIn);

                $encryptedAccess = encryptData($newAccess);
                
                $stmtUpdate = $db->prepare("UPDATE external_app_connections SET access_token = ?, expires_at = ?, updated_at = NOW() WHERE id = ?");
                $stmtUpdate->execute([$encryptedAccess, $newExpiresAt, $conn['id']]);

                return $newAccess;
            } else {
                error_log("Google Refresh Token Error: " . $res['body']);
                return false;
            }
        }

        return $accessToken;
    }

    /**
     * Schedule a meeting inside Google Calendar with Google Meet enabled
     */
    public static function createGoogleCalendarEvent($userId, $meetingId, $title, $description, $startTime, $endTime, $location, $contactId = null) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token) return false;

        $db = Database::getConnection();

        // Fetch contact email if linked
        $attendees = [];
        if ($contactId) {
            $stmtC = $db->prepare("SELECT name, email FROM crm_contacts WHERE id = ? LIMIT 1");
            $stmtC->execute([$contactId]);
            $contact = $stmtC->fetch();
            if ($contact && !empty($contact['email'])) {
                $attendees[] = [
                    'email' => $contact['email'],
                    'displayName' => $contact['name']
                ];
            }
        }

        // Format dates to RFC3339
        $startIso = date(DATE_RFC3339, strtotime($startTime));
        $endIso = $endTime ? date(DATE_RFC3339, strtotime($endTime)) : date(DATE_RFC3339, strtotime($startTime) + 3600);

        $eventBody = [
            'summary' => $title,
            'description' => $description,
            'location' => $location,
            'start' => ['dateTime' => $startIso],
            'end' => ['dateTime' => $endIso],
            'attendees' => $attendees,
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => 30],
                    ['method' => 'email', 'minutes' => 1440]
                ]
            ],
            // Request Google Meet creation
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => 'meet-' . $meetingId . '-' . time(),
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet'
                    ]
                ]
            ]
        ];

        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1';
        $res = self::makeCurlRequest($url, 'POST', json_encode($eventBody), [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ]);

        if ($res['code'] === 200 && isset($res['data']['id'])) {
            $googleEventId = $res['data']['id'];
            
            // Extract Google Meet link
            $meetLink = null;
            $confData = $res['data']['conferenceData'] ?? [];
            $entryPoints = $confData['entryPoints'] ?? [];
            foreach ($entryPoints as $ep) {
                if ($ep['entryPointType'] === 'video') {
                    $meetLink = $ep['uri'];
                    break;
                }
            }

            // Save to local database
            $stmtUpdate = $db->prepare("UPDATE crm_meetings SET google_event_id = ?, meet_link = ? WHERE id = ?");
            $stmtUpdate->execute([$googleEventId, $meetLink, $meetingId]);

            return [
                'event_id' => $googleEventId,
                'meet_link' => $meetLink
            ];
        }

        error_log("Failed to create Google Calendar Event: " . $res['body']);
        return false;
    }

    /**
     * Update an existing Google Calendar Event
     */
    public static function updateGoogleCalendarEvent($userId, $googleEventId, $title, $description, $startTime, $endTime, $location, $contactId = null) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token || !$googleEventId) return false;

        $db = Database::getConnection();

        // Fetch contact email if linked
        $attendees = [];
        if ($contactId) {
            $stmtC = $db->prepare("SELECT name, email FROM crm_contacts WHERE id = ? LIMIT 1");
            $stmtC->execute([$contactId]);
            $contact = $stmtC->fetch();
            if ($contact && !empty($contact['email'])) {
                $attendees[] = [
                    'email' => $contact['email'],
                    'displayName' => $contact['name']
                ];
            }
        }

        $startIso = date(DATE_RFC3339, strtotime($startTime));
        $endIso = $endTime ? date(DATE_RFC3339, strtotime($endTime)) : date(DATE_RFC3339, strtotime($startTime) + 3600);

        $eventBody = [
            'summary' => $title,
            'description' => $description,
            'location' => $location,
            'start' => ['dateTime' => $startIso],
            'end' => ['dateTime' => $endIso],
            'attendees' => $attendees
        ];

        $url = "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$googleEventId}";
        $res = self::makeCurlRequest($url, 'PUT', json_encode($eventBody), [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ]);

        return ($res['code'] === 200);
    }

    /**
     * Delete Google Calendar Event
     */
    public static function deleteGoogleCalendarEvent($userId, $googleEventId) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token || !$googleEventId) return false;

        $url = "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$googleEventId}";
        $res = self::makeCurlRequest($url, 'DELETE', null, [
            'Authorization' => "Bearer $token"
        ]);

        return ($res['code'] === 204 || $res['code'] === 200);
    }

    /**
     * Create Calendar Event from Task Sync
     */
    public static function createGoogleCalendarEventFromTask($userId, $taskId, $title, $description, $dueDate, $dueTime = null) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token) return false;

        $db = Database::getConnection();

        $startStr = $dueDate . ' ' . ($dueTime ?: '09:00:00');
        $startIso = date(DATE_RFC3339, strtotime($startStr));
        $endIso = date(DATE_RFC3339, strtotime($startStr) + 1800); // 30-min duration task

        $eventBody = [
            'summary' => "[Task] " . $title,
            'description' => $description,
            'start' => ['dateTime' => $startIso],
            'end' => ['dateTime' => $endIso],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => 15]
                ]
            ]
        ];

        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
        $res = self::makeCurlRequest($url, 'POST', json_encode($eventBody), [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ]);

        if ($res['code'] === 200 && isset($res['data']['id'])) {
            $googleEventId = $res['data']['id'];
            
            $stmtUpdate = $db->prepare("UPDATE crm_tasks SET google_event_id = ?, sync_to_calendar = 1 WHERE id = ?");
            $stmtUpdate->execute([$googleEventId, $taskId]);

            return $googleEventId;
        }

        return false;
    }

    /**
     * Update Google Calendar Event from Task Sync
     */
    public static function updateGoogleCalendarEventFromTask($userId, $googleEventId, $title, $description, $dueDate, $dueTime = null) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token || !$googleEventId) return false;

        $startStr = $dueDate . ' ' . ($dueTime ?: '09:00:00');
        $startIso = date(DATE_RFC3339, strtotime($startStr));
        $endIso = date(DATE_RFC3339, strtotime($startStr) + 1800);

        $eventBody = [
            'summary' => "[Task] " . $title,
            'description' => $description,
            'start' => ['dateTime' => $startIso],
            'end' => ['dateTime' => $endIso]
        ];

        $url = "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$googleEventId}";
        $res = self::makeCurlRequest($url, 'PUT', json_encode($eventBody), [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ]);

        return ($res['code'] === 200);
    }

    /**
     * Send email via Gmail API
     */
    public static function sendGmailEmail($userId, $recipientEmail, $subject, $body, $attachments = [], $inReplyTo = null) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token) {
            return [
                'status' => false,
                'message' => 'Gmail account not connected or authorization failed.'
            ];
        }

        // Build raw RFC2822 email headers and body
        $boundary = uniqid('np', true);
        $rawMessage = "To: $recipientEmail\r\n";
        $rawMessage .= "Subject: =?utf-8?B?" . base64_encode($subject) . "?=\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        
        if ($inReplyTo) {
            $rawMessage .= "In-Reply-To: $inReplyTo\r\n";
            $rawMessage .= "References: $inReplyTo\r\n";
        }

        if (empty($attachments)) {
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
            $rawMessage .= $body;
        } else {
            $rawMessage .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
            $rawMessage .= "--$boundary\r\n";
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
            $rawMessage .= $body . "\r\n";

            foreach ($attachments as $att) {
                if (isset($att['path']) && is_file($att['path'])) {
                    $fileName = basename($att['name'] ?? $att['path']);
                    $fileContent = base64_encode(file_get_contents($att['path']));
                    $mimeType = mime_content_type($att['path']) ?: 'application/octet-stream';
                    
                    $rawMessage .= "--$boundary\r\n";
                    $rawMessage .= "Content-Type: $mimeType; name=\"$fileName\"\r\n";
                    $rawMessage .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n";
                    $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
                    $rawMessage .= chunk_split($fileContent) . "\r\n";
                }
            }
            $rawMessage .= "--$boundary--";
        }

        // Base64url encode helper
        $encodedRaw = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

        $url = 'https://gmail.googleapis.com/v1/users/me/messages/send';
        $res = self::makeCurlRequest($url, 'POST', json_encode(['raw' => $encodedRaw]), [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ]);

        if ($res['code'] === 200 && isset($res['data']['id'])) {
            return [
                'status' => true,
                'message' => 'Email sent successfully via Gmail API.',
                'message_id' => $res['data']['id']
            ];
        }

        return [
            'status' => false,
            'message' => 'Gmail API Send Error: ' . ($res['data']['error']['message'] ?? $res['body'])
        ];
    }

    /**
     * Create email Draft in Gmail API
     */
    public static function createGmailDraft($userId, $recipientEmail, $subject, $body) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token) {
            return [
                'status' => false,
                'message' => 'Gmail account not connected or authorization failed.'
            ];
        }

        $rawMessage = "To: $recipientEmail\r\n";
        $rawMessage .= "Subject: =?utf-8?B?" . base64_encode($subject) . "?=\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
        $rawMessage .= $body;

        $encodedRaw = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

        $url = 'https://gmail.googleapis.com/v1/users/me/drafts';
        $res = self::makeCurlRequest($url, 'POST', json_encode([
            'message' => [
                'raw' => $encodedRaw
            ]
        ]), [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ]);

        if ($res['code'] === 200 && isset($res['data']['id'])) {
            return [
                'status' => true,
                'message' => 'Draft created successfully in Gmail.',
                'draft_id' => $res['data']['id']
            ];
        }

        return [
            'status' => false,
            'message' => 'Gmail API Draft Error: ' . ($res['data']['error']['message'] ?? $res['body'])
        ];
    }
}
