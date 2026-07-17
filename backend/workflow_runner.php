<?php
// backend/workflow_runner.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/wallet_helper.php';
require_once __DIR__ . '/smtp_helper.php';
require_once __DIR__ . '/providers/whatsapp_meta_service.php';

class WorkflowRunner {

    /**
     * Execute a visual node workflow graph
     * 
     * @param int $userId
     * @param array $workflow The workflow row from database
     * @param array $context The event variables
     */
    public static function execute($userId, $workflow, $context = []) {
        $db = Database::getConnection();
        
        $actions = json_decode($workflow['actions_json'], true);
        if (!$actions || empty($actions['nodes'])) {
            return;
        }

        // Gather all nodes by ID
        $nodes = [];
        foreach ($actions['nodes'] as $n) {
            $nodes[$n['id']] = $n;
        }

        // Find starting trigger node
        $triggerNode = null;
        foreach ($nodes as $n) {
            $isTrigger = ($n['id'] === 'node-trigger' || 
                          ($n['category'] ?? '') === 'TRIGGERS' || 
                          $n['type'] === 'email_received' || 
                          $n['type'] === 'whatsapp_received');
            if ($isTrigger) {
                $triggerNode = $n;
                break;
            }
        }

        if (!$triggerNode) {
            return;
        }

        // Record execution log start
        $startTime = microtime(true);

        // Flatten context for dot-notation dynamic variable substitution
        $flatContext = [];
        $flatten = function($arr, $prefix = '') use (&$flatten, &$flatContext) {
            foreach ($arr as $k => $v) {
                if (is_array($v)) {
                    $flatten($v, $prefix . $k . '.');
                } else {
                    $flatContext[$prefix . $k] = $v;
                }
            }
        };
        $flatten($context);

        // Keep track of visited nodes to prevent cycles
        $visited = [];

        try {
            self::executeNode($userId, $triggerNode, $nodes, $actions['connections'] ?? [], $flatContext, $visited);
            
            $duration = microtime(true) - $startTime;
            
            // Log successful workflow execution
            $stmt = $db->prepare("INSERT INTO workflow_execution_logs (user_id, workflow_id, workflow_name, status, execution_time) VALUES (?, ?, ?, 'success', ?)");
            $stmt->execute([$userId, $workflow['id'], $workflow['name'], $duration]);
        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;
            
            // Log failed workflow execution
            $stmt = $db->prepare("INSERT INTO workflow_execution_logs (user_id, workflow_id, workflow_name, status, execution_time, error_message) VALUES (?, ?, ?, 'failed', ?, ?)");
            $stmt->execute([$userId, $workflow['id'], $workflow['name'], $duration, $e->getMessage()]);
        }
    }

