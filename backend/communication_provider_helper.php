<?php
// backend/communication_provider_helper.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp_helper.php';
require_once __DIR__ . '/providers/whatsapp_meta_service.php';
require_once __DIR__ . '/crm_sync_helper.php';
require_once __DIR__ . '/wallet_helper.php';

class CommunicationProviderHelper {

    /**
     * Validates if the requested channel integration is connected and authorized.
     */
    public static function validateConnection($userId, $channel) {
        $db = Database::getConnection();
        $channel = strtolower(trim($channel));

        if ($channel === 'email') {
            $smtpConfig = SMTPHelper::getSMTPConfig($userId);
            if ($smtpConfig) {
                return [
                    'connected' => true,
                    'channel' => 'email',
                    'provider' => $smtpConfig['host'] ?? 'Custom SMTP',
                    'sender' => $smtpConfig['sender_email'] ?? ''
                ];
            }

            // Check Gmail OAuth token
            $stmtGmail = $db->prepare("SELECT setting_value FROM external_app_settings WHERE user_id = ? AND app_name = 'google_gmail' AND setting_key = 'access_token' LIMIT 1");
            $stmtGmail->execute([$userId]);
            $gmailToken = $stmtGmail->fetchColumn();

            if (!empty($gmailToken)) {
                return [
                    'connected' => true,
                    'channel' => 'email',
                    'provider' => 'Google Gmail OAuth',
                    'sender' => 'Connected Gmail'
                ];
            }

            return [
                'connected' => false,
                'channel' => 'email',
                'error' => 'Email provider is not configured. Please set up your SMTP or Gmail integration in Settings.',
                'action_url' => 'setup.html?step=2'
            ];
        }

        if ($channel === 'whatsapp') {
            $stmtAcc = $db->prepare("SELECT phone_number_id, access_token, business_name FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
            $stmtAcc->execute([$userId]);
            $acc = $stmtAcc->fetch(PDO::FETCH_ASSOC);

            if ($acc) {
                return [
                    'connected' => true,
                    'channel' => 'whatsapp',
                    'provider' => 'Meta WhatsApp Cloud API',
                    'phone_number_id' => $acc['phone_number_id'],
                    'business_name' => $acc['business_name']
                ];
            }

            return [
                'connected' => false,
                'channel' => 'whatsapp',
                'error' => 'WhatsApp Cloud API is not connected to LinkPilot. Please connect your WhatsApp Business account in Settings.',
                'action_url' => 'setup.html?step=2'
            ];
        }

        if ($channel === 'sms') {
            return [
                'connected' => false,
                'channel' => 'sms',
                'error' => 'SMS Gateway (Twilio/Plivo) integration coming soon in next update.',
                'action_url' => '#/settings'
            ];
        }

        return [
            'connected' => false,
            'channel' => $channel,
            'error' => "Unsupported communication channel '$channel'."
        ];
    }

    /**
     * Executes communication send with strict Idempotency & Validation checks.
     */
    public static function sendCommunication($userId, $channel, $recipient, $subject, $message, $attachments = [], $idempotencyToken = null, $contactId = null, $db = null) {
        if (!$db) {
            $db = Database::getConnection();
        }

        $channel = strtolower(trim($channel));
        $recipient = trim($recipient);

        // 1. Idempotency Check: Prevent duplicate sends on double-click/retries
        if (!empty($idempotencyToken)) {
            $stmtIdem = $db->prepare("SELECT * FROM communication_actions WHERE idempotency_token = ? LIMIT 1");
            $stmtIdem->execute([$idempotencyToken]);
            $existingAction = $stmtIdem->fetch(PDO::FETCH_ASSOC);

            if ($existingAction && $existingAction['status'] === 'sent') {
                return [
                    'success' => true,
                    'idempotent_duplicate' => true,
                    'message' => "Communication already dispatched previously (Idempotency Token matched).",
                    'action_id' => $existingAction['id'],
                    'provider_response' => json_decode($existingAction['provider_response'] ?? '[]', true)
                ];
            }
        } else {
            $idempotencyToken = 'idem_' . uniqid() . '_' . bin2hex(random_bytes(4));
        }

        // 2. Provider Connection Validation
        $connStatus = self::validateConnection($userId, $channel);
        if (!$connStatus['connected']) {
            throw new Exception($connStatus['error']);
        }

        // 3. Create or Update Record in `communication_actions`
        $stmtIns = $db->prepare("
            INSERT INTO communication_actions (user_id, contact_id, channel, recipient, subject, message, attachments_json, status, idempotency_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'processing', ?)
            ON DUPLICATE KEY UPDATE status = 'processing', updated_at = NOW()
        ");
        $stmtIns->execute([
            $userId,
            $contactId ? (int)$contactId : null,
            $channel,
            $recipient,
            $subject,
            $message,
            !empty($attachments) ? json_encode($attachments) : null,
            $idempotencyToken
        ]);
        $actionId = $db->lastInsertId() ?: null;

        $executionResult = null;

        // 4. Provider Dispatch Execution
        if ($channel === 'email') {
            $sentRes = SMTPHelper::sendEmail($userId, $recipient, $subject, $message, $attachments);
            $isSuccess = !empty($sentRes['status']) || !empty($sentRes['success']);
            if (!$isSuccess) {
                $err = $sentRes['message'] ?? 'SMTP Dispatch Failed';
                $db->prepare("UPDATE communication_actions SET status = 'failed', provider_response = ? WHERE idempotency_token = ?")
                   ->execute([json_encode(['error' => $err]), $idempotencyToken]);
                throw new Exception("Email dispatch failed: " . $err);
            }
            $executionResult = $sentRes;
        } elseif ($channel === 'whatsapp') {
            $phoneNumberId = $connStatus['phone_number_id'];
            $stmtAccToken = $db->prepare("SELECT access_token FROM whatsapp_accounts WHERE phone_number_id = ? AND user_id = ? LIMIT 1");
            $stmtAccToken->execute([$phoneNumberId, $userId]);
            $encToken = $stmtAccToken->fetchColumn();

            $decrypted = decryptData($encToken);
            $accessToken = ($decrypted !== false) ? $decrypted : $encToken;

            $waRes = WhatsAppMetaService::sendTextMessage($userId, $phoneNumberId, $recipient, $message, $accessToken);
            $executionResult = $waRes;
        }

        // 5. Update Status to 'sent' & Persist Provider Response
        $db->prepare("UPDATE communication_actions SET status = 'sent', provider_response = ? WHERE idempotency_token = ?")
           ->execute([json_encode($executionResult), $idempotencyToken]);

        // 6. Log Event to crm_activity_timeline
        CRMSyncHelper::logActivity(
            $userId,
            $contactId,
            $channel,
            'outbound',
            "Sent " . ucfirst($channel) . (!empty($subject) ? ": \"$subject\"" : ": \"$message\""),
            [
                'recipient' => $recipient,
                'channel' => $channel,
                'idempotency_token' => $idempotencyToken,
                'attachments' => $attachments
            ],
            $db
        );

        return [
            'success' => true,
            'channel' => $channel,
            'recipient' => $recipient,
            'action_id' => $actionId,
            'idempotency_token' => $idempotencyToken,
            'provider_response' => $executionResult
        ];
    }

    /**
     * Schedules a communication for future execution via server-side queue.
     */
    public static function scheduleCommunication($userId, $channel, $recipient, $subject, $message, $scheduledAt, $attachments = [], $idempotencyToken = null, $contactId = null, $db = null) {
        if (!$db) {
            $db = Database::getConnection();
        }

        $channel = strtolower(trim($channel));
        $recipient = trim($recipient);

        // Validate Connection first
        $connStatus = self::validateConnection($userId, $channel);
        if (!$connStatus['connected']) {
            throw new Exception($connStatus['error']);
        }

        if (empty($idempotencyToken)) {
            $idempotencyToken = 'sched_' . uniqid() . '_' . bin2hex(random_bytes(4));
        }

        $stmtSched = $db->prepare("
            INSERT INTO scheduled_communications (user_id, contact_id, channel, recipient, subject, message, attachments_json, scheduled_at, status, idempotency_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
        ");
        $stmtSched->execute([
            $userId,
            $contactId ? (int)$contactId : null,
            $channel,
            $recipient,
            $subject,
            $message,
            !empty($attachments) ? json_encode($attachments) : null,
            $scheduledAt,
            $idempotencyToken
        ]);
        $schedId = $db->lastInsertId();

        // Also record in communication_actions as status 'scheduled'
        $db->prepare("
            INSERT INTO communication_actions (user_id, contact_id, channel, recipient, subject, message, attachments_json, status, idempotency_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
        ")->execute([
            $userId,
            $contactId ? (int)$contactId : null,
            $channel,
            $recipient,
            $subject,
            $message,
            !empty($attachments) ? json_encode($attachments) : null,
            $idempotencyToken
        ]);

        CRMSyncHelper::logActivity(
            $userId,
            $contactId,
            $channel,
            'system',
            "Scheduled " . ucfirst($channel) . " for " . date('M j, Y g:i A', strtotime($scheduledAt)),
            [
                'scheduled_at' => $scheduledAt,
                'recipient' => $recipient
            ],
            $db
        );

        return [
            'success' => true,
            'scheduled_id' => $schedId,
            'scheduled_at' => $scheduledAt,
            'idempotency_token' => $idempotencyToken
        ];
    }
}
