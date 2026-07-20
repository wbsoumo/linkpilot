<?php
// backend/imap_helper.php

require_once __DIR__ . '/config.php';

class IMAPHelper {

    /**
     * Build IMAP connection string based on host, port and encryption
     */
    private static function getConnectionString($host, $port, $encryption) {
        $connectionString = "{" . $host . ":" . $port . "/imap";
        if ($encryption === 'ssl') {
            $connectionString .= "/ssl/novalidate-cert";
        } elseif ($encryption === 'tls') {
            $connectionString .= "/tls/novalidate-cert";
        } else {
            $connectionString .= "/novalidate-cert";
        }
        $connectionString .= "}";
        return $connectionString;
    }

    /**
     * Test IMAP connection configurations
     */
    public static function testConnection($host, $port, $username, $password, $encryption) {
        if (function_exists('imap_open')) {
            $connectionString = self::getConnectionString($host, $port, $encryption) . "INBOX";
            @imap_timeout(IMAP_OPENTIMEOUT, 4);
            $mbox = @imap_open($connectionString, $username, $password, OP_HALFOPEN, 1, [
                'DISABLE_AUTHENTICATOR' => 'GSSAPI'
            ]);

            if ($mbox) {
                @imap_close($mbox);
                return [
                    "status" => true,
                    "message" => "IMAP Connection Test Successful."
                ];
            }
        }

        // Try direct socket connection
        $prefix = (strtolower($encryption) === 'ssl' || (int)$port === 993) ? 'ssl://' : '';
        $fp = @fsockopen($prefix . $host, (int)$port, $errno, $errstr, 4);
        if ($fp) {
            fclose($fp);
            return [
                "status" => true,
                "message" => "IMAP Connection Test Successful! Port " . $port . " verified."
            ];
        }

        // Fallback to AWS Proxy Worker
        $proxyRes = SMTPHelper::callAwsProxyWorker('test_imap', [
            'imap_host' => $host,
            'imap_port' => (int)$port,
            'imap_username' => $username,
            'imap_password' => $password,
            'imap_encryption' => $encryption
        ]);

        if ($proxyRes !== null) {
            return $proxyRes;
        }

        $errors = function_exists('imap_errors') ? imap_errors() : null;
        $errorMsg = $errors ? implode(", ", $errors) : ($errstr ?: "Unable to connect to IMAP server.");
        return [
            "status" => false,
            "message" => "IMAP Connection Failed: " . $errorMsg
        ];
    }

    /**
     * Fetch unseen emails from IMAP
     */
    public static function fetchNewEmails($userId, $limit = 20) {
        if (!function_exists('imap_open')) {
            throw new Exception("PHP IMAP extension is not enabled on this server.");
        }

        $db = Database::getConnection();

        // 1. Fetch IMAP config
        $stmt = $db->prepare("SELECT * FROM imap_smtp_configurations WHERE user_id = ?");
        $stmt->execute([$userId]);
        $config = $stmt->fetch();

        if (!$config || empty($config['imap_host']) || empty($config['imap_username'])) {
            throw new Exception("IMAP account is not configured. Please complete the setup wizard.");
        }

        // 2. Decrypt Password
        $decryptedPassword = decryptData($config['imap_password']);
        if ($decryptedPassword === false) {
            throw new Exception("Failed to decrypt IMAP credentials. Please update settings.");
        }

        $connectionString = self::getConnectionString($config['imap_host'], $config['imap_port'], $config['imap_encryption']) . "INBOX";
        @imap_timeout(IMAP_OPENTIMEOUT, 10);
        $mbox = @imap_open($connectionString, $config['imap_username'], $decryptedPassword);

        if (!$mbox) {
            $errors = imap_errors();
            $errorMsg = $errors ? implode(", ", $errors) : "Connection failed.";
            throw new Exception("IMAP Server connection failed: " . $errorMsg);
        }

        // 3. Search for recent messages
        // Since we compare message_id to prevent duplicates, we can fetch the last N messages
        $numMessages = imap_num_msg($mbox);
        if ($numMessages === 0) {
            @imap_close($mbox);
            return [];
        }

        $emails = [];
        $start = max(1, $numMessages - $limit + 1);
        
        for ($i = $numMessages; $i >= $start; $i--) {
            $header = @imap_headerinfo($mbox, $i);
            if (!$header) continue;

            $messageId = $header->message_id ?? '';
            if (empty($messageId)) {
                $messageId = md5(($header->date ?? '') . ($header->subject ?? '') . ($header->fromaddress ?? ''));
            }

            // Check if email already exists in DB
            $stmtCheck = $db->prepare("SELECT id FROM received_emails WHERE user_id = ? AND message_id = ?");
            $stmtCheck->execute([$userId, $messageId]);
            if ($stmtCheck->fetch()) {
                continue; // already synced
            }

            $subject = isset($header->subject) ? self::decodeMimeHeader($header->subject) : '(No Subject)';
            $senderName = '';
            $senderEmail = '';
            
            if (isset($header->from[0])) {
                $from = $header->from[0];
                $senderEmail = ($from->mailbox ?? '') . '@' . ($from->host ?? '');
                $senderName = isset($from->personal) ? self::decodeMimeHeader($from->personal) : $senderEmail;
            }

            $recipientEmail = '';
            if (isset($header->to[0])) {
                $to = $header->to[0];
                $recipientEmail = ($to->mailbox ?? '') . '@' . ($to->host ?? '');
            }

            $date = isset($header->date) ? date('Y-m-d H:i:s', strtotime($header->date)) : date('Y-m-d H:i:s');

            // Fetch Body
            $structure = imap_fetchstructure($mbox, $i);
            $bodies = self::getBodies($mbox, $i, $structure);
            
            // Extract Attachments
            $attachments = self::getAttachments($mbox, $i, $structure);

            $emails[] = [
                'message_id' => $messageId,
                'sender_email' => $senderEmail,
                'sender_name' => $senderName,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'body_text' => $bodies['text'],
                'body_html' => $bodies['html'],
                'received_date' => $date,
                'attachments' => $attachments
            ];
        }

        @imap_close($mbox);
        return $emails;
    }

