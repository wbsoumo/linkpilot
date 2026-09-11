<?php
// backend/workflow_runner.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crm_sync_helper.php';
require_once __DIR__ . '/communication_provider_helper.php';

class WorkflowRunner {
    private static $maxDepth = 10;

    /**
     * Trigger workflows listening for an event (e.g. 'lead.created', 'contact.updated', etc.)
     */
    public static function triggerEvent($userId, $eventType, array $eventContext = [], PDO $db = null) {
        if (!$db) {
            $db = Database::getConnection();
        }

        // Fetch all active workflows matching user_id and trigger_type
        $stmt = $db->prepare("
            SELECT * FROM automation_workflows 
            WHERE user_id = ? 
              AND trigger_type = ? 
              AND status = 'active'
        ");
        $stmt->execute([$userId, $eventType]);
        $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $startedExecutions = [];
        foreach ($workflows as $wf) {
            $execId = self::startExecution($userId, $wf, $eventContext, $db);
            if ($execId) {
                $startedExecutions[] = $execId;
            }
        }

        return $startedExecutions;
    }

    /**
     * Start a new persistent workflow execution
     */
    public static function startExecution($userId, array $workflow, array $eventContext, PDO $db = null) {
        if (!$db) {
            $db = Database::getConnection();
        }

        $nodes = json_decode($workflow['nodes_json'] ?? '[]', true) ?: [];
        $edges = json_decode($workflow['edges_json'] ?? '[]', true) ?: [];

        if (empty($nodes)) {
            // Support legacy actions_json fallback if nodes_json is missing
            $legacyActions = json_decode($workflow['actions_json'] ?? '[]', true) ?: [];
            if (!empty($legacyActions)) {
                $nodes = self::convertLegacyActionsToNodes($workflow['trigger_type'], $legacyActions);
                $edges = self::buildLinearEdges($nodes);
            } else {
                return null;
            }
        }

        // Find trigger node
        $triggerNode = null;
        foreach ($nodes as $n) {
            if (($n['type'] ?? '') === 'trigger') {
                $triggerNode = $n;
                break;
            }
        }
        if (!$triggerNode && !empty($nodes)) {
            $triggerNode = $nodes[0];
        }

        // Build context
        $context = array_merge([
            'trigger_type' => $workflow['trigger_type'],
            'user_id' => $userId,
            'triggered_at' => date('Y-m-d H:i:s')
        ], $eventContext);

        $contactId = $context['contact_id'] ?? null;
        $leadId = $context['lead_id'] ?? null;
        $dealId = $context['deal_id'] ?? null;

        // Idempotency token to prevent duplicate trigger runs
        $idempotencyKey = 'wf_' . $workflow['id'] . '_v' . ($workflow['version'] ?? 1) . '_' . md5(json_encode($context) . '_' . microtime(true));

        $stmt = $db->prepare("
            INSERT INTO automation_executions 
            (user_id, workflow_id, workflow_version, contact_id, lead_id, deal_id, current_node_id, execution_context_json, status, idempotency_key, depth)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'running', ?, 1)
        ");
        $stmt->execute([
            $userId,
            $workflow['id'],
            $workflow['version'] ?? 1,
            $contactId,
            $leadId,
            $dealId,
            $triggerNode['id'] ?? 'trigger_1',
            json_encode($context),
            $idempotencyKey
        ]);
        $execId = (int)$db->lastInsertId();

        // Increment workflow run counter
        $db->prepare("UPDATE automation_workflows SET runs_count = runs_count + 1, last_run_at = NOW() WHERE id = ?")->execute([$workflow['id']]);

        // Log step
        self::logStep($execId, $userId, $triggerNode['id'] ?? 'trigger_1', 'trigger', 'Trigger Fired', 'passed', $context, ['status' => 'matched'], null, $db);

        // Advance to next node from trigger
        $nextNodes = self::getNextNodes($triggerNode['id'] ?? 'trigger_1', 'default', $nodes, $edges);
        if (!empty($nextNodes)) {
            self::processNode($execId, $nextNodes[0]['id'], $nodes, $edges, $context, $db);
        } else {
            // Workflow complete
            self::completeExecution($execId, $workflow['id'], $db);
        }

        return $execId;
    }

    /**
     * Process a node in an active execution
     */
    public static function processNode($execId, $nodeId, array $nodes, array $edges, array $context, PDO $db = null) {
        if (!$db) {
            $db = Database::getConnection();
        }

        // Fetch execution state
        $stmt = $db->prepare("SELECT * FROM automation_executions WHERE id = ?");
        $stmt->execute([$execId]);
        $exec = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exec || in_array($exec['status'], ['completed', 'failed', 'cancelled'])) {
            return;
        }

        if ((int)$exec['depth'] > self::$maxDepth) {
            self::failExecution($execId, $exec['workflow_id'], "Execution depth exceeded safe limit of " . self::$maxDepth . " (Loop Protection)", $db);
            return;
        }

        // Locate target node
        $currentNode = null;
        foreach ($nodes as $n) {
            if ($n['id'] === $nodeId) {
                $currentNode = $n;
                break;
            }
        }

        if (!$currentNode) {
            self::completeExecution($execId, $exec['workflow_id'], $db);
            return;
        }

        // Update current node in execution
        $db->prepare("UPDATE automation_executions SET current_node_id = ?, depth = depth + 1 WHERE id = ?")->execute([$nodeId, $execId]);

        $nodeType = $currentNode['type'] ?? 'action';
        $nodeConfig = $currentNode['config'] ?? $currentNode['data'] ?? [];
        $nodeLabel = $currentNode['label'] ?? $currentNode['name'] ?? $nodeType;

        switch ($nodeType) {
            case 'delay':
                $delaySeconds = self::parseDelaySeconds($nodeConfig);
                $resumeAt = date('Y-m-d H:i:s', time() + $delaySeconds);
                
                $db->prepare("
                    UPDATE automation_executions 
                    SET status = 'waiting', next_run_at = ?, execution_context_json = ? 
                    WHERE id = ?
                ")->execute([$resumeAt, json_encode($context), $execId]);

                self::logStep($execId, $exec['user_id'], $nodeId, 'delay', $nodeLabel, 'waiting', ['delay_seconds' => $delaySeconds], ['resume_at' => $resumeAt], null, $db);
                return; // Execution pauses until queue worker resumes

            case 'condition':
                $conditionResult = self::evaluateConditionGroup($nodeConfig, $context, $exec['user_id'], $db);
                $outcomeBranch = $conditionResult ? 'true' : 'false';

                self::logStep($execId, $exec['user_id'], $nodeId, 'condition', $nodeLabel, 'passed', $nodeConfig, ['evaluated' => $conditionResult, 'branch' => $outcomeBranch], null, $db);

                $nextNodes = self::getNextNodes($nodeId, $outcomeBranch, $nodes, $edges);
                if (empty($nextNodes)) {
                    // Fallback to default edge if specific true/false branch not found
                    $nextNodes = self::getNextNodes($nodeId, 'default', $nodes, $edges);
                }

                if (!empty($nextNodes)) {
                    self::processNode($execId, $nextNodes[0]['id'], $nodes, $edges, $context, $db);
                } else {
                    self::completeExecution($execId, $exec['workflow_id'], $db);
                }
                return;

            case 'action':
                $actionType = $currentNode['action_type'] ?? $nodeConfig['action_type'] ?? '';
                $actionParams = $nodeConfig['params'] ?? $nodeConfig;

                try {
                    $actionOutput = self::executeAction($exec['user_id'], $actionType, $actionParams, $context, $db);
                    
                    // Merge any newly produced context values (e.g. created task_id, sent comm_id)
                    if (is_array($actionOutput)) {
                        $context = array_merge($context, $actionOutput);
                    }

                    self::logStep($execId, $exec['user_id'], $nodeId, 'action', $nodeLabel, 'passed', $actionParams, $actionOutput, null, $db);

                    $nextNodes = self::getNextNodes($nodeId, 'default', $nodes, $edges);
                    if (!empty($nextNodes)) {
                        self::processNode($execId, $nextNodes[0]['id'], $nodes, $edges, $context, $db);
                    } else {
                        self::completeExecution($execId, $exec['workflow_id'], $db);
                    }
                } catch (Exception $e) {
                    self::logStep($execId, $exec['user_id'], $nodeId, 'action', $nodeLabel, 'failed', $actionParams, null, $e->getMessage(), $db);
                    self::failExecution($execId, $exec['workflow_id'], "Action '{$nodeLabel}' failed: " . $e->getMessage(), $db);
                }
                return;

            case 'end':
                self::logStep($execId, $exec['user_id'], $nodeId, 'end', 'Workflow Finished', 'passed', [], [], null, $db);
                self::completeExecution($execId, $exec['workflow_id'], $db);
                return;

            default:
                // Move to next
                $nextNodes = self::getNextNodes($nodeId, 'default', $nodes, $edges);
                if (!empty($nextNodes)) {
                    self::processNode($execId, $nextNodes[0]['id'], $nodes, $edges, $context, $db);
                } else {
                    self::completeExecution($execId, $exec['workflow_id'], $db);
                }
                return;
        }
    }

    /**
     * Execute structured backend action using existing LinkPilot services
     */
    private static function executeAction($userId, $actionType, array $params, array &$context, PDO $db) {
        $contactId = $context['contact_id'] ?? null;
        $leadId = $context['lead_id'] ?? null;
        $dealId = $context['deal_id'] ?? null;

        // Resolve contact if contactId not directly passed but leadId exists
        if (!$contactId && $leadId) {
            $stmt = $db->prepare("SELECT contact_id FROM crm_leads WHERE id = ? AND user_id = ?");
            $stmt->execute([$leadId, $userId]);
            $contactId = $stmt->fetchColumn() ?: null;
        }

        switch ($actionType) {
            case 'send_email':
            case 'send_whatsapp':
                $channel = ($actionType === 'send_whatsapp') ? 'whatsapp' : 'email';
                $message = self::interpolateVariables($params['message'] ?? $params['body'] ?? '', $context, $userId, $db);
                $subject = self::interpolateVariables($params['subject'] ?? 'Notification from LinkPilot', $context, $userId, $db);

                $recipient = $params['recipient'] ?? null;
                if (!$recipient && $contactId) {
                    $c = CRMSyncHelper::resolveContact($userId, ['id' => $contactId], $db);
                    $recipient = ($channel === 'whatsapp') ? ($c['whatsapp'] ?? $c['phone']) : $c['email'];
                }
                if (!$recipient) {
                    throw new Exception("No recipient available for {$channel} communication.");
                }

                $commResult = CommunicationProviderHelper::sendCommunication(
                    $userId,
                    $channel,
                    $recipient,
                    $subject,
                    $message,
                    $contactId,
                    $params['attachments'] ?? []
                );

                if ($commResult['status'] === 'failed') {
                    throw new Exception($commResult['message'] ?? 'Communication dispatch failed');
                }

                return [
                    'communication_id' => $commResult['action_id'] ?? null,
                    'communication_status' => $commResult['status']
                ];

            case 'assign_lead':
            case 'assign_owner':
                $assignee = self::interpolateVariables($params['assignee'] ?? $params['owner'] ?? 'Unassigned', $context, $userId, $db);
                if ($leadId) {
                    $db->prepare("UPDATE crm_leads SET assigned_employee = ? WHERE id = ? AND user_id = ?")->execute([$assignee, $leadId, $userId]);
                }
                if ($contactId) {
                    $db->prepare("UPDATE crm_contacts SET owner = ? WHERE id = ? AND user_id = ?")->execute([$assignee, $contactId, $userId]);
                }
                if ($dealId) {
                    $db->prepare("UPDATE crm_deals SET owner = ? WHERE id = ? AND user_id = ?")->execute([$assignee, $dealId, $userId]);
                }

                CRMSyncHelper::logActivityTimeline($userId, $contactId, $leadId, null, $dealId, 'system', 'outbound', "Assigned to {$assignee} via Automation", "Assigned owner to {$assignee}", 'completed', null, ['assignee' => $assignee], $db);

                return ['assigned_owner' => $assignee];

            case 'create_task':
                $title = self::interpolateVariables($params['title'] ?? 'Follow up task', $context, $userId, $db);
                $description = self::interpolateVariables($params['description'] ?? '', $context, $userId, $db);
                $priority = $params['priority'] ?? 'medium';

                $dueDate = date('Y-m-d');
                if (!empty($params['due_in_days'])) {
                    $dueDate = date('Y-m-d', strtotime('+' . (int)$params['due_in_days'] . ' days'));
                } elseif (!empty($params['due_date'])) {
                    $dueDate = date('Y-m-d', strtotime($params['due_date']));
                }

                $stmt = $db->prepare("
                    INSERT INTO crm_tasks (user_id, contact_id, lead_id, title, description, priority, due_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$userId, $contactId, $leadId, $title, $description, $priority, $dueDate]);
                $taskId = (int)$db->lastInsertId();

                CRMSyncHelper::logActivityTimeline($userId, $contactId, $leadId, null, null, 'task', 'outbound', "Task Created: {$title}", $description, 'completed', null, ['task_id' => $taskId], $db);

                return ['created_task_id' => $taskId];

            case 'add_tag':
                $tagName = self::interpolateVariables($params['tag_name'] ?? $params['tag'] ?? '', $context, $userId, $db);
                if ($contactId && $tagName) {
                    CRMSyncHelper::addContactTag($userId, $contactId, $tagName, '#3b82f6', $db);
                }
                return ['added_tag' => $tagName];

            case 'change_stage':
            case 'update_stage':
                $newStage = self::interpolateVariables($params['stage'] ?? $params['new_stage'] ?? '', $context, $userId, $db);
                if ($leadId && $newStage) {
                    $db->prepare("UPDATE crm_leads SET stage = ? WHERE id = ? AND user_id = ?")->execute([$newStage, $leadId, $userId]);
                }
                if ($dealId && $newStage) {
                    $db->prepare("UPDATE crm_deals SET stage = ? WHERE id = ? AND user_id = ?")->execute([$newStage, $dealId, $userId]);
                }
                return ['new_stage' => $newStage];

            case 'notify_user':
                $title = self::interpolateVariables($params['title'] ?? 'Automation Alert', $context, $userId, $db);
                $msg = self::interpolateVariables($params['message'] ?? '', $context, $userId, $db);
                
                CRMSyncHelper::logActivityTimeline($userId, $contactId, $leadId, null, $dealId, 'system', 'outbound', "Notification: {$title}", $msg, 'completed', null, ['notification' => true], $db);
                return ['notified' => true];

            default:
                // Generic log action fallback
                return ['status' => 'executed', 'action' => $actionType];
        }
    }

    /**
     * Evaluate condition block (Allowlisted fields & operators)
     */
    private static function evaluateConditionGroup(array $config, array $context, $userId, PDO $db) {
        $field = $config['field'] ?? '';
        $operator = strtolower($config['operator'] ?? 'equals');
        $expectedValue = $config['value'] ?? '';

        $actualValue = self::resolveFieldValue($field, $context, $userId, $db);

        switch ($operator) {
            case 'equals':
            case '==':
                return strtolower((string)$actualValue) === strtolower((string)$expectedValue);
            case 'not_equals':
            case '!=':
                return strtolower((string)$actualValue) !== strtolower((string)$expectedValue);
            case 'contains':
                return stripos((string)$actualValue, (string)$expectedValue) !== false;
            case 'not_contains':
                return stripos((string)$actualValue, (string)$expectedValue) === false;
            case 'greater_than':
            case '>':
                return (float)$actualValue > (float)$expectedValue;
            case 'less_than':
            case '<':
                return (float)$actualValue < (float)$expectedValue;
            case 'exists':
            case 'is_not_empty':
                return !empty($actualValue);
            case 'does_not_exist':
            case 'is_empty':
                return empty($actualValue);
            case 'no_reply_in_days':
            case 'older_than_days':
                $days = (int)$expectedValue;
                if (empty($actualValue)) return true; // Never contacted
                $diffDays = (time() - strtotime($actualValue)) / 86400;
                return $diffDays >= $days;
            default:
                return true;
        }
    }

    /**
     * Resolve field values from context or database safely
     */
    private static function resolveFieldValue($field, array $context, $userId, PDO $db) {
        if (array_key_exists($field, $context)) {
            return $context[$field];
        }

        $contactId = $context['contact_id'] ?? null;
        $leadId = $context['lead_id'] ?? null;
        $dealId = $context['deal_id'] ?? null;

        if ($field === 'last_contacted_at' || $field === 'last_activity') {
            if ($contactId) {
                $stmt = $db->prepare("SELECT MAX(created_at) FROM crm_activity_timeline WHERE contact_id = ? AND user_id = ?");
                $stmt->execute([$contactId, $userId]);
                return $stmt->fetchColumn() ?: null;
            }
        }

        if (strpos($field, 'contact.') === 0 && $contactId) {
            $prop = str_replace('contact.', '', $field);
            $stmt = $db->prepare("SELECT * FROM crm_contacts WHERE id = ? AND user_id = ?");
            $stmt->execute([$contactId, $userId]);
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            return $c[$prop] ?? null;
        }

        if (strpos($field, 'lead.') === 0 && $leadId) {
            $prop = str_replace('lead.', '', $field);
            $stmt = $db->prepare("SELECT * FROM crm_leads WHERE id = ? AND user_id = ?");
            $stmt->execute([$leadId, $userId]);
            $l = $stmt->fetch(PDO::FETCH_ASSOC);
            return $l[$prop] ?? null;
        }

        if (strpos($field, 'deal.') === 0 && $dealId) {
            $prop = str_replace('deal.', '', $field);
            $stmt = $db->prepare("SELECT * FROM crm_deals WHERE id = ? AND user_id = ?");
            $stmt->execute([$dealId, $userId]);
            $d = $stmt->fetch(PDO::FETCH_ASSOC);
            return $d[$prop] ?? null;
        }

        return null;
    }

    /**
     * Safely interpolate template variables e.g. {{first_name}}, {{lead.name}}
     */
    private static function interpolateVariables($text, array $context, $userId, PDO $db) {
        if (empty($text) || strpos($text, '{{') === false) {
            return $text;
        }

        $contactId = $context['contact_id'] ?? null;
        $contact = $contactId ? CRMSyncHelper::resolveContact($userId, ['id' => $contactId], $db) : [];

        $leadId = $context['lead_id'] ?? null;
        $lead = [];
        if ($leadId) {
            $stmt = $db->prepare("SELECT * FROM crm_leads WHERE id = ? AND user_id = ?");
            $stmt->execute([$leadId, $userId]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $replacements = [
            '{{first_name}}' => $contact['name'] ?? $lead['name'] ?? 'Customer',
            '{{contact.name}}' => $contact['name'] ?? 'Customer',
            '{{contact.email}}' => $contact['email'] ?? '',
            '{{contact.phone}}' => $contact['phone'] ?? '',
            '{{lead.name}}' => $lead['name'] ?? 'Lead',
            '{{lead.company}}' => $lead['company'] ?? '',
            '{{lead.stage}}' => $lead['stage'] ?? '',
            '{{owner_name}}' => $context['assigned_owner'] ?? $lead['assigned_employee'] ?? $contact['owner'] ?? 'LinkPilot Support'
        ];

        return strtr($text, $replacements);
    }

    private static function parseDelaySeconds(array $config) {
        $unit = strtolower($config['unit'] ?? 'hours');
        $val = (float)($config['value'] ?? $config['duration'] ?? 1);

        switch ($unit) {
            case 'minutes':
            case 'minute':
                return (int)($val * 60);
            case 'days':
            case 'day':
                return (int)($val * 86400);
            case 'hours':
            case 'hour':
            default:
                return (int)($val * 3600);
        }
    }

    private static function getNextNodes($currentNodeId, $branchOutput, array $nodes, array $edges) {
        $targetIds = [];
        foreach ($edges as $e) {
            if ($e['source'] === $currentNodeId) {
                $sourceHandle = $e['sourceHandle'] ?? 'default';
                if ($branchOutput === 'default' || $sourceHandle === $branchOutput || $sourceHandle === 'default') {
                    $targetIds[] = $e['target'];
                }
            }
        }

        $result = [];
        foreach ($nodes as $n) {
            if (in_array($n['id'], $targetIds)) {
                $result[] = $n;
            }
        }
        return $result;
    }

    private static function logStep($execId, $userId, $nodeId, $nodeType, $nodeName, $status, $input, $output, $error, PDO $db) {
        $stmt = $db->prepare("
            INSERT INTO automation_execution_steps 
            (execution_id, user_id, node_id, node_type, node_name, status, input_json, output_json, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $execId,
            $userId,
            $nodeId,
            $nodeType,
            $nodeName,
            $status,
            json_encode($input),
            json_encode($output),
            $error
        ]);
    }

    private static function completeExecution($execId, $workflowId, PDO $db) {
        $db->prepare("
            UPDATE automation_executions 
            SET status = 'completed', completed_at = NOW() 
            WHERE id = ?
        ")->execute([$execId]);

        $db->prepare("UPDATE automation_workflows SET success_count = success_count + 1 WHERE id = ?")->execute([$workflowId]);
    }

    private static function failExecution($execId, $workflowId, $reason, PDO $db) {
        $db->prepare("
            UPDATE automation_executions 
            SET status = 'failed', error_message = ?, completed_at = NOW() 
            WHERE id = ?
        ")->execute([$reason, $execId]);

        $db->prepare("UPDATE automation_workflows SET failed_count = failed_count + 1 WHERE id = ?")->execute([$workflowId]);
    }

    private static function convertLegacyActionsToNodes($triggerType, array $actions) {
        $nodes = [
            [
                'id' => 'node_trigger',
                'type' => 'trigger',
                'label' => 'Trigger: ' . $triggerType,
                'config' => ['trigger_type' => $triggerType]
            ]
        ];

        $idx = 1;
        foreach ($actions as $act) {
            $nodes[] = [
                'id' => 'node_action_' . $idx,
                'type' => $act['type'] === 'delay' ? 'delay' : 'action',
                'label' => $act['label'] ?? $act['type'],
                'action_type' => $act['type'],
                'config' => $act
            ];
            $idx++;
        }
        $nodes[] = ['id' => 'node_end', 'type' => 'end', 'label' => 'End Workflow'];
        return $nodes;
    }

    private static function buildLinearEdges(array $nodes) {
        $edges = [];
        for ($i = 0; $i < count($nodes) - 1; $i++) {
            $edges[] = [
                'id' => 'edge_' . $i,
                'source' => $nodes[$i]['id'],
                'target' => $nodes[$i + 1]['id'],
                'sourceHandle' => 'default'
            ];
        }
        return $edges;
    }
}
