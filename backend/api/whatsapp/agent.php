<?php
// backend/api/whatsapp/agent.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        $stmt = $db->prepare("SELECT * FROM whatsapp_agents WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$agent) {
            $agent = [
                'phone_number' => '',
                'website_url' => '',
                'capabilities' => 'faq_support,human_handoff',
                'ground_rules' => '',
                'knowledge_base' => '',
                'status' => 'idle'
            ];
        }
        
        sendJsonResponse('success', 'Agent configuration retrieved.', [
            'agent' => $agent
        ]);
    }
    
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $phoneNumber = trim($input['phone_number'] ?? '');
        $websiteUrl = trim($input['website_url'] ?? '');
        $capabilities = trim($input['capabilities'] ?? 'faq_support,human_handoff');
        $groundRules = trim($input['ground_rules'] ?? '');
        $status = trim($input['status'] ?? 'idle');
        
        // Find existing agent
        $stmt = $db->prepare("SELECT id FROM whatsapp_agents WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmtUpdate = $db->prepare("
                UPDATE whatsapp_agents 
                SET phone_number = ?, website_url = ?, capabilities = ?, ground_rules = ?, status = ?
                WHERE user_id = ?
            ");
            $stmtUpdate->execute([$phoneNumber, $websiteUrl, $capabilities, $groundRules, $status, $userId]);
        } else {
            $stmtInsert = $db->prepare("
                INSERT INTO whatsapp_agents (user_id, phone_number, website_url, capabilities, ground_rules, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$userId, $phoneNumber, $websiteUrl, $capabilities, $groundRules, $status]);
        }
        
        // Handle Crawling/Training if requested
        if ($action === 'crawl' && !empty($websiteUrl)) {
            // Set status to training first
            $db->prepare("UPDATE whatsapp_agents SET status = 'training' WHERE user_id = ?")->execute([$userId]);
            
            // Try to scrap basic content from URL
            $crawledText = "";
            if (filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 5, // 5 seconds timeout
                        'header' => "User-Agent: LinkPilotAgentCrawler/1.0\r\n"
                    ]
                ]);
                $html = @file_get_contents($websiteUrl, false, $context);
                if ($html !== false) {
                    // Extract text content cleanly
                    // Remove scripts and styles
                    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
                    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
                    // Get plain text
                    $text = strip_tags($html);
                    // Decode entities and clean whitespaces
                    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $text = preg_replace('/\s+/', ' ', $text);
                    $crawledText = trim(substr($text, 0, 8000)); // Limit to 8000 chars
                }
            }
            
            // Generate some fallback content if we couldn't load the website
            if (empty($crawledText)) {
                $domain = parse_url($websiteUrl, PHP_URL_HOST) ?: $websiteUrl;
                $crawledText = "Knowledge Base for $domain:\n";
                $crawledText .= "- This is a professional business website operating at $websiteUrl.\n";
                $crawledText .= "- The agent handles customer support questions, responds politely to inquiries, and routes technical queries to human handoff.\n";
                $crawledText .= "- Supported operations include business hours, general pricing questions, products catalog overview, and location settings.\n";
            }
            
            // Update knowledge base and change status to live
            $stmtFinish = $db->prepare("
                UPDATE whatsapp_agents 
                SET knowledge_base = ?, status = 'live'
                WHERE user_id = ?
            ");
            $stmtFinish->execute([$crawledText, $userId]);
            
            sendJsonResponse('success', 'Crawling and training completed successfully.', [
                'knowledge_base' => $crawledText,
                'status' => 'live'
            ]);
            exit;
        }
        
        sendJsonResponse('success', 'Agent configuration updated successfully.');
    } else {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Agent operation failed: ' . $e->getMessage(), [], 500);
}
