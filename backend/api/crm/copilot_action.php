<?php
// backend/api/crm/copilot_action.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../crm_sync_helper.php';
require_once __DIR__ . '/../../smtp_helper.php';
require_once __DIR__ . '/../../providers/whatsapp_meta_service.php';
require_once __DIR__ . '/../../communication_provider_helper.php';
require_once __DIR__ . '/../../wallet_helper.php';
require_once __DIR__ . '/../../ai_tool_registry.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];
$db = Database::getConnection();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: [];

$action = $input['action'] ?? ($_GET['action'] ?? 'parse');

try {
    if ($action === 'list_tools') {
        sendJsonResponse('success', 'Registered AI Tools list retrieved.', [
            'tools' => AIToolRegistry::listTools()
        ]);
    }

    elseif ($action === 'parse') {
        $prompt = trim($input['prompt'] ?? '');
        if (empty($prompt)) {
            sendJsonResponse('error', 'Prompt text cannot be empty.', [], 400);
        }

        // 1. Fetch workspace contacts for context matching
        $stmtCon = $db->prepare("SELECT id, name, email, phone, whatsapp, company_id FROM crm_contacts WHERE user_id = ? AND is_archived = 0 ORDER BY id DESC LIMIT 50");
        $stmtCon->execute([$userId]);
        $contacts = $stmtCon->fetchAll(PDO::FETCH_ASSOC);

        $contactsCtx = "";
        foreach ($contacts as $c) {
            $contactsCtx .= "- Contact ID {$c['id']}: {$c['name']} | Email: {$c['email']} | Phone: {$c['phone']}\n";
        }

        // 2. Fetch user profile/business details
        $stmtUser = $db->prepare("SELECT u.name, u.email, p.company_name FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $userProf = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];
        $senderName = $userProf['name'] ?? 'LinkPilot User';
        $companyName = $userProf['company_name'] ?? 'Our Company';

        $systemPrompt = "You are the LinkPilot AI Omnichannel Action Parser & AI Command Center Planner. Your task is to analyze natural language command prompts from $senderName (representing $companyName) and extract the intended workspace action into a strict JSON payload.

Supported action_type options:
1. 'SEND_EMAIL': Draft an email to a contact. (Risk: EXTERNAL)
2. 'SEND_WHATSAPP': Draft a WhatsApp message to a contact. (Risk: EXTERNAL)
3. 'CREATE_INVOICE': Draft an invoice for a contact/company. (Risk: WRITE)
4. 'SCHEDULE_MEETING': Schedule a meeting call and create a task. (Risk: WRITE)
5. 'CREATE_TASK': Create a task with due date/time and priority. (Risk: WRITE)
6. 'MOVE_DEAL': Update a deal stage (e.g. Lead, Qualified, Proposal, Negotiation, Closed Won, Closed Lost). (Risk: WRITE)
7. 'CREATE_CONTACT': Add a new contact to CRM. (Risk: WRITE)
8. 'UPDATE_CONTACT': Update contact details. (Risk: WRITE)
9. 'SEARCH_CONTACTS': Search or filter contacts. (Risk: READ)
10. 'ADD_NOTE': Add a note to a contact profile. (Risk: WRITE)
11. 'ADD_TAG': Add a tag to a contact. (Risk: WRITE)
12. 'MERGE_CONTACTS': Merge duplicate contacts. (Risk: WRITE)
13. 'CREATE_AUTOMATION': Build an automation workflow. (Risk: WRITE)
14. 'UPDATE_AUTOMATION': Modify an existing workflow definition. (Risk: WRITE)
15. 'PAUSE_AUTOMATION': Pause an active automation workflow. (Risk: WRITE)
16. 'RESUME_AUTOMATION': Resume/activate a workflow. (Risk: WRITE)
17. 'SEARCH_AUTOMATIONS': Find/query user automations. (Risk: READ)
18. 'ANALYZE_PIPELINE': Fetch backend sales analytics, revenue metrics, and lead distribution. (Risk: READ)
19. 'ARCHIVE_CONTACT': Archive contact record. (Risk: DESTRUCTIVE)

Return your response as a valid JSON object ONLY (no markdown fences around it) with this structure:
{
  \"action_type\": \"SEND_EMAIL|SEND_WHATSAPP|CREATE_INVOICE|SCHEDULE_MEETING|CREATE_TASK|MOVE_DEAL|CREATE_CONTACT|UPDATE_CONTACT|SEARCH_CONTACTS|ADD_NOTE|ADD_TAG|MERGE_CONTACTS\",
  \"target_contact\": {
    \"name\": \"...\",
    \"email\": \"...\",
    \"phone\": \"...\",
    \"company\": \"...\",
    \"designation\": \"...\",
    \"location\": \"...\",
    \"tag\": \"...\",
    \"note_text\": \"...\"
  },
  \"email_draft\": {
    \"subject\": \"...\",
    \"body\": \"...\"
  },
  \"whatsapp_draft\": {
    \"message\": \"...\"
  },
  \"invoice_draft\": {
    \"client_name\": \"...\",
    \"client_email\": \"...\",
    \"amount\": 0.00,
    \"currency\": \"INR|USD|EUR|GBP\",
    \"description\": \"...\",
    \"due_date\": \"YYYY-MM-DD\"
  },
  \"task_draft\": {
    \"title\": \"...\",
    \"description\": \"...\",
    \"due_date\": \"YYYY-MM-DD\",
    \"due_time\": \"HH:MM:SS\",
    \"priority\": \"high|medium|low\"
  },
  \"deal_draft\": {
    \"deal_title\": \"...\",
    \"target_stage\": \"Lead|Qualified|Proposal|Negotiation|Closed Won|Closed Lost\"
  },
  \"search_filters\": {
    \"q\": \"...\",
    \"city\": \"...\",
    \"company\": \"...\",
    \"tag\": \"...\",
    \"contact_type\": \"...\",
    \"not_contacted_days\": 0
  },
  \"scheduling\": {
    \"is_scheduled\": true|false,
    \"scheduled_at\": \"YYYY-MM-DD HH:MM:SS\" or null
  },
  \"requested_attachments\": [\"proposal\", \"quotation\", \"invoice\"],
  \"summary\": \"Brief human-readable summary of the action parsed\"
}

