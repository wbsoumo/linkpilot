<?php
// backend/api/crm/reports.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJsonResponse('error', 'Method not allowed', [], 405);
    }

    // 1. Emails Received Trend (Last 7 Days)
    $stmtEmails = $db->prepare("
        SELECT DATE_FORMAT(received_date, '%Y-%m-%d') as date, COUNT(*) as count 
        FROM received_emails 
        WHERE user_id = ? AND received_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        GROUP BY date ORDER BY date
    ");
    $stmtEmails->execute([$userId]);
    $emailsTrend = $stmtEmails->fetchAll();

    // 2. Leads Generated Trend (Last 7 Days)
    $stmtLeadsTrend = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as date, COUNT(*) as count 
        FROM crm_leads 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        GROUP BY date ORDER BY date
    ");
    $stmtLeadsTrend->execute([$userId]);
    $leadsTrend = $stmtLeadsTrend->fetchAll();

    // 3. Lead Sources breakdown
    $stmtSources = $db->prepare("
        SELECT lead_source as source, COUNT(*) as count 
        FROM crm_leads 
        WHERE user_id = ? AND lead_source IS NOT NULL AND lead_source != ''
        GROUP BY lead_source
    ");
    $stmtSources->execute([$userId]);
    $leadSources = $stmtSources->fetchAll();

    // 4. Company Growth (monthly check)
    $stmtCompGrowth = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
        FROM crm_companies 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
        GROUP BY month ORDER BY month
    ");
    $stmtCompGrowth->execute([$userId]);
    $companyGrowth = $stmtCompGrowth->fetchAll();

    // 5. AI Categorization Distribution
    $stmtCats = $db->prepare("
        SELECT category, COUNT(*) as count 
        FROM received_emails 
        WHERE user_id = ? AND category IS NOT NULL AND category != ''
        GROUP BY category
    ");
    $stmtCats->execute([$userId]);
    $aiCategories = $stmtCats->fetchAll();

    // 6. Sales Funnel (Leads per Stage)
    $stmtFunnel = $db->prepare("
        SELECT stage, COUNT(*) as count 
        FROM crm_leads 
        WHERE user_id = ?
        GROUP BY stage
    ");
    $stmtFunnel->execute([$userId]);
    $salesFunnel = $stmtFunnel->fetchAll();

    // 7. Revenue Pipeline (Expected revenue per deal stage)
    $stmtRev = $db->prepare("
        SELECT stage, SUM(expected_revenue) as value 
        FROM crm_deals 
        WHERE user_id = ?
        GROUP BY stage
    ");
    $stmtRev->execute([$userId]);
    $revenuePipeline = $stmtRev->fetchAll();

    // 8. Follow-up Completion Rate (Tasks completed vs pending)
    $stmtTasks = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM crm_tasks 
        WHERE user_id = ?
        GROUP BY status
    ");
    $stmtTasks->execute([$userId]);
    $tasksBreakdown = $stmtTasks->fetchAll();

    // 9. AI Accuracy / Processing success rate
    $stmtLogs = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM email_processing_logs 
        WHERE user_id = ?
        GROUP BY status
    ");
    $stmtLogs->execute([$userId]);
    $aiSuccessLogs = $stmtLogs->fetchAll();

    sendJsonResponse('success', 'Reports data fetched successfully', [
        'emails_received_trend' => $emailsTrend,
        'leads_generated_trend' => $leadsTrend,
        'lead_sources' => $leadSources,
        'company_growth' => $companyGrowth,
        'ai_categorization' => $aiCategories,
        'sales_funnel' => $salesFunnel,
        'revenue_pipeline' => $revenuePipeline,
        'tasks_completion' => $tasksBreakdown,
        'ai_processing_accuracy' => $aiSuccessLogs
    ]);
} catch (Exception $e) {
    sendJsonResponse('error', 'Database operation failed: ' . $e->getMessage(), [], 500);
}