    /**
     * Recursively traverse and execute nodes
     */
    private static function executeNode($userId, $node, $allNodes, $connections, &$context, &$visited) {
        if (in_array($node['id'], $visited)) {
            return; // Prevent infinite loops
        }
        $visited[] = $node['id'];

        $db = Database::getConnection();
        $type = $node['type'] ?? '';
        $config = $node['config'] ?? [];

        // Helper for variable replacement (Supports both {{var}} and {var} brackets)
        $replaceVars = function($txt) use (&$context, $db, $userId) {
            if (empty($txt)) return $txt;
            
            // Load contact details dynamically if referenced
            if ((strpos($txt, '{{contact.') !== false || strpos($txt, '{contact.') !== false) && !empty($context['contact_id'])) {
                $stmtC = $db->prepare("SELECT * FROM crm_contacts WHERE id = ? AND user_id = ?");
                $stmtC->execute([$context['contact_id'], $userId]);
                $cRow = $stmtC->fetch(PDO::FETCH_ASSOC);
                if ($cRow) {
                    foreach ($cRow as $ck => $cv) {
                        $context['contact.' . $ck] = $cv;
                    }
                }
            }
            
            // Load lead details dynamically if referenced
            if ((strpos($txt, '{{lead.') !== false || strpos($txt, '{lead.') !== false) && !empty($context['lead_id'])) {
                $stmtL = $db->prepare("SELECT * FROM crm_leads WHERE id = ? AND user_id = ?");
                $stmtL->execute([$context['lead_id'], $userId]);
                $lRow = $stmtL->fetch(PDO::FETCH_ASSOC);
                if ($lRow) {
                    foreach ($lRow as $lk => $lv) {
                        $context['lead.' . $lk] = $lv;
                    }
                }
            }

            foreach ($context as $key => $val) {
                if (is_scalar($val)) {
                    $txt = str_replace('{{' . $key . '}}', $val, $txt);
                    $txt = str_replace('{' . $key . '}', $val, $txt);
                }
            }
            return $txt;
        };

        $nextConnectionLabel = null; // Used for branching condition nodes

        switch ($type) {
            case 'create_lead':
                $leadName = $replaceVars($config['leadName'] ?? ($context['sender_name'] ?? 'New Lead'));
                $leadCompany = $replaceVars($config['company'] ?? ($context['company_name'] ?? ''));
                $leadBudget = (float)($config['budget'] ?? ($context['budget'] ?? 0.00));
                $leadPriority = $config['priority'] ?? 'medium';
                $leadSource = $config['source'] ?? 'Automation';

                $stmt = $db->prepare("INSERT INTO crm_leads (user_id, name, company, budget, priority, lead_source, stage) VALUES (?, ?, ?, ?, ?, ?, 'New')");
                $stmt->execute([$userId, $leadName, $leadCompany, $leadBudget, $leadPriority, $leadSource]);
                $newLeadId = $db->lastInsertId();
                $context['lead_id'] = $newLeadId;

                // Log to timeline
                self::logTimeline($db, $userId, 'Lead Created', "Lead '$leadName' was automatically created by visual workflow.", $newLeadId);
                break;

            case 'create_task':
                $taskTitle = $replaceVars($config['taskTitle'] ?? 'Follow up required');
                $taskDesc = $replaceVars($config['taskDesc'] ?? '');
                $dueDate = date('Y-m-d', strtotime('+' . ($config['dueDate'] ?? 2) . ' days'));
                $priority = $config['priority'] ?? 'medium';

                $leadId = $context['lead_id'] ?? null;
                $contactId = $context['contact_id'] ?? null;

                $stmt = $db->prepare("INSERT INTO crm_tasks (user_id, lead_id, contact_id, title, description, due_date, status, priority) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
                $stmt->execute([$userId, $leadId, $contactId, $taskTitle, $taskDesc, $dueDate, $priority]);
                $newTaskId = $db->lastInsertId();
                $context['task_id'] = $newTaskId;

                // Log to timeline
                self::logTimeline($db, $userId, 'Task Created', "Task '$taskTitle' (due $dueDate) was automatically scheduled by visual workflow.", $leadId, $contactId);
                break;

            case 'send_email':
                $to = $replaceVars($config['toEmail'] ?? ($context['sender_email'] ?? ''));
                $subject = $replaceVars($config['subject'] ?? 'Notification');
                $body = $replaceVars($config['body'] ?? '');

                if (!empty($to)) {
                    SMTPHelper::sendEmail($userId, $to, $subject, $body);
                    self::logTimeline($db, $userId, 'Email Outbound', "Automated email sent to $to: '$subject'", $context['lead_id'] ?? null, $context['contact_id'] ?? null);
                }
                break;

            case 'whatsapp_outbound':
                $to = $replaceVars($config['to'] ?? ($context['sender_phone'] ?? ''));
                $message = $replaceVars($config['message'] ?? '');
                $reminderOffset = $config['reminderOffset'] ?? 'None (Send Immediately)';

                // Clean phone number
                $toClean = preg_replace('/[^0-9]/', '', $to);

                if (!empty($toClean) && !empty($message)) {
                    // Fetch connected WhatsApp account
                    $stmtAcc = $db->prepare("SELECT access_token, phone_number_id FROM whatsapp_accounts WHERE user_id = ? AND status = 'connected' LIMIT 1");
                    $stmtAcc->execute([$userId]);
                    $acc = $stmtAcc->fetch();

                    if ($acc) {
                        $phoneNumberId = $acc['phone_number_id'];
                        
                        // Parse scheduled reminder date if configured
                        $scheduledAt = null;
                        if (!empty($reminderOffset) && $reminderOffset !== 'None (Send Immediately)' && !empty($context['meeting_start'])) {
                            $meetingStartTs = strtotime($context['meeting_start']);
                            
                            if ($reminderOffset === '5 minutes before meeting') {
                                $scheduledAt = date('Y-m-d H:i:s', $meetingStartTs - 300);
                            } elseif ($reminderOffset === '15 minutes before meeting') {
                                $scheduledAt = date('Y-m-d H:i:s', $meetingStartTs - 900);
                            } elseif ($reminderOffset === '30 minutes before meeting') {
                                $scheduledAt = date('Y-m-d H:i:s', $meetingStartTs - 1800);
                            } elseif ($reminderOffset === '1 hour before meeting') {
                                $scheduledAt = date('Y-m-d H:i:s', $meetingStartTs - 3600);
                            } elseif ($reminderOffset === '1 day before meeting') {
                                $scheduledAt = date('Y-m-d H:i:s', $meetingStartTs - 86400);
                            }
                        }

                        if ($scheduledAt !== null) {
                            // Queue the message with status 'pending' and correct scheduled_at timestamp
                            $payloadJson = json_encode(['body' => $message]);
                            $stmtQueue = $db->prepare("
                                INSERT INTO whatsapp_queue (user_id, phone_number_id, recipient_number, payload_json, type, status, scheduled_at)
                                VALUES (?, ?, ?, ?, 'text', 'pending', ?)
                            ");
                            $stmtQueue->execute([$userId, $phoneNumberId, $toClean, $payloadJson, $scheduledAt]);

                            self::logTimeline($db, $userId, 'WhatsApp Outbound', "Automated WhatsApp reminder queued for $toClean at $scheduledAt: " . substr($message, 0, 80), $context['lead_id'] ?? null, $context['contact_id'] ?? null);
                        } else {
                            // Send immediately
                            $encryptedToken = $acc['access_token'];
                            $decrypted = decryptData($encryptedToken);
                            $accessToken = ($decrypted !== false) ? $decrypted : $encryptedToken;
                            
                            $isMock = (strpos($accessToken, 'Mock') !== false || $accessToken === 'EAAGemini' || $accessToken === 'EAAGeminiTest');
                            $metaMsgId = '';
                            if (!$isMock) {
                                $res = WhatsAppMetaService::sendTextMessage($userId, $phoneNumberId, $toClean, $message, $accessToken);
                                $metaMsgId = $res['messages'][0]['id'] ?? 'wamid.' . uniqid();
                            } else {
                                $metaMsgId = 'wamid.MockAuto.' . uniqid();
                            }

                            // Log outbound message to database
                            $stmtExist = $db->prepare("SELECT id FROM whatsapp_contacts WHERE user_id = ? AND RIGHT(wa_id, 10) = RIGHT(?, 10) ORDER BY last_message_at DESC LIMIT 1");
                            $stmtExist->execute([$userId, $toClean]);
                            $waContactId = $stmtExist->fetchColumn();
                            
                            if (!$waContactId) {
                                $stmtInsWaCon = $db->prepare("INSERT INTO whatsapp_contacts (user_id, wa_id, profile_name, last_message_at, unread_count) VALUES (?, ?, ?, NOW(), 0)");
                                $stmtInsWaCon->execute([$userId, $toClean, 'WhatsApp Contact']);
                                $waContactId = $db->lastInsertId();
                            }
                            
                            $stmtInsMsg = $db->prepare("INSERT INTO whatsapp_messages (user_id, wa_contact_id, message_id, direction, type, body, status) VALUES (?, ?, ?, 'outbound', 'text', ?, 'sent')");
                            $stmtInsMsg->execute([$userId, $waContactId, $metaMsgId, $message]);
                            
                            $db->prepare("UPDATE whatsapp_contacts SET last_message_at = NOW() WHERE id = ?")->execute([$waContactId]);

                            self::logTimeline($db, $userId, 'WhatsApp Outbound', "Automated WhatsApp sent to $toClean: " . substr($message, 0, 80), $context['lead_id'] ?? null, $context['contact_id'] ?? null);
                        }
                    }
                }
                break;

            case 'condition':
            case 'if_branch':
                $left = $replaceVars($config['leftValue'] ?? '');
                $op = $config['operator'] ?? 'Equals';
                $right = $replaceVars($config['rightValue'] ?? '');

                $matched = false;
                if ($op === 'Equals') $matched = (strtolower($left) == strtolower($right));
                elseif ($op === 'Not Equals') $matched = (strtolower($left) != strtolower($right));
                elseif ($op === 'Contains') $matched = (stripos($left, $right) !== false);
                elseif ($op === 'Greater Than') $matched = ((float)$left > (float)$right);
                elseif ($op === 'Less Than') $matched = ((float)$left < (float)$right);
                elseif ($op === 'Exists') $matched = !empty($left);
                else $matched = false;

                $nextConnectionLabel = $matched ? 'Yes' : 'No';
                break;

            case 'add_tag':
                $tagName = $replaceVars($config['tagName'] ?? '');
                $leadId = $context['lead_id'] ?? null;
                
                if (!empty($tagName) && $leadId) {
                    // Update tags on lead
                    $stmtGet = $db->prepare("SELECT tags FROM crm_leads WHERE id = ? AND user_id = ?");
                    $stmtGet->execute([$leadId, $userId]);
                    $currTags = trim($stmtGet->fetchColumn() ?: '');
                    
                    $newTags = empty($currTags) ? $tagName : $currTags . ', ' . $tagName;
                    $db->prepare("UPDATE crm_leads SET tags = ? WHERE id = ?")->execute([$newTags, $leadId]);
                    
                    self::logTimeline($db, $userId, 'Lead Updated', "Added tag '$tagName' to lead.", $leadId);
                }
                break;

            default:
                // Other nodes are currently no-ops in backend execution
                break;
        }

        // Find next nodes following connections
        $outgoing = [];
        foreach ($connections as $conn) {
            $fromId = $conn['from'] ?? ($conn['fromId'] ?? '');
            $toId = $conn['to'] ?? ($conn['toId'] ?? '');
            $label = $conn['handle'] ?? ($conn['label'] ?? '');

            if ($fromId === $node['id']) {
                if ($nextConnectionLabel !== null) {
                    if (strtolower($label) === strtolower($nextConnectionLabel)) {
                        $outgoing[] = $toId;
                    }
                } else {
                    $outgoing[] = $toId;
                }
            }
        }

        // Execute subsequent nodes
        foreach ($outgoing as $nextId) {
            if (isset($allNodes[$nextId])) {
                self::executeNode($userId, $allNodes[$nextId], $allNodes, $connections, $context, $visited);
            }
        }
    }

    /**
     * Helper to log activities into CRM timeline
     */
    private static function logTimeline($db, $userId, $activityType, $description, $leadId = null, $contactId = null) {
        try {
            $stmt = $db->prepare("INSERT INTO crm_timeline (user_id, lead_id, contact_id, activity_type, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $leadId, $contactId, $activityType, $description]);
        } catch (Throwable $e) {
            // Ignore timeline logger errors to prevent halting execution
        }
    }
}