WORKSPACE CONTACTS LIST FOR MATCHING:
" . ($contactsCtx ?: "No contacts found yet.") . "
";

        $userPrompt = "User Command Prompt: \"$prompt\"";

        $ai = callAI($systemPrompt, $userPrompt, $userId);
        $aiData = json_decode($ai['text'], true);

        if (!$aiData || empty($aiData['action_type'])) {
            sendJsonResponse('error', 'AI could not parse command. Please rephrase your request.', [], 422);
        }

        // Audit Log AI Parsing Action
        $db->prepare("INSERT INTO ai_action_logs (user_id, prompt, intent, channel, action_status) VALUES (?, ?, ?, ?, 'parsed')")
           ->execute([$userId, $prompt, $aiData['action_type'], strtolower($aiData['action_type'])]);

        // Handle GET_HOT_LEADS intent
        if ($aiData['action_type'] === 'GET_HOT_LEADS') {
            $hotLeads = AIToolRegistry::executeTool('get_hot_leads', $userId, [], $db);
            sendJsonResponse('success', 'Hot leads retrieved successfully.', [
                'is_hot_leads_result' => true,
                'hot_leads' => $hotLeads,
                'parsed' => $aiData
            ]);
        }

        // Handle ANALYZE_PIPELINE intent
        if ($aiData['action_type'] === 'ANALYZE_PIPELINE') {
            $analytics = AIToolRegistry::executeTool('get_pipeline_summary', $userId, [], $db);
            sendJsonResponse('success', 'Pipeline analytics retrieved successfully.', [
                'is_analytics_result' => true,
                'analytics' => $analytics,
                'parsed' => $aiData
            ]);
        }

        // Handle SEARCH_CONTACTS intent
        if ($aiData['action_type'] === 'SEARCH_CONTACTS') {
            $filters = $aiData['search_filters'] ?? [];
            $results = CRMSyncHelper::searchContacts($userId, $filters, 20, 0, $db);

            sendJsonResponse('success', 'Search complete.', [
                'is_search_result' => true,
                'search_results' => $results,
                'parsed' => $aiData
            ]);
        }

        // Handle CREATE_CONTACT duplicate check
        if ($aiData['action_type'] === 'CREATE_CONTACT') {
            $target = $aiData['target_contact'] ?? [];
            $dups = CRMSyncHelper::findDuplicates(
                $userId,
                $target['email'] ?? null,
                $target['phone'] ?? null,
                $target['name'] ?? null,
                $target['company'] ?? null,
                $db
            );

            if (count($dups) > 0) {
                sendJsonResponse('success', 'Potential duplicate contacts detected.', [
                    'duplicate_found' => true,
                    'duplicates' => $dups,
                    'parsed' => $aiData
                ]);
            }
        }

        // 3. Ambiguity Resolution Check: Search DB if multiple contacts match specified name
        $target = $aiData['target_contact'] ?? [];
        $searchName = trim($target['name'] ?? '');

        if (!empty($searchName) && empty($target['email']) && empty($target['phone']) && $aiData['action_type'] !== 'CREATE_CONTACT') {
            $stmtMatches = $db->prepare("SELECT id, name, email, phone, whatsapp FROM crm_contacts WHERE user_id = ? AND (name LIKE ? OR email LIKE ?) AND is_archived = 0 LIMIT 5");
            $stmtMatches->execute([$userId, "%$searchName%", "%$searchName%"]);
            $matches = $stmtMatches->fetchAll(PDO::FETCH_ASSOC);

            if (count($matches) > 1) {
                // Ambiguous match: Return candidates list for Contact Picker UI
                sendJsonResponse('success', "Multiple contacts match '$searchName'. Please select one.", [
                    'ambiguous' => true,
                    'candidates' => $matches,
                    'parsed' => $aiData
                ]);
            }
        }

        // Resolve single contact
        $resolvedContact = CRMSyncHelper::resolveContact(
            $userId,
            !empty($target['email']) ? $target['email'] : null,
            !empty($target['phone']) ? $target['phone'] : null,
            !empty($target['name']) ? $target['name'] : null,
            $db
        );

        $aiData['matched_contact'] = $resolvedContact;

        // Attachment Matching
        $matchedAttachments = [];
        if (!empty($aiData['requested_attachments']) && is_array($aiData['requested_attachments'])) {
            $stmtVault = $db->prepare("SELECT name, post_url FROM lead_vault WHERE user_id = ? ORDER BY id DESC LIMIT 5");
            $stmtVault->execute([$userId]);
            $files = $stmtVault->fetchAll(PDO::FETCH_ASSOC);
            foreach ($files as $f) {
                if (!empty($f['name'])) {
                    $matchedAttachments[] = [
                        'name' => $f['name'],
                        'url' => $f['post_url'] ?? '#'
                    ];
                }
            }
        }
        $aiData['resolved_attachments'] = $matchedAttachments;

        sendJsonResponse('success', 'Command parsed successfully into actionable draft.', [
            'ambiguous' => false,
            'parsed' => $aiData
        ]);
    }

    elseif ($action === 'execute') {
        $actionType = $input['action_type'] ?? '';
        $contactId = (int)($input['contact_id'] ?? 0);
        $summary = $input['summary'] ?? 'Executed AI Co-Pilot action';
        $idempotencyToken = $input['idempotency_token'] ?? ('idem_' . uniqid());
        $isScheduled = !empty($input['scheduling']['is_scheduled']);
        $scheduledAt = $input['scheduling']['scheduled_at'] ?? null;

        if (empty($actionType)) {
            sendJsonResponse('error', 'Action type is required for execution.', [], 400);
        }

        $resultData = [];

        switch ($actionType) {
            case 'CREATE_CONTACT':
                $target = $input['target_contact'] ?? [];
                $name = trim($target['name'] ?? 'New Contact');
                $email = strtolower(trim($target['email'] ?? ''));
                $phone = trim($target['phone'] ?? '');
                $designation = trim($target['designation'] ?? '');
                $companyName = trim($target['company'] ?? '');

                // Resolve/Create Company
                $companyId = null;
                if (!empty($companyName)) {
                    $stmtC = $db->prepare("SELECT id FROM crm_companies WHERE user_id = ? AND name = ? LIMIT 1");
                    $stmtC->execute([$userId, $companyName]);
                    $companyId = $stmtC->fetchColumn();
                    if (!$companyId) {
                        $db->prepare("INSERT INTO crm_companies (user_id, name, source) VALUES (?, ?, 'AI Co-Pilot')")->execute([$userId, $companyName]);
                        $companyId = $db->lastInsertId();
                    }
                }

                $insC = $db->prepare("INSERT INTO crm_contacts (user_id, company_id, name, email, phone, whatsapp, designation) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insC->execute([$userId, $companyId, $name, $email ?: null, $phone ?: null, $phone ?: null, $designation]);
                $newContactId = $db->lastInsertId();

                CRMSyncHelper::logActivity($userId, $newContactId, 'system', 'system', "Created Contact: \"$name\" ($companyName)", [], $db);
                $resultData = ['message' => "Contact '$name' created successfully.", 'contact_id' => $newContactId];
                break;

            case 'UPDATE_CONTACT':
                $target = $input['target_contact'] ?? [];
                if (!$contactId && !empty($target['name'])) {
                    $stmtC = $db->prepare("SELECT id FROM crm_contacts WHERE user_id = ? AND name LIKE ? LIMIT 1");
                    $stmtC->execute([$userId, "%{$target['name']}%"]);
                    $contactId = (int)$stmtC->fetchColumn();
                }

                if (!$contactId) {
                    sendJsonResponse('error', 'Target contact could not be found to update.', [], 404);
                }

                $updates = [];
                $params = [];

                if (!empty($target['phone'])) {
                    $updates[] = "phone = ?";
                    $updates[] = "whatsapp = ?";
                    $params[] = trim($target['phone']);
                    $params[] = trim($target['phone']);
                }
                if (!empty($target['email'])) {
                    $updates[] = "email = ?";
                    $params[] = strtolower(trim($target['email']));
                }
                if (!empty($target['designation'])) {
                    $updates[] = "designation = ?";
                    $params[] = trim($target['designation']);
                }

                if (count($updates) > 0) {
                    $params[] = $contactId;
                    $params[] = $userId;
                    $db->prepare("UPDATE crm_contacts SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?")->execute($params);
                }

                if (!empty($target['tag'])) {
                    CRMSyncHelper::addContactTag($userId, $contactId, $target['tag'], $db);
                }
                if (!empty($target['note_text'])) {
                    CRMSyncHelper::addContactNote($userId, $contactId, $target['note_text'], 'AI Co-Pilot', $db);
                }

                CRMSyncHelper::logActivity($userId, $contactId, 'system', 'system', "Updated Contact details via AI Co-Pilot", [], $db);
                $resultData = ['message' => "Contact updated successfully.", 'contact_id' => $contactId];
                break;

            case 'ADD_NOTE':
                $target = $input['target_contact'] ?? [];
                $noteText = trim($target['note_text'] ?? $input['summary'] ?? '');
                if (!$contactId || empty($noteText)) {
                    sendJsonResponse('error', 'Contact ID and note text are required.', [], 400);
                }

                $noteId = CRMSyncHelper::addContactNote($userId, $contactId, $noteText, 'AI Co-Pilot', $db);
                $resultData = ['message' => "Note added to contact successfully.", 'note_id' => $noteId];
                break;

            case 'ADD_TAG':
                $target = $input['target_contact'] ?? [];
                $tagName = trim($target['tag'] ?? '');
                if (!$contactId || empty($tagName)) {
                    sendJsonResponse('error', 'Contact ID and tag name are required.', [], 400);
                }

                CRMSyncHelper::addContactTag($userId, $contactId, $tagName, $db);
                $resultData = ['message' => "Tag '$tagName' added to contact successfully."];
                break;

            case 'SEND_EMAIL':
                $emailData = $input['email_draft'] ?? [];
                $recipientEmail = strtolower(trim($emailData['recipient_email'] ?? $input['target_contact']['email'] ?? ''));
                $subject = trim($emailData['subject'] ?? 'Message from LinkPilot AI');
                $body = trim($emailData['body'] ?? '');
                $attachments = $input['resolved_attachments'] ?? [];

                if (empty($recipientEmail) || empty($body)) {
                    sendJsonResponse('error', 'Recipient email and message body are required.', [], 400);
                }

                if ($isScheduled && !empty($scheduledAt)) {
                    $res = CommunicationProviderHelper::scheduleCommunication($userId, 'email', $recipientEmail, $subject, $body, $scheduledAt, $attachments, $idempotencyToken, $contactId, $db);
                    $resultData = ['message' => "Email scheduled for $scheduledAt to $recipientEmail", 'schedule_id' => $res['scheduled_id']];
                } else {
                    $res = CommunicationProviderHelper::sendCommunication($userId, 'email', $recipientEmail, $subject, $body, $attachments, $idempotencyToken, $contactId, $db);
                    $resultData = ['message' => "Email sent successfully to $recipientEmail", 'action_id' => $res['action_id']];
                }
                break;

            case 'SEND_WHATSAPP':
                $waData = $input['whatsapp_draft'] ?? [];
                $recipientPhone = trim($waData['recipient_phone'] ?? $input['target_contact']['phone'] ?? '');
                $waMessage = trim($waData['message'] ?? '');
                $attachments = $input['resolved_attachments'] ?? [];

                if (empty($recipientPhone) || empty($waMessage)) {
                    sendJsonResponse('error', 'Recipient phone number and message text are required.', [], 400);
                }

                if ($isScheduled && !empty($scheduledAt)) {
                    $res = CommunicationProviderHelper::scheduleCommunication($userId, 'whatsapp', $recipientPhone, '', $waMessage, $scheduledAt, $attachments, $idempotencyToken, $contactId, $db);
                    $resultData = ['message' => "WhatsApp message scheduled for $scheduledAt to $recipientPhone", 'schedule_id' => $res['scheduled_id']];
                } else {
                    $res = CommunicationProviderHelper::sendCommunication($userId, 'whatsapp', $recipientPhone, '', $waMessage, $attachments, $idempotencyToken, $contactId, $db);
                    $resultData = ['message' => "WhatsApp message sent successfully to $recipientPhone", 'action_id' => $res['action_id']];
                }
                break;

            case 'CREATE_TASK':
            case 'SCHEDULE_MEETING':
                $taskData = $input['task_draft'] ?? [];
                $title = trim($taskData['title'] ?? 'New Co-Pilot Task');
                $desc = trim($taskData['description'] ?? '');
                $dueDate = !empty($taskData['due_date']) ? $taskData['due_date'] : date('Y-m-d');
                $dueTime = !empty($taskData['due_time']) ? $taskData['due_time'] : null;
                $priority = trim($taskData['priority'] ?? 'medium');

                $stmtTask = $db->prepare("INSERT INTO crm_tasks (user_id, contact_id, title, description, due_date, due_time, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmtTask->execute([$userId, $contactId ?: null, $title, $desc, $dueDate, $dueTime, $priority]);
                $taskId = $db->lastInsertId();

                CRMSyncHelper::logActivity($userId, $contactId, 'task', 'system', "Created Task: \"$title\"", [
                    'task_id' => $taskId,
                    'due_date' => $dueDate
                ], $db);

                $resultData = ['message' => "Task '$title' created successfully", 'task_id' => $taskId];
                break;

            case 'CREATE_INVOICE':
                $invData = $input['invoice_draft'] ?? [];
                $clientName = trim($invData['client_name'] ?? $input['target_contact']['name'] ?? 'Client');
                $amount = (float)($invData['amount'] ?? 0.00);
                $currency = trim($invData['currency'] ?? 'INR');
                $desc = trim($invData['description'] ?? 'Services rendered');
                $dueDate = !empty($invData['due_date']) ? $invData['due_date'] : date('Y-m-d', strtotime('+7 days'));

                $invTitle = "Invoice #INV-" . rand(1000, 9999) . " ($currency " . number_format($amount, 2) . ") for $clientName";
                $stmtInvTask = $db->prepare("INSERT INTO crm_tasks (user_id, contact_id, title, description, due_date, priority, status) VALUES (?, ?, ?, ?, ?, 'high', 'pending')");
                $stmtInvTask->execute([$userId, $contactId ?: null, "[Invoice] $invTitle", $desc, $dueDate]);
                $taskId = $db->lastInsertId();

                CRMSyncHelper::logActivity($userId, $contactId, 'ticket', 'system', "Generated Invoice Draft: \"$invTitle\"", [
                    'amount' => $amount,
                    'currency' => $currency,
                    'due_date' => $dueDate
                ], $db);

                $resultData = ['message' => "Invoice draft created successfully: $invTitle", 'task_id' => $taskId];
                break;

            case 'MOVE_DEAL':
                $dealData = $input['deal_draft'] ?? [];
                $dealTitle = trim($dealData['deal_title'] ?? '');
                $targetStage = trim($dealData['target_stage'] ?? 'Qualified');

                if (!empty($dealTitle)) {
                    $stmtUpdDeal = $db->prepare("UPDATE crm_deals SET stage = ? WHERE user_id = ? AND title LIKE ?");
                    $stmtUpdDeal->execute([$targetStage, $userId, "%$dealTitle%"]);
                } elseif ($contactId > 0) {
                    $stmtUpdDeal = $db->prepare("UPDATE crm_deals SET stage = ? WHERE user_id = ? AND contact_id = ? ORDER BY id DESC LIMIT 1");
                    $stmtUpdDeal->execute([$targetStage, $userId, $contactId]);
                }

                CRMSyncHelper::logActivity($userId, $contactId, 'system', 'system', "Moved Deal stage to '$targetStage'", [
                    'deal_title' => $dealTitle,
                    'stage' => $targetStage
                ], $db);

                $resultData = ['message' => "Deal stage updated to '$targetStage'"];
                break;

            case 'CREATE_AUTOMATION':
            case 'UPDATE_AUTOMATION':
                $autoData = $input['automation_draft'] ?? $input;
                $wfName = trim($autoData['name'] ?? $input['summary'] ?? 'AI Automation Workflow');
                $triggerType = trim($autoData['trigger_type'] ?? 'lead.created');
                $nodes = $autoData['nodes'] ?? [];
                $edges = $autoData['edges'] ?? [];
                $actions = $autoData['actions'] ?? [];

                // Convert natural actions array to structured nodes if nodes is empty
                if (empty($nodes) && !empty($actions)) {
                    $nodes = [
                        ['id' => 'node_trigger', 'type' => 'trigger', 'label' => 'Trigger: ' . $triggerType, 'config' => ['trigger_type' => $triggerType]]
                    ];
                    $idx = 1;
                    foreach ($actions as $act) {
                        $nodes[] = [
                            'id' => 'node_act_' . $idx,
                            'type' => ($act['type'] ?? '') === 'delay' ? 'delay' : 'action',
                            'label' => $act['label'] ?? $act['type'] ?? 'Action Step',
                            'action_type' => $act['action_type'] ?? $act['type'] ?? 'send_email',
                            'config' => $act
                        ];
                        $idx++;
                    }
                    $nodes[] = ['id' => 'node_end', 'type' => 'end', 'label' => 'End Workflow'];
                    
                    $edges = [];
                    for ($i = 0; $i < count($nodes) - 1; $i++) {
                        $edges[] = [
                            'id' => 'edge_' . $i,
                            'source' => $nodes[$i]['id'],
                            'target' => $nodes[$i + 1]['id'],
                            'sourceHandle' => 'default'
                        ];
                    }
                }

                $stmtIns = $db->prepare("
                    INSERT INTO automation_workflows (user_id, name, description, trigger_type, nodes_json, edges_json, status, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 'active', 1)
                ");
                $stmtIns->execute([
                    $userId,
                    $wfName,
                    "Created via AI Co-Pilot command",
                    $triggerType,
                    json_encode($nodes),
                    json_encode($edges)
                ]);
                $wfId = $db->lastInsertId();

                $resultData = [
                    'message' => "Automation workflow '$wfName' created and activated successfully.",
                    'workflow_id' => $wfId,
                    'status' => 'active'
                ];
                break;

            case 'PAUSE_AUTOMATION':
            case 'RESUME_AUTOMATION':
                $wfName = trim($input['automation_name'] ?? '');
                $targetStatus = ($actionType === 'PAUSE_AUTOMATION') ? 'paused' : 'active';
                $isActiveVal = ($targetStatus === 'active') ? 1 : 0;

                if (!empty($wfName)) {
                    $stmtWf = $db->prepare("UPDATE automation_workflows SET status = ?, is_active = ? WHERE user_id = ? AND name LIKE ?");
                    $stmtWf->execute([$targetStatus, $isActiveVal, $userId, "%$wfName%"]);
                } else {
                    $stmtWf = $db->prepare("UPDATE automation_workflows SET status = ?, is_active = ? WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                    $stmtWf->execute([$targetStatus, $isActiveVal, $userId]);
                }

                $resultData = ['message' => "Automation status set to '$targetStatus'."];
                break;

            case 'SEARCH_AUTOMATIONS':
                $stmtWfs = $db->prepare("
                    SELECT id, name, trigger_type, status, runs_count, success_count, failed_count, last_run_at 
                    FROM automation_workflows 
                    WHERE user_id = ? 
                    ORDER BY id DESC LIMIT 20
                ");
                $stmtWfs->execute([$userId]);
                $wfs = $stmtWfs->fetchAll(PDO::FETCH_ASSOC);

                $resultData = [
                    'message' => 'Fetched automations list.',
                    'automations' => $wfs
                ];
                break;

            default:
                sendJsonResponse('error', 'Unsupported action type.', [], 400);
        }

        // Update AI Action Audit log status
        $db->prepare("UPDATE ai_action_logs SET action_status = 'executed' WHERE user_id = ? ORDER BY id DESC LIMIT 1")->execute([$userId]);

        sendJsonResponse('success', 'Co-Pilot action executed successfully.', $resultData);
    } else {
        sendJsonResponse('error', 'Invalid action mode.', [], 400);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Co-Pilot execution failed: ' . $e->getMessage(), [], 500);
}
