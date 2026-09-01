<?php
// backend/api/crm/email_campaigns.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../smtp_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

// Ensure DB tables exist
function ensureEmailCampaignsTablesExist($db) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `email_campaigns` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `campaign_name` VARCHAR(255) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `body_html` LONGTEXT NOT NULL,
                `total_recipients` INT DEFAULT 0,
                `sent_count` INT DEFAULT 0,
                `failed_count` INT DEFAULT 0,
                `batch_size` INT DEFAULT 10,
                `interval_minutes` INT DEFAULT 10,
                `status` VARCHAR(50) DEFAULT 'Scheduled',
                `start_at` DATETIME NULL,
                `estimated_end_at` DATETIME NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (`user_id`),
                INDEX (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `email_campaign_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `campaign_id` INT NOT NULL,
                `recipient_email` VARCHAR(255) NOT NULL,
                `variable_data_json` LONGTEXT NULL,
                `status` VARCHAR(50) DEFAULT 'Pending',
                `error_message` TEXT NULL,
                `scheduled_at` DATETIME NULL,
                `sent_at` DATETIME NULL,
                INDEX (`campaign_id`),
                INDEX (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {
        error_log("Table creation error: " . $e->getMessage());
    }
}

ensureEmailCampaignsTablesExist($db);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && !empty($action)) {
    $method = strtoupper($action);
}

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $campId = (int)$_GET['id'];
            $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE id = ? AND user_id = ?");
            $stmt->execute([$campId, $userId]);
            $campaign = $stmt->fetch();
            if (!$campaign) {
                sendJsonResponse('error', 'Campaign not found.', [], 404);
            }
            
            $stmtLogs = $db->prepare("
                SELECT 
                    l.*,
                    t.first_opened_at,
                    t.last_opened_at,
                    t.open_count,
                    t.ip_address,
                    t.device,
                    t.browser,
                    t.os,
                    t.country,
                    t.city,
                    t.is_google_proxy,
                    t.is_apple_privacy
                FROM email_campaign_logs l
                LEFT JOIN email_tracking t ON l.id = t.campaign_log_id
                WHERE l.campaign_id = ?
                ORDER BY l.id ASC
            ");
            $stmtLogs->execute([$campId]);
            $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            $logIds = array_column($logs, 'id');
            $eventsMap = [];
            $linkAnalytics = [];

            if (!empty($logIds)) {
                $inQuery = implode(',', array_fill(0, count($logIds), '?'));

                // Fetch activity events
                $stmtEvts = $db->prepare("
                    SELECT * FROM email_activity_events
                    WHERE campaign_log_id IN ({$inQuery})
                    ORDER BY created_at ASC, id ASC
                ");
                $stmtEvts->execute($logIds);
                $allEvts = $stmtEvts->fetchAll(PDO::FETCH_ASSOC);
                foreach ($allEvts as $evt) {
                    $eventsMap[$evt['campaign_log_id']][] = $evt;
                }

                // Fetch link click analytics
                $stmtLinks = $db->prepare("
                    SELECT 
                        link_url, 
                        COUNT(*) as total_clicks, 
                        COUNT(DISTINCT campaign_log_id) as unique_clicks 
                    FROM email_click_tracking 
                    WHERE campaign_log_id IN ({$inQuery}) 
                    GROUP BY link_url 
                    ORDER BY total_clicks DESC
                ");
                $stmtLinks->execute($logIds);
                $linkAnalytics = $stmtLinks->fetchAll(PDO::FETCH_ASSOC);
            }

            // Attach events timeline to each log
            foreach ($logs as &$logRow) {
                $logRow['timeline'] = $eventsMap[$logRow['id']] ?? [];
            }
            unset($logRow);

            $campaign['logs'] = $logs;
            $campaign['link_analytics'] = $linkAnalytics;
            
            sendJsonResponse('success', 'Campaign details loaded.', ['campaign' => $campaign]);
        } else {
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    COALESCE(SUM(CASE WHEN t.open_count > 0 THEN 1 ELSE 0 END), 0) AS opens_count,
                    COALESCE(SUM(CASE WHEN cl.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS clicks_count,
                    COALESCE(SUM(CASE WHEN l.is_replied = 1 THEN 1 ELSE 0 END), 0) AS replies_count,
                    COALESCE(SUM(CASE WHEN l.is_bounced = 1 OR l.status = 'Failed' THEN 1 ELSE 0 END), 0) AS bounces_count
                FROM email_campaigns c
                LEFT JOIN email_campaign_logs l ON c.id = l.campaign_id
                LEFT JOIN email_tracking t ON l.id = t.campaign_log_id
                LEFT JOIN (
                    SELECT DISTINCT campaign_log_id FROM email_click_tracking
                ) cl ON l.id = cl.campaign_log_id
                WHERE c.user_id = ?
                GROUP BY c.id
                ORDER BY c.id DESC
            ");
            $stmt->execute([$userId]);
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Email campaigns list loaded.', ['campaigns' => $campaigns]);
        }
    }
    
    elseif ($action === 'mark_event') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $logId = (int)($input['log_id'] ?? 0);
        $eventType = trim($input['event_type'] ?? '');

        if (!$logId || empty($eventType)) {
            sendJsonResponse('error', 'Log ID and event type required.', [], 400);
        }

        $stmtLog = $db->prepare("SELECT l.*, c.user_id FROM email_campaign_logs l JOIN email_campaigns c ON l.campaign_id = c.id WHERE l.id = ? AND c.user_id = ?");
        $stmtLog->execute([$logId, $userId]);
        $log = $stmtLog->fetch();
        if (!$log) sendJsonResponse('error', 'Log record not found.', [], 404);

        if ($eventType === 'Replied') {
            $db->prepare("UPDATE email_campaign_logs SET is_replied = 1, replied_at = NOW() WHERE id = ?")->execute([$logId]);
            $evtLabel = 'Recipient replied to email';
        } elseif ($eventType === 'MeetingBooked') {
            $db->prepare("UPDATE email_campaign_logs SET is_meeting_booked = 1, meeting_booked_at = NOW() WHERE id = ?")->execute([$logId]);
            $evtLabel = 'Meeting booked with recipient';
        } elseif ($eventType === 'Bounced') {
            $db->prepare("UPDATE email_campaign_logs SET is_bounced = 1, status = 'Failed', error_message = 'Hard bounce detected' WHERE id = ?")->execute([$logId]);
            $evtLabel = 'Email bounced';
        } elseif ($eventType === 'Unsubscribed') {
            $db->prepare("UPDATE email_campaign_logs SET is_unsubscribed = 1 WHERE id = ?")->execute([$logId]);
            $evtLabel = 'Unsubscribed from campaign';
        } else {
            sendJsonResponse('error', 'Invalid event type.', [], 400);
        }

        $db->prepare("
            INSERT INTO email_activity_events (campaign_log_id, event_type, event_label, created_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([$logId, $eventType, $evtLabel]);

        sendJsonResponse('success', "Event '{$eventType}' marked successfully.");
    }
    
    elseif ($method === 'POST' || $method === 'CREATE') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $campaignName = trim($input['campaign_name'] ?? 'Email Campaign ' . date('Y-m-d H:i'));
        $subject = trim($input['subject'] ?? 'Outbound Email');
        $bodyHtml = trim($input['body_html'] ?? '');
        $recipients = $input['recipients'] ?? []; // Array of { email: string, variables: {} }
        $batchSize = (int)($input['batch_size'] ?? 10);
        $intervalMinutes = (int)($input['interval_minutes'] ?? 10);
        $startAtStr = trim($input['start_at'] ?? '');
        
        if (empty($bodyHtml)) {
            sendJsonResponse('error', 'Mail body HTML cannot be empty.', [], 400);
        }
        
        if (empty($recipients) || !is_array($recipients)) {
            sendJsonResponse('error', 'At least one valid recipient is required.', [], 400);
        }
        
        $totalRecipients = count($recipients);
        $startAt = !empty($startAtStr) ? date('Y-m-d H:i:s', strtotime($startAtStr)) : date('Y-m-d H:i:s');
        
        // Calculate estimated completion end time
        $totalBatches = ceil($totalRecipients / max(1, $batchSize));
        $totalMinutesNeeded = max(0, ($totalBatches - 1) * $intervalMinutes);
        $estimatedEndAt = date('Y-m-d H:i:s', strtotime($startAt) + ($totalMinutesNeeded * 60));
        
        // Insert Campaign
        $stmtIns = $db->prepare("
            INSERT INTO email_campaigns (user_id, campaign_name, subject, body_html, total_recipients, batch_size, interval_minutes, status, start_at, estimated_end_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?)
        ");
        $stmtIns->execute([$userId, $campaignName, $subject, $bodyHtml, $totalRecipients, $batchSize, $intervalMinutes, $startAt, $estimatedEndAt]);
        $campaignId = $db->lastInsertId();
        
        // Insert Recipient Logs & Schedule Batch Timestamps
        $stmtLog = $db->prepare("
            INSERT INTO email_campaign_logs (campaign_id, recipient_email, variable_data_json, status, scheduled_at)
            VALUES (?, ?, ?, 'Pending', ?)
        ");
        
        $batchIdx = 0;
        foreach ($recipients as $idx => $r) {
            $email = trim($r['email'] ?? '');
            if (empty($email)) continue;
            
            $varJson = isset($r['variables']) ? json_encode($r['variables']) : null;
            
            // Calculate scheduled timestamp for this batch
            if ($idx > 0 && ($idx % $batchSize === 0)) {
                $batchIdx++;
            }
            $schedTime = date('Y-m-d H:i:s', strtotime($startAt) + ($batchIdx * $intervalMinutes * 60));
            
            $stmtLog->execute([$campaignId, $email, $varJson, $schedTime]);
        }
        
        // Process first batch immediately if start time is now
        if (strtotime($startAt) <= time()) {
            processEmailCampaignBatch($db, $userId, $campaignId, $batchSize);
        }
        
        sendJsonResponse('success', 'Email Campaign successfully created and launched!', [
            'campaign_id' => $campaignId,
            'total_recipients' => $totalRecipients,
            'start_at' => $startAt,
            'estimated_end_at' => $estimatedEndAt
        ]);
    }
    
    elseif ($method === 'PAUSE' || $action === 'toggle_pause') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $campId = (int)($input['campaign_id'] ?? 0);
        
        $stmt = $db->prepare("SELECT status FROM email_campaigns WHERE id = ? AND user_id = ?");
        $stmt->execute([$campId, $userId]);
        $c = $stmt->fetch();
        if (!$c) sendJsonResponse('error', 'Campaign not found.', [], 404);
        
        $newStatus = ($c['status'] === 'Active' || $c['status'] === 'Scheduled') ? 'Paused' : 'Active';
        $db->prepare("UPDATE email_campaigns SET status = ? WHERE id = ?")->execute([$newStatus, $campId]);
        
        sendJsonResponse('success', "Campaign status updated to {$newStatus}.", ['status' => $newStatus]);
    }
    
    elseif ($method === 'DELETE' || $action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $campId = (int)($input['campaign_id'] ?? $_GET['id'] ?? 0);
        
        $db->prepare("DELETE FROM email_campaign_logs WHERE campaign_id = ?")->execute([$campId]);
        $db->prepare("DELETE FROM email_campaigns WHERE id = ? AND user_id = ?")->execute([$campId, $userId]);
        
        sendJsonResponse('success', 'Email Campaign deleted successfully.');
    }

} catch (Exception $e) {
    sendJsonResponse('error', $e->getMessage(), [], 500);
}

// Helper to dispatch email batch
function processEmailCampaignBatch($db, $userId, $campaignId, $limit = 10) {
    $stmtC = $db->prepare("SELECT * FROM email_campaigns WHERE id = ? AND status = 'Active'");
    $stmtC->execute([$campaignId]);
    $camp = $stmtC->fetch();
    if (!$camp) return;
    
    $stmtLogs = $db->prepare("
        SELECT * FROM email_campaign_logs 
        WHERE campaign_id = ? AND status = 'Pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW())
        LIMIT ?
    ");
    $stmtLogs->bindValue(1, $campaignId, PDO::PARAM_INT);
    $stmtLogs->bindValue(2, (int)$limit, PDO::PARAM_INT);
    $stmtLogs->execute();
    $pending = $stmtLogs->fetchAll();
    
    if (empty($pending)) {
        // Check if all sent
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM email_campaign_logs WHERE campaign_id = ? AND status = 'Pending'");
        $stmtCheck->execute([$campaignId]);
        if ((int)$stmtCheck->fetchColumn() === 0) {
            $db->prepare("UPDATE email_campaigns SET status = 'Completed' WHERE id = ?")->execute([$campaignId]);
        }
        return;
    }
    
    $sentIncrement = 0;
    $failedIncrement = 0;
    
    foreach ($pending as $log) {
        $email = $log['recipient_email'];
        $vars = !empty($log['variable_data_json']) ? json_decode($log['variable_data_json'], true) : [];
        
        // Inject Subject Variables
        $subject = $camp['subject'];
        $body = $camp['body_html'];
        
        if (!empty($vars) && is_array($vars)) {
            foreach ($vars as $k => $v) {
                $placeholder = '{' . $k . '}';
                $valStr = (string)$v;
                $subject = str_replace($placeholder, $valStr, $subject);
                $body = str_replace($placeholder, $valStr, $body);
            }
        }
        
        // Send email via SMTPHelper
        try {
            $res = SMTPHelper::sendEmail($userId, $email, $subject, $body, [], null, null, [], $log['id']);
            if ($res && !empty($res['status'])) {
                $db->prepare("UPDATE email_campaign_logs SET status = 'Sent', sent_at = NOW() WHERE id = ?")->execute([$log['id']]);
                $sentIncrement++;
            } else {
                $errMsg = $res['message'] ?? 'Sending failed';
                $db->prepare("UPDATE email_campaign_logs SET status = 'Failed', error_message = ? WHERE id = ?")->execute([$errMsg, $log['id']]);
                $failedIncrement++;
            }
        } catch (Exception $e) {
            $db->prepare("UPDATE email_campaign_logs SET status = 'Failed', error_message = ? WHERE id = ?")->execute([$e->getMessage(), $log['id']]);
            $failedIncrement++;
        }
    }
    
    // Update campaign counters
    $db->prepare("
        UPDATE email_campaigns 
        SET sent_count = sent_count + ?, failed_count = failed_count + ?
        WHERE id = ?
    ")->execute([$sentIncrement, $failedIncrement, $campaignId]);
}