    /**
     * Decode MIME headers (e.g. UTF-8 or ISO-8859-1 strings)
     */
    private static function decodeMimeHeader($str) {
        $decoded = @imap_mime_header_decode($str);
        if (!$decoded) {
            return $str;
        }
        $result = "";
        foreach ($decoded as $element) {
            $text = $element->text;
            $charset = $element->charset;
            if ($charset !== "default" && !empty($charset)) {
                if (function_exists('mb_convert_encoding')) {
                    $text = @mb_convert_encoding($text, "UTF-8", $charset);
                } elseif (function_exists('iconv')) {
                    $text = @iconv($charset, "UTF-8//IGNORE", $text);
                }
            }
            $result .= $text;
        }
        return $result;
    }

    /**
     * Parse body text and HTML
     */
    private static function getBodies($mbox, $msgNum, $structure) {
        $bodies = ['text' => '', 'html' => ''];
        if (!$structure || !isset($structure->type)) {
            return $bodies;
        }
        
        if ($structure->type == 0) { // Primary text format
            $body = @imap_fetchbody($mbox, $msgNum, 1);
            $body = self::decodeBody($body, $structure->encoding ?? 0);
            if (strtolower($structure->subtype ?? '') == 'plain') {
                $bodies['text'] = $body;
            } else {
                $bodies['html'] = $body;
            }
        } elseif ($structure->type == 1 && isset($structure->parts) && is_array($structure->parts)) { // Multipart
            foreach ($structure->parts as $partNum => $part) {
                self::parseMultipartBody($mbox, $msgNum, $part, ($partNum + 1), $bodies);
            }
        }
        
        return $bodies;
    }

    private static function parseMultipartBody($mbox, $msgNum, $part, $sectionNumber, &$bodies) {
        if (!$part || !isset($part->type)) return;
        
        if ($part->type == 0) {
            $body = @imap_fetchbody($mbox, $msgNum, $sectionNumber);
            $body = self::decodeBody($body, $part->encoding ?? 0);
            if (strtolower($part->subtype ?? '') == 'plain') {
                $bodies['text'] = $body;
            } else {
                $bodies['html'] = $body;
            }
        } elseif ($part->type == 1 && isset($part->parts) && is_array($part->parts)) {
            foreach ($part->parts as $partNum => $subpart) {
                self::parseMultipartBody($mbox, $msgNum, $subpart, $sectionNumber . '.' . ($partNum + 1), $bodies);
            }
        }
    }

    /**
     * Decode encoded text bodies (base64, quoted-printable)
     */
    private static function decodeBody($body, $encoding) {
        if ($encoding == 3) {
            return base64_decode($body);
        } elseif ($encoding == 4) {
            return quoted_printable_decode($body);
        }
        return $body;
    }

    /**
     * Retrieve list of attachments metadata
     */
    private static function getAttachments($mbox, $msgNum, $structure) {
        $attachments = [];
        if (!$structure) {
            return $attachments;
        }
        
        if (isset($structure->parts) && is_array($structure->parts)) {
            for ($i = 0; $i < count($structure->parts); $i++) {
                $part = $structure->parts[$i];
                $partNumber = $i + 1;
                
                // An attachment can be indicated by disposition = ATTACHMENT or inline
                $isAttachment = false;
                $filename = "";

                if ($part->ifdisposition) {
                    if (in_array(strtolower($part->disposition), ['attachment', 'inline'])) {
                        $isAttachment = true;
                    }
                }

                // Check parameters for filename
                if ($part->ifparameters) {
                    foreach ($part->parameters as $object) {
                        if (strtolower($object->attribute) == 'name' || strtolower($object->attribute) == 'filename') {
                            $filename = self::decodeMimeHeader($object->value);
                            $isAttachment = true;
                        }
                    }
                }

                if ($part->ifdparameters) {
                    foreach ($part->dparameters as $object) {
                        if (strtolower($object->attribute) == 'filename' || strtolower($object->attribute) == 'name') {
                            $filename = self::decodeMimeHeader($object->value);
                            $isAttachment = true;
                        }
                    }
                }

                if ($isAttachment) {
                    if (empty($filename)) {
                        $filename = "attachment_msg_{$msgNum}_part_{$partNumber}";
                    }
                    
                    // Fetch attachment payload
                    $data = imap_fetchbody($mbox, $msgNum, $partNumber);
                    if ($part->encoding == 3) { // Base64
                        $data = base64_decode($data);
                    } elseif ($part->encoding == 4) { // Quoted-Printable
                        $data = quoted_printable_decode($data);
                    }

                    $attachments[] = [
                        'filename' => $filename,
                        'content' => $data,
                        'file_type' => isset($part->subtype) ? strtolower($part->subtype) : 'bin',
                        'file_size' => strlen($data)
                    ];
                }
            }
        }
        
        return $attachments;
    }
}
