<?php
// backend/api/crm/agent.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../agent_orchestrator.php';
require_once __DIR__ . '/../../ai_tool_registry.php';

// Validate User Authentication
$user = JWTHelper::requireAuth();
$userId = (int)$user['id'];
$db = Database::getConnection();

// Self-healing migration
AgentOrchestrator::init($db);

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: [];

$action = $input['action'] ?? ($_GET['action'] ?? 'chat');
$isStream = !empty($_GET['stream']) || !empty($input['stream']);

try {
    // 1. List conversations
    if ($action === 'list_conversations') {
        $search = trim($_GET['search'] ?? ($input['search'] ?? ''));
        $conversations = AgentOrchestrator::listConversations($userId, $search, $db);
        sendJsonResponse('success', 'Conversations retrieved.', [
            'conversations' => $conversations
        ]);
    }

    // 2. Create new conversation
    elseif ($action === 'create_conversation') {
        $title = trim($input['title'] ?? 'New Conversation');
        $newConv = AgentOrchestrator::createConversation($userId, $title, $db);
        sendJsonResponse('success', 'New conversation started.', [
            'conversation' => $newConv
        ]);
    }

    // 3. Get conversation messages
    elseif ($action === 'get_conversation') {
        $convId = trim($_GET['id'] ?? ($input['id'] ?? ''));
        if (empty($convId)) {
            sendJsonResponse('error', 'Conversation ID is required.', [], 400);
        }
        $data = AgentOrchestrator::getConversationMessages($userId, $convId, $db);
        sendJsonResponse('success', 'Conversation loaded.', $data);
    }

    // 4. Rename conversation
    elseif ($action === 'rename_conversation') {
        $convId = trim($input['id'] ?? '');
        $title = trim($input['title'] ?? '');
        if (empty($convId) || empty($title)) {
            sendJsonResponse('error', 'Conversation ID and title are required.', [], 400);
        }
        $res = AgentOrchestrator::renameConversation($userId, $convId, $title, $db);
        sendJsonResponse('success', 'Conversation renamed.', $res);
    }

    // 5. Delete conversation
    elseif ($action === 'delete_conversation') {
        $convId = trim($input['id'] ?? '');
        if (empty($convId)) {
            sendJsonResponse('error', 'Conversation ID is required.', [], 400);
        }
        $res = AgentOrchestrator::deleteConversation($userId, $convId, $db);
        sendJsonResponse('success', 'Conversation deleted.', $res);
    }

    // 6. Get capabilities & tool registry summary
    elseif ($action === 'get_capabilities') {
        $tools = AIToolRegistry::listTools();
        $categories = [];
        foreach ($tools as $t) {
            $cat = $t['category'] ?? 'general';
            if (!isset($categories[$cat])) $categories[$cat] = [];
            $categories[$cat][] = $t;
        }

        sendJsonResponse('success', 'Agent capabilities loaded.', [
            'total_tools' => count($tools),
            'categories' => $categories
        ]);
    }

    // 7. Approve & Execute Pending Action
    elseif ($action === 'approve_action') {
        $convId = trim($input['conversation_id'] ?? '');
        $toolId = trim($input['tool_id'] ?? '');
        $toolArgs = $input['arguments'] ?? [];

        if (empty($toolId) || empty($convId)) {
            sendJsonResponse('error', 'Conversation ID and Tool ID are required.', [], 400);
        }

        // Execute tool directly
        $toolResult = AIToolRegistry::executeTool($toolId, $userId, $toolArgs, $db);

        // Resume conversation with synthesis
        $resumeMsg = "Action confirmed and executed. Please review result and provide completion confirmation.";
        $res = AgentOrchestrator::processChat($userId, $convId, $resumeMsg, [
            'approved_tool_id' => $toolId,
            'approved_tool_result' => $toolResult
        ], null, $db);

        sendJsonResponse('success', 'Action approved and executed successfully.', [
            'tool_result' => $toolResult,
            'chat_response' => $res
        ]);
    }

    // 8. Chat Execution (Streaming or Standard JSON)
    elseif ($action === 'chat') {
        $convId = trim($input['conversation_id'] ?? ($_GET['conversation_id'] ?? ''));
        $message = trim($input['message'] ?? '');

        if (empty($convId)) {
            $newConv = AgentOrchestrator::createConversation($userId, 'New Conversation', $db);
            $convId = $newConv['id'];
        }

        if (empty($message)) {
            sendJsonResponse('error', 'Message cannot be empty.', [], 400);
        }

        if ($isStream) {
            // Disable output buffering
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            // Flush SSE headers
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            flush();

            $emitter = function($event, $data) {
                echo "event: {$event}\n";
                echo "data: " . json_encode($data) . "\n\n";
                @ob_flush();
                @flush();
            };

            try {
                AgentOrchestrator::processChat($userId, $convId, $message, $input, $emitter, $db);
            } catch (Throwable $streamEx) {
                $emitter('error', ['message' => $streamEx->getMessage()]);
            }
            exit(0);
        } else {
            // Standard JSON Request/Response
            $result = AgentOrchestrator::processChat($userId, $convId, $message, $input, null, $db);
            sendJsonResponse('success', 'Agent response generated.', $result);
        }
    }

    else {
        sendJsonResponse('error', "Unknown action '{$action}'.", [], 400);
    }

} catch (Throwable $e) {
    if ($isStream) {
        echo "event: error\n";
        echo "data: " . json_encode(['message' => $e->getMessage()]) . "\n\n";
        @flush();
        exit(0);
    }
    sendJsonResponse('error', $e->getMessage(), [], 500);
}
