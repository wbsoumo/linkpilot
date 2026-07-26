<?php
// backend/external_apps_helper.php

require_once __DIR__ . '/config.php';

class GoogleOAuthHelper {
    public static $predefined = [
        'login' => [
            'openid',
            'email',
            'profile'
        ],
        'calendar' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/calendar.events'
        ],
        'gmail' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/gmail.modify'
        ]
    ];

    public static function getScopes($type) {
        if (!isset(self::$predefined[$type])) {
            return [];
        }
        return self::$predefined[$type];
    }

    public static function validateScopes($scopesArray) {
        $allowed = [];
        foreach (self::$predefined as $t => $sList) {
            $allowed = array_merge($allowed, $sList);
        }

        $valid = [];
        foreach ($scopesArray as $s) {
            $s = trim($s);
            if (empty($s)) continue;
            if (in_array($s, $allowed)) {
                $valid[] = $s;
            }
        }
        return array_values(array_unique($valid));
    }

    public static function validateConfiguration($creds, $redirectUri) {
        if (empty($creds['client_id'])) {
            return 'Google Client ID is missing or empty.';
        }
        if (empty($creds['client_secret'])) {
            return 'Google Client Secret is missing or empty.';
        }
        if (empty($redirectUri) || !filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            return 'Google Redirect URI is invalid or empty.';
        }
        return true;
    }
}

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

            try {
                $stmt = $db->query("SHOW COLUMNS FROM `crm_meetings` LIKE 'zoom_meeting_id'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `crm_meetings` ADD COLUMN `zoom_meeting_id` VARCHAR(255) DEFAULT NULL AFTER `google_event_id`");
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

            // New connection columns
            try {
                $stmt = $db->query("SHOW COLUMNS FROM `external_app_connections` LIKE 'connected_email'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `external_app_connections` ADD COLUMN `connected_email` VARCHAR(255) DEFAULT NULL AFTER `email`");
                }
            } catch (Exception $e) {}

            try {
                $stmt = $db->query("SHOW COLUMNS FROM `external_app_connections` LIKE 'connected_name'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `external_app_connections` ADD COLUMN `connected_name` VARCHAR(255) DEFAULT NULL AFTER `connected_email`");
                }
            } catch (Exception $e) {}

            try {
                $stmt = $db->query("SHOW COLUMNS FROM `external_app_connections` LIKE 'avatar'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `external_app_connections` ADD COLUMN `avatar` TEXT DEFAULT NULL AFTER `connected_name`");
                }
            } catch (Exception $e) {}

            try {
                $stmt = $db->query("SHOW COLUMNS FROM `external_app_connections` LIKE 'google_user_id'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `external_app_connections` ADD COLUMN `google_user_id` VARCHAR(255) DEFAULT NULL AFTER `avatar`");
                }
            } catch (Exception $e) {}

            try {
                $stmt = $db->query("SHOW COLUMNS FROM `external_app_connections` LIKE 'scopes'");
                if ($stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE `external_app_connections` ADD COLUMN `scopes` TEXT DEFAULT NULL AFTER `google_user_id`");
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
     * Fetch Zoom Client ID and Secret from admin_settings
     */
    public static function getZoomCredentials() {
        self::checkDatabaseSchema();
        $db = Database::getConnection();
        
        $stmt = $db->query("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key IN ('zoom_external_enabled', 'zoom_external_client_id', 'zoom_external_client_secret', 'zoom_webhook_secret_token')");
        $rows = $stmt->fetchAll();
        
        $settings = [
            'enabled' => '1',
            'client_id' => '',
            'client_secret' => '',
            'webhook_secret_token' => ''
        ];

        foreach ($rows as $r) {
            if ($r['setting_key'] === 'zoom_external_enabled') $settings['enabled'] = $r['setting_value'];
            if ($r['setting_key'] === 'zoom_external_client_id') $settings['client_id'] = $r['setting_value'];
            if ($r['setting_key'] === 'zoom_external_client_secret') $settings['client_secret'] = $r['setting_value'];
            if ($r['setting_key'] === 'zoom_webhook_secret_token') $settings['webhook_secret_token'] = $r['setting_value'];
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
     * Checks if user has a connected Zoom account
     */
    public static function isZoomConnected($userId) {
        self::checkDatabaseSchema();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT status FROM external_app_connections WHERE user_id = ? AND provider = 'zoom' LIMIT 1");
        $stmt->execute([$userId]);
        $status = $stmt->fetchColumn();
        return ($status === 'connected');
    }

    /**
     * Checks if user has a connected Google account with active Gmail sync scopes
     */
    public static function isGoogleGmailConnected($userId) {
        self::checkDatabaseSchema();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT scopes, status FROM external_app_connections WHERE user_id = ? AND provider = 'google' LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || $row['status'] !== 'connected') {
            return false;
        }
        $scopes = $row['scopes'] ?? '';
        return (strpos($scopes, 'gmail.modify') !== false || strpos($scopes, 'mail.google.com') !== false);
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
        if (!class_exists('Google\Client')) {
            error_log("Google API Client class not found. Please run 'composer install' in backend directory.");
            return false;
        }

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
            
            try {
                $client = new Google\Client();
                $client->setClientId($creds['client_id']);
                $client->setClientSecret($creds['client_secret']);
                
                $tokenData = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                if (isset($tokenData['error'])) {
                    throw new Exception("Token refresh failed: " . ($tokenData['error_description'] ?? $tokenData['error']));
                }
                
                $newAccess = $tokenData['access_token'];
                $newExpiresIn = $tokenData['expires_in'] ?? 3600;
                $newExpiresAt = date('Y-m-d H:i:s', time() + $newExpiresIn);

                $encryptedAccess = encryptData($newAccess);
                
                $stmtUpdate = $db->prepare("UPDATE external_app_connections SET access_token = ?, expires_at = ?, updated_at = NOW() WHERE id = ?");
                $stmtUpdate->execute([$encryptedAccess, $newExpiresAt, $conn['id']]);

                return $newAccess;
            } catch (Exception $e) {
                error_log("Google SDK Refresh Token Error: " . $e->getMessage());
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

        $attendees = [];
        if ($contactId) {
            $stmtC = $db->prepare("SELECT name, email FROM crm_contacts WHERE id = ? LIMIT 1");
            $stmtC->execute([$contactId]);
            $contact = $stmtC->fetch();
            if ($contact && !empty($contact['email'])) {
                $attendees[] = new Google\Service\Calendar\EventAttendee([
                    'email' => $contact['email'],
                    'displayName' => $contact['name']
                ]);
            }
        }

        $startIso = date(DATE_RFC3339, strtotime($startTime));
        $endIso = $endTime ? date(DATE_RFC3339, strtotime($endTime)) : date(DATE_RFC3339, strtotime($startTime) + 3600);

        try {
            $client = new Google\Client();
            $client->setAccessToken($token);
            $service = new Google\Service\Calendar($client);

            $isZoom = (str_contains($location, 'zoom.us') || str_contains($location, 'zoom.com'));

            $eventData = [
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
                ]
            ];

            if (!$isZoom) {
                $eventData['conferenceData'] = [
                    'createRequest' => [
                        'requestId' => 'meet-' . $meetingId . '-' . time(),
                        'conferenceSolutionKey' => [
                            'type' => 'hangoutsMeet'
                        ]
                    ]
                ];
            }

            $event = new Google\Service\Calendar\Event($eventData);

            $createdEvent = $service->events->insert('primary', $event, $isZoom ? [] : ['conferenceDataVersion' => 1]);
            $googleEventId = $createdEvent->getId();
            
            // Extract Google Meet link
            $meetLink = null;
            if (!$isZoom) {
                $confData = $createdEvent->getConferenceData();
                if ($confData) {
                    $entryPoints = $confData->getEntryPoints();
                    if ($entryPoints) {
                        foreach ($entryPoints as $ep) {
                            if ($ep->getEntryPointType() === 'video') {
                                $meetLink = $ep->getUri();
                                break;
                            }
                        }
                    }
                }
            } else {
                $meetLink = $location;
            }

            // Save to local database (including location and meet_link updates)
            $stmtUpdate = $db->prepare("UPDATE crm_meetings SET google_event_id = ?, meet_link = ?, location = ? WHERE id = ?");
            $stmtUpdate->execute([$googleEventId, $meetLink, $meetLink ?: $location, $meetingId]);

            return [
                'event_id' => $googleEventId,
                'meet_link' => $meetLink
            ];
        } catch (Exception $e) {
            error_log("Failed to create Google Calendar Event via SDK: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing Google Calendar Event
     */
    public static function updateGoogleCalendarEvent($userId, $googleEventId, $title, $description, $startTime, $endTime, $location, $contactId = null) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token || !$googleEventId) return false;

        $db = Database::getConnection();

        $attendees = [];
        if ($contactId) {
            $stmtC = $db->prepare("SELECT name, email FROM crm_contacts WHERE id = ? LIMIT 1");
            $stmtC->execute([$contactId]);
            $contact = $stmtC->fetch();
            if ($contact && !empty($contact['email'])) {
                $attendees[] = new Google\Service\Calendar\EventAttendee([
                    'email' => $contact['email'],
                    'displayName' => $contact['name']
                ]);
            }
        }

        $startIso = date(DATE_RFC3339, strtotime($startTime));
        $endIso = $endTime ? date(DATE_RFC3339, strtotime($endTime)) : date(DATE_RFC3339, strtotime($startTime) + 3600);

        try {
            $client = new Google\Client();
            $client->setAccessToken($token);
            $service = new Google\Service\Calendar($client);

            $event = new Google\Service\Calendar\Event([
                'summary' => $title,
                'description' => $description,
                'location' => $location,
                'start' => ['dateTime' => $startIso],
                'end' => ['dateTime' => $endIso],
                'attendees' => $attendees
            ]);

            $updatedEvent = $service->events->update('primary', $googleEventId, $event);
            return !empty($updatedEvent->getId());
        } catch (Exception $e) {
            error_log("Failed to update Google Calendar Event via SDK: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete Google Calendar Event
     */
    public static function deleteGoogleCalendarEvent($userId, $googleEventId) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token || !$googleEventId) return false;

        try {
            $client = new Google\Client();
            $client->setAccessToken($token);
            $service = new Google\Service\Calendar($client);

            $service->events->delete('primary', $googleEventId);
            return true;
        } catch (Exception $e) {
            error_log("Failed to delete Google Calendar Event via SDK: " . $e->getMessage());
            return false;
        }
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

        try {
            $client = new Google\Client();
            $client->setAccessToken($token);
            $service = new Google\Service\Calendar($client);

            $event = new Google\Service\Calendar\Event([
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
            ]);

            $created = $service->events->insert('primary', $event);
            $googleEventId = $created->getId();
            
            $stmtUpdate = $db->prepare("UPDATE crm_tasks SET google_event_id = ?, sync_to_calendar = 1 WHERE id = ?");
            $stmtUpdate->execute([$googleEventId, $taskId]);

            return $googleEventId;
        } catch (Exception $e) {
            error_log("Failed to create Google Task Calendar Event via SDK: " . $e->getMessage());
            return false;
        }
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

        try {
            $client = new Google\Client();
            $client->setAccessToken($token);
            $service = new Google\Service\Calendar($client);

            $event = new Google\Service\Calendar\Event([
                'summary' => "[Task] " . $title,
                'description' => $description,
                'start' => ['dateTime' => $startIso],
                'end' => ['dateTime' => $endIso]
            ]);

            $updated = $service->events->update('primary', $googleEventId, $event);
            return !empty($updated->getId());
        } catch (Exception $e) {
            error_log("Failed to update Google Task Calendar Event via SDK: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email via Gmail API
     */
    public static function sendGmailEmail($userId, $recipientEmail, $subject, $body, $attachments = [], $inReplyTo = null, $ccEmails = []) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token) {
            return [
                'status' => false,
                'message' => 'Gmail account not connected or authorization failed.'
            ];
        }

        $db = Database::getConnection();
        $senderEmail = '';
        $senderName = '';
        try {
            $stmt = $db->prepare("SELECT connected_email, connected_name FROM external_app_connections WHERE user_id = ? AND provider = 'google' LIMIT 1");
            $stmt->execute([$userId]);
            $conn = $stmt->fetch();
            if ($conn) {
                $senderEmail = $conn['connected_email'] ?? '';
                $senderName = $conn['connected_name'] ?? '';
            }
        } catch (Exception $e) {}

        // Build raw RFC2822 email headers and body
        $boundary = uniqid('np', true);
        $rawMessage = "";
        if (!empty($senderEmail)) {
            if (!empty($senderName)) {
                $rawMessage .= "From: =?utf-8?B?" . base64_encode($senderName) . "?= <$senderEmail>\r\n";
            } else {
                $rawMessage .= "From: $senderEmail\r\n";
            }
        }
        $rawMessage .= "To: $recipientEmail\r\n";
        if (!empty($ccEmails)) {
            $rawMessage .= "Cc: " . implode(', ', $ccEmails) . "\r\n";
        }
        $rawMessage .= "Subject: =?utf-8?B?" . base64_encode($subject) . "?=\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        
        if ($inReplyTo) {
            $rawMessage .= "In-Reply-To: $inReplyTo\r\n";
            $rawMessage .= "References: $inReplyTo\r\n";
        }

        if (empty($attachments)) {
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $rawMessage .= chunk_split(base64_encode($body));
        } else {
            $rawMessage .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
            $rawMessage .= "--$boundary\r\n";
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $rawMessage .= chunk_split(base64_encode($body)) . "\r\n";

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

        try {
            $client = new Google\Client();
            $client->setAccessToken($token);
            $service = new Google\Service\Gmail($client);

            $msg = new Google\Service\Gmail\Message();
            $msg->setRaw($encodedRaw);

            $result = $service->users_messages->send('me', $msg);
            return [
                'status' => true,
                'message' => 'Email sent successfully via Gmail API.',
                'message_id' => $result->getId()
            ];
        } catch (Exception $e) {
            error_log("Gmail API Send SDK Error: " . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Gmail API Send Error: ' . $e->getMessage()
            ];
        }
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
        $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $rawMessage .= chunk_split(base64_encode($body));

        $encodedRaw = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

        try {
            $client = new Google\Client();
            $client->setAccessToken($token);
            $service = new Google\Service\Gmail($client);

            $msg = new Google\Service\Gmail\Message();
            $msg->setRaw($encodedRaw);

            $draft = new Google\Service\Gmail\Draft();
            $draft->setMessage($msg);

            $result = $service->users_drafts->create('me', $draft);
            return [
                'status' => true,
                'message' => 'Draft created successfully in Gmail.',
                'draft_id' => $result->getId()
            ];
        } catch (Exception $e) {
            error_log("Gmail API Draft SDK Error: " . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Gmail API Draft Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate Google Meet link for a specific CRM Task
     */
    public static function generateGoogleMeetForTask($userId, $taskId) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token) {
            throw new Exception("Google Account connection is not active.");
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM crm_tasks WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$taskId, $userId]);
        $task = $stmt->fetch();
        
        if (!$task) {
            throw new Exception("Task not found.");
        }

        $title = $task['title'];
        // Ensure task title contains [Meeting] prefix
        if (!str_contains($title, '[Meeting]')) {
            if (str_starts_with($title, '[')) {
                $title = preg_replace('/^\[.*?\]\s*/', '[Meeting] ', $title);
            } else {
                $title = '[Meeting] ' . $title;
            }
        }

        $dueDate = $task['due_date'] ?: date('Y-m-d');
        $dueTime = $task['due_time'] ?: '09:00:00';
        $startStr = $dueDate . ' ' . $dueTime;
        $startIso = date(DATE_RFC3339, strtotime($startStr));
        $endIso = date(DATE_RFC3339, strtotime($startStr) + 3600); // 1-hour duration meeting

        try {
            $client = new Google\Client();
            $client->setAccessToken($token);
            $service = new Google\Service\Calendar($client);

            $event = new Google\Service\Calendar\Event([
                'summary' => $title,
                'description' => $task['description'] ?: 'Meeting scheduled via LinkPilot CRM.',
                'start' => ['dateTime' => $startIso],
                'end' => ['dateTime' => $endIso],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'popup', 'minutes' => 15]
                    ]
                ],
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => 'task-meet-' . $taskId . '-' . time(),
                        'conferenceSolutionKey' => [
                            'type' => 'hangoutsMeet'
                        ]
                    ]
                ]
            ]);

            $createdEvent = $service->events->insert('primary', $event, ['conferenceDataVersion' => 1]);
            $googleEventId = $createdEvent->getId();

            $meetLink = null;
            $confData = $createdEvent->getConferenceData();
            if ($confData) {
                $entryPoints = $confData->getEntryPoints();
                if ($entryPoints) {
                    foreach ($entryPoints as $ep) {
                        if ($ep->getEntryPointType() === 'video') {
                            $meetLink = $ep->getUri();
                            break;
                        }
                    }
                }
            }

            if (empty($meetLink)) {
                throw new Exception("Google Calendar did not generate a conference (Meet) link. Ensure Meet is enabled for your Google calendar account.");
            }

            // Update Task in DB
            $stmtUpdate = $db->prepare("UPDATE crm_tasks SET meet_link = ?, google_event_id = ?, title = ?, sync_to_calendar = 1 WHERE id = ?");
            $stmtUpdate->execute([$meetLink, $googleEventId, $title, $taskId]);

            return $meetLink;
        } catch (Exception $e) {
            throw new Exception("Google Meet generation failed: " . $e->getMessage());
        }
    }

    /**
     * Send calendar invite email with invite.ics attachment for a task meeting
     */
    public static function sendTaskMeetingInviteEmail($userId, $taskId, $meetLink, $recipientEmail) {
        $db = Database::getConnection();
        
        // Fetch task details
        $stmtTask = $db->prepare("SELECT title, description, due_date, due_time FROM crm_tasks WHERE id = ? AND user_id = ?");
        $stmtTask->execute([$taskId, $userId]);
        $task = $stmtTask->fetch();
        if (!$task) return false;

        $title = $task['title'];
        $cleanTitle = str_replace('[Meeting] ', '', $title);
        $dateStr = $task['due_date'] ?: date('Y-m-d');
        $timeStr = $task['due_time'] ?: '12:00:00';
        
        $startDateTime = str_replace('-', '', $dateStr) . 'T' . str_replace(':', '', $timeStr);
        $endTimeVal = strtotime("$dateStr $timeStr") + 3600;
        $endDateTime = date('Ymd\THis', $endTimeVal);
        
        $uid = uniqid() . '@linkpilot.ai';
        $createdStamp = date('Ymd\THis\Z');

        $icsContent = "BEGIN:VCALENDAR\r\n";
        $icsContent .= "VERSION:2.0\r\n";
        $icsContent .= "PRODID:-//LinkPilot//CRM//EN\r\n";
        $icsContent .= "METHOD:REQUEST\r\n";
        $icsContent .= "BEGIN:VEVENT\r\n";
        $icsContent .= "UID:{$uid}\r\n";
        $icsContent .= "DTSTAMP:{$createdStamp}\r\n";
        $icsContent .= "DTSTART:{$startDateTime}\r\n";
        $icsContent .= "DTEND:{$endDateTime}\r\n";
        $icsContent .= "SUMMARY:{$cleanTitle}\r\n";
        $icsContent .= "DESCRIPTION:{$task['description']}\\n\\nJoin meeting here: {$meetLink}\r\n";
        $icsContent .= "LOCATION:{$meetLink}\r\n";
        $icsContent .= "STATUS:CONFIRMED\r\n";
        $icsContent .= "SEQUENCE:0\r\n";
        $icsContent .= "ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:{$recipientEmail}\r\n";
        $icsContent .= "END:VEVENT\r\n";
        $icsContent .= "END:VCALENDAR\r\n";

        // Fetch SMTP credentials
        $stmtSmtp = $db->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption FROM imap_smtp_configurations WHERE user_id = ?");
        $stmtSmtp->execute([$userId]);
        $smtp = $stmtSmtp->fetch();

        $phpmailerPath = __DIR__ . '/libs/PHPMailer/PHPMailer.php';
        if (file_exists($phpmailerPath)) {
            require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/libs/PHPMailer/SMTP.php';
            require_once __DIR__ . '/libs/PHPMailer/Exception.php';
        }

        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                if ($smtp && !empty($smtp['smtp_host'])) {
                    $mail->isSMTP();
                    $mail->Host = $smtp['smtp_host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtp['smtp_username'];
                    $mail->Password = decryptData($smtp['smtp_password']);
                    $mail->Port = (int)$smtp['smtp_port'];
                    $mail->SMTPSecure = strtolower($smtp['smtp_encryption']) === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->isMail();
                }

                $mail->setFrom($smtp['smtp_username'] ?? 'noreply@linkpilot.ai', 'LinkPilot AI CRM');
                $mail->addAddress($recipientEmail);
                $mail->Subject = "Meeting Invite: " . $cleanTitle;
                $mail->isHTML(true);
                $mailBody = "
                    <div style='font-family: sans-serif; padding: 20px; color: #1e293b;'>
                        <h2 style='color: #4f46e5;'>Meeting Scheduled</h2>
                        <p>Hello,</p>
                        <p>You have been invited to a meeting scheduled through LinkPilot CRM.</p>
                        <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
                            <tr>
                                <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #e2e8f0; width: 120px;'>Subject:</td>
                                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0;'>{$cleanTitle}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #e2e8f0;'>Date & Time:</td>
                                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0;'>{$dateStr} @ " . substr($timeStr, 0, 5) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #e2e8f0;'>Meeting Link:</td>
                                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0;'><a href='{$meetLink}' style='color: #4f46e5; font-weight: bold;'>Join Google Meet</a></td>
                            </tr>
                        </table>
                        <p style='color: #64748b; font-size: 12px;'>An <b>invite.ics</b> file is attached to this email. You can add it directly to your Google Calendar, Outlook, or Apple Calendar in one click.</p>
                    </div>
                ";
                $mail->Body = $mailBody;

                $mail->addStringAttachment($icsContent, 'invite.ics', 'base64', 'text/calendar; method=REQUEST');
                $mail->send();
                return true;
            } catch (Exception $e) {
                error_log("Failed to send calendar invite email to $recipientEmail: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    /**
     * Fetch incoming/new emails from Gmail API using OAuth token
     */
    public static function fetchGmailEmails($userId, $limit = 10) {
        $token = self::getGoogleAccessToken($userId);
        if (!$token) {
            throw new Exception("Gmail account not connected or authorization failed.");
        }

        try {
            $client = new Google\Client();
            $client->setAccessToken($token);
            $service = new Google\Service\Gmail($client);

            // Fetch the list of messages in user's inbox
            $listParams = [
                'maxResults' => $limit,
                'q' => 'label:INBOX' // Only fetch from Inbox
            ];
            $messagesResponse = $service->users_messages->listUsersMessages('me', $listParams);
            $messagesList = $messagesResponse->getMessages();
            
            if (empty($messagesList)) {
                return [];
            }

            $db = Database::getConnection();
            $emails = [];

            foreach ($messagesList as $msgSummary) {
                $messageId = $msgSummary->getId();

                // Check if email already exists in DB
                $stmtCheck = $db->prepare("SELECT id FROM received_emails WHERE user_id = ? AND message_id = ?");
                $stmtCheck->execute([$userId, $messageId]);
                if ($stmtCheck->fetch()) {
                    continue; // already synced
                }

                // Get full message details
                $message = $service->users_messages->get('me', $messageId, ['format' => 'full']);
                $payload = $message->getPayload();
                if (!$payload) continue;

                // Extract Headers
                $headers = $payload->getHeaders();
                $subject = '(No Subject)';
                $senderEmail = '';
                $senderName = '';
                $recipientEmail = '';
                $dateStr = date('Y-m-d H:i:s');

                foreach ($headers as $h) {
                    $name = strtolower($h->getName());
                    if ($name === 'subject') {
                        $subject = $h->getValue();
                    } elseif ($name === 'from') {
                        $fromRaw = $h->getValue();
                        if (preg_match('/^(.*?)\s*<(.*?)>$/', $fromRaw, $matches)) {
                            $senderName = trim($matches[1], ' "\'');
                            $senderEmail = trim($matches[2]);
                        } else {
                            $senderEmail = trim($fromRaw);
                            $senderName = $senderEmail;
                        }
                    } elseif ($name === 'to') {
                        $toRaw = $h->getValue();
                        if (preg_match('/<(.*?)>/', $toRaw, $matches)) {
                            $recipientEmail = trim($matches[1]);
                        } else {
                            $recipientEmail = trim($toRaw);
                        }
                    } elseif ($name === 'date') {
                        $dateStr = date('Y-m-d H:i:s', strtotime($h->getValue()));
                    }
                }

                // Extract Body parts
                $bodies = ['text' => '', 'html' => ''];
                $attachments = [];

                $bodyObj = $payload->getBody();
                $data = $bodyObj ? $bodyObj->getData() : '';
                if (!empty($data)) {
                    $decodedData = base64_decode(str_replace(['-','_'], ['+','/'], $data));
                    if ($payload->getMimeType() === 'text/plain') {
                        $bodies['text'] = $decodedData;
                    } elseif ($payload->getMimeType() === 'text/html') {
                        $bodies['html'] = $decodedData;
                    }
                }

                $parts = $payload->getParts();
                if (!empty($parts)) {
                    self::parseGmailMessageParts($parts, $bodies, $attachments);
                }

                // Fetch attachment contents from Google API
                foreach ($attachments as &$att) {
                    try {
                        $attRes = $service->users_messages_attachments->get('me', $messageId, $att['attachment_id']);
                        $attData = base64_decode(str_replace(['-','_'], ['+','/'], $attRes->getData()));
                        $att['content'] = $attData;
                    } catch (Exception $e) {
                        error_log("Failed to fetch Gmail attachment content: " . $e->getMessage());
                        $att['content'] = '';
                    }
                    unset($att['attachment_id']);
                }

                $emails[] = [
                    'message_id' => $messageId,
                    'sender_email' => $senderEmail,
                    'sender_name' => $senderName,
                    'recipient_email' => $recipientEmail,
                    'subject' => $subject,
                    'body_text' => $bodies['text'],
                    'body_html' => $bodies['html'],
                    'received_date' => $dateStr,
                    'attachments' => $attachments
                ];
            }

            return $emails;

        } catch (Exception $e) {
            error_log("fetchGmailEmails API Error: " . $e->getMessage());
            throw new Exception("Gmail API fetch failed: " . $e->getMessage());
        }
    }

    private static function parseGmailMessageParts($parts, &$bodies, &$attachments) {
        foreach ($parts as $part) {
            $mimeType = strtolower($part->getMimeType());
            $body = $part->getBody();
            $data = $body ? $body->getData() : '';
            
            if (!empty($data)) {
                $decodedData = base64_decode(str_replace(['-','_'], ['+','/'], $data));
                if ($mimeType === 'text/plain') {
                    $bodies['text'] = $decodedData;
                } elseif ($mimeType === 'text/html') {
                    $bodies['html'] = $decodedData;
                }
            }
            
            $filename = $part->getFilename();
            $attachmentId = $body ? $body->getAttachmentId() : '';
            if (!empty($filename) && !empty($attachmentId)) {
                $attachments[] = [
                    'filename' => $filename,
                    'attachment_id' => $attachmentId,
                    'file_type' => explode('/', $mimeType)[1] ?? 'bin',
                    'file_size' => $body->getSize()
                ];
            }
            
            $subParts = $part->getParts();
            if (!empty($subParts)) {
                self::parseGmailMessageParts($subParts, $bodies, $attachments);
            }
        }
    }

    /**
     * Gets and auto-refreshes Zoom access token
     */
    public static function getZoomAccessToken($userId) {
        self::checkDatabaseSchema();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM external_app_connections WHERE user_id = ? AND provider = 'zoom' LIMIT 1");
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

            $creds = self::getZoomCredentials();
            $authHeader = base64_encode($creds['client_id'] . ':' . $creds['client_secret']);
            
            $url = 'https://zoom.us/oauth/token';
            $body = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken
            ];
            $headers = [
                'Authorization' => 'Basic ' . $authHeader,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ];

            $res = self::makeCurlRequest($url, 'POST', $body, $headers);
            if ($res['code'] !== 200 || !isset($res['data']['access_token'])) {
                error_log("Zoom Token Refresh Error (HTTP " . $res['code'] . "): " . $res['body']);
                return false;
            }

            $newAccess = $res['data']['access_token'];
            $newRefresh = $res['data']['refresh_token'] ?? $refreshToken;
            $newExpiresIn = $res['data']['expires_in'] ?? 3600;
            $newExpiresAt = date('Y-m-d H:i:s', time() + $newExpiresIn);

            $encryptedAccess = encryptData($newAccess);
            $encryptedRefresh = encryptData($newRefresh);

            $stmtUpdate = $db->prepare("UPDATE external_app_connections SET access_token = ?, refresh_token = ?, expires_at = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$encryptedAccess, $encryptedRefresh, $newExpiresAt, $conn['id']]);

            return $newAccess;
        }

        return $accessToken;
    }

    /**
     * Schedule a meeting inside Zoom using Zoom Meetings API
     */
    public static function createZoomMeeting($userId, $meetingId, $title, $description, $startTime, $endTime) {
        $token = self::getZoomAccessToken($userId);
        if (!$token) return false;

        $db = Database::getConnection();

        // Calculate duration in minutes (default 30)
        $duration = 30;
        if ($startTime && $endTime) {
            $diff = strtotime($endTime) - strtotime($startTime);
            if ($diff > 0) {
                $duration = ceil($diff / 60);
            }
        }

        // Format start time as ISO 8601 UTC (e.g. 2026-07-13T12:00:00Z)
        $utcStart = gmdate('Y-m-d\TH:i:s\Z', strtotime($startTime));

        $url = 'https://api.zoom.us/v2/users/me/meetings';
        $body = json_encode([
            'topic' => $title,
            'type' => 2, // Scheduled meeting
            'start_time' => $utcStart,
            'duration' => (int)$duration,
            'timezone' => 'UTC',
            'agenda' => $description ?: 'Scheduled via LinkPilot AI CRM',
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => true,
                'mute_upon_entry' => false,
                'waiting_room' => false
            ]
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ];

        $res = self::makeCurlRequest($url, 'POST', $body, $headers);
        if ($res['code'] !== 201 || !isset($res['data']['join_url'])) {
            error_log("Zoom Meeting Creation Failure (HTTP " . $res['code'] . "): " . $res['body']);
            return false;
        }

        $zoomMeetingId = $res['data']['id'];
        $joinUrl = $res['data']['join_url'];

        // Save Zoom meeting ID and join link to database (including location)
        $stmtUpdate = $db->prepare("UPDATE crm_meetings SET zoom_meeting_id = ?, meet_link = ?, location = ? WHERE id = ?");
        $stmtUpdate->execute([$zoomMeetingId, $joinUrl, $joinUrl, $meetingId]);

        return [
            'meeting_id' => $zoomMeetingId,
            'join_url' => $joinUrl
        ];
    }

    /**
     * Delete a Zoom meeting
     */
    public static function deleteZoomMeeting($userId, $zoomMeetingId) {
        $token = self::getZoomAccessToken($userId);
        if (!$token || !$zoomMeetingId) return false;

        $url = 'https://api.zoom.us/v2/meetings/' . $zoomMeetingId;
        $headers = [
            'Authorization' => 'Bearer ' . $token
        ];

        $res = self::makeCurlRequest($url, 'DELETE', null, $headers);
        return ($res['code'] === 204 || $res['code'] === 404);
    }
}
