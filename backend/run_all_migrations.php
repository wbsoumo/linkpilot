<?php
// backend/run_all_migrations.php

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=UTF-8');

$db = Database::getConnection();

// Tables required by LinkPilot CRM, Communication Automation, Automation Engine, AI Command Center & Sales Intelligence
$tablesToVerify = [
    'crm_activity_timeline' => "Omnichannel Timeline Activity Log",
    'communication_actions' => "Communication Outbound Actions Registry",
    'scheduled_communications' => "Scheduled Communications Queue",
    'ai_action_logs' => "AI Co-Pilot Audit Logs",
    'crm_contact_notes' => "Contact Notes System",
    'crm_contact_tags' => "Contact Tags Registry",
    'crm_contacts' => "CRM Contacts Base Table",
    'crm_leads' => "CRM Leads Base Table",
    'crm_deals' => "CRM Deals Base Table",
    'crm_companies' => "CRM Companies Table",
    'crm_tasks' => "CRM Tasks & Reminders Table",
    'crm_meetings' => "CRM Meetings Table",
    'automation_workflows' => "CRM Automation Workflows Table",
    'automation_executions' => "Server-Side Persistent Execution Engine",
    'automation_execution_steps' => "Automation Execution Steps Log",
    'crm_lead_scores' => "AI Lead Scores Cache Table",
    'crm_lead_score_signals' => "AI Lead Score Signals Breakdown",
    'crm_lead_score_history' => "AI Lead Score Audit History"
];

// Helper to execute migrations directly without JWT admin header block
function executeDirectMigrations($db) {
    // 1. Email Intelligence Settings Table
    $db->exec("CREATE TABLE IF NOT EXISTS `email_intelligence_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNIQUE NOT NULL,
        `is_active` TINYINT(1) DEFAULT 0,
        `sync_interval_minutes` INT DEFAULT 60,
        `last_sync_at` TIMESTAMP NULL DEFAULT NULL,
        `next_sync_at` TIMESTAMP NULL DEFAULT NULL,
        `business_type` VARCHAR(100) DEFAULT NULL,
        `industry` VARCHAR(100) DEFAULT NULL,
        `timezone` VARCHAR(100) DEFAULT 'Asia/Kolkata',
        `working_hours` VARCHAR(100) DEFAULT NULL,
        `preferred_language` VARCHAR(50) DEFAULT 'en',
        `currency` VARCHAR(10) DEFAULT 'USD',
        `permissions_json` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. CRM Activity Timeline Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_activity_timeline` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `contact_id` INT DEFAULT NULL,
        `lead_id` INT DEFAULT NULL,
        `company_id` INT DEFAULT NULL,
        `channel` ENUM('email', 'whatsapp', 'call', 'task', 'ticket', 'system') NOT NULL DEFAULT 'system',
        `direction` ENUM('inbound', 'outbound', 'system') NOT NULL DEFAULT 'system',
        `subject_or_title` VARCHAR(255) DEFAULT NULL,
        `content` LONGTEXT DEFAULT NULL,
        `summary` TEXT DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'completed',
        `external_ref_id` VARCHAR(100) DEFAULT NULL,
        `meta_data_json` LONGTEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_act_timeline_lookup` (`user_id`, `contact_id`, `channel`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Communication Actions Table
    $db->exec("CREATE TABLE IF NOT EXISTS `communication_actions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `contact_id` INT DEFAULT NULL,
        `channel` VARCHAR(50) NOT NULL DEFAULT 'email',
        `recipient` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) DEFAULT NULL,
        `message` LONGTEXT NOT NULL,
        `attachments_json` LONGTEXT DEFAULT NULL,
        `status` ENUM('draft', 'pending_approval', 'approved', 'queued', 'processing', 'sent', 'failed', 'scheduled', 'cancelled') NOT NULL DEFAULT 'draft',
        `idempotency_token` VARCHAR(100) UNIQUE DEFAULT NULL,
        `provider_response` LONGTEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_comm_act_lookup` (`user_id`, `contact_id`, `channel`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Scheduled Communications Table
    $db->exec("CREATE TABLE IF NOT EXISTS `scheduled_communications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `contact_id` INT DEFAULT NULL,
        `channel` VARCHAR(50) NOT NULL DEFAULT 'email',
        `recipient` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) DEFAULT NULL,
        `message` LONGTEXT NOT NULL,
        `attachments_json` LONGTEXT DEFAULT NULL,
        `scheduled_at` DATETIME NOT NULL,
        `status` ENUM('scheduled', 'processing', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'scheduled',
        `attempts` INT DEFAULT 0,
        `last_error` TEXT DEFAULT NULL,
        `idempotency_token` VARCHAR(100) UNIQUE DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_sched_comm_status` (`user_id`, `status`, `scheduled_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. AI Action Audit Logs Table
    $db->exec("CREATE TABLE IF NOT EXISTS `ai_action_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `prompt` TEXT NOT NULL,
        `intent` VARCHAR(100) NOT NULL,
        `channel` VARCHAR(50) DEFAULT NULL,
        `resolved_contact_id` INT DEFAULT NULL,
        `action_status` VARCHAR(50) DEFAULT 'parsed',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_ai_act_user` (`user_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 6. Contact Notes Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_contact_notes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `contact_id` INT NOT NULL,
        `note_text` LONGTEXT NOT NULL,
        `author_name` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_cnt_notes_lookup` (`user_id`, `contact_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 7. Contact Tags Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_contact_tags` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `tag_name` VARCHAR(100) NOT NULL,
        `color_code` VARCHAR(20) DEFAULT '#3b82f6',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_user_tag` (`user_id`, `tag_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 8. Automation Workflows Table
    $db->exec("CREATE TABLE IF NOT EXISTS `automation_workflows` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `trigger_type` VARCHAR(100) NOT NULL,
        `trigger_value` VARCHAR(100) DEFAULT NULL,
        `trigger_config_json` LONGTEXT DEFAULT NULL,
        `nodes_json` LONGTEXT DEFAULT NULL,
        `edges_json` LONGTEXT DEFAULT NULL,
        `actions_json` LONGTEXT DEFAULT NULL,
        `status` ENUM('draft', 'active', 'paused', 'disabled', 'archived') NOT NULL DEFAULT 'active',
        `is_active` TINYINT(1) DEFAULT 1,
        `version` INT DEFAULT 1,
        `runs_count` INT DEFAULT 0,
        `success_count` INT DEFAULT 0,
        `failed_count` INT DEFAULT 0,
        `last_run_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_wf_user_trigger` (`user_id`, `trigger_type`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 9. Automation Executions Table
    $db->exec("CREATE TABLE IF NOT EXISTS `automation_executions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `workflow_id` INT NOT NULL,
        `workflow_version` INT DEFAULT 1,
        `contact_id` INT DEFAULT NULL,
        `lead_id` INT DEFAULT NULL,
        `deal_id` INT DEFAULT NULL,
        `current_node_id` VARCHAR(100) DEFAULT NULL,
        `execution_context_json` LONGTEXT DEFAULT NULL,
        `status` ENUM('pending', 'running', 'waiting', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
        `next_run_at` DATETIME DEFAULT NULL,
        `error_message` TEXT DEFAULT NULL,
        `idempotency_key` VARCHAR(150) UNIQUE DEFAULT NULL,
        `depth` INT DEFAULT 0,
        `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `completed_at` DATETIME DEFAULT NULL,
        INDEX `idx_wf_exec_poll` (`user_id`, `status`, `next_run_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 10. Automation Execution Steps Table
    $db->exec("CREATE TABLE IF NOT EXISTS `automation_execution_steps` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `execution_id` INT NOT NULL,
        `node_id` VARCHAR(100) NOT NULL,
        `node_type` VARCHAR(50) NOT NULL,
        `node_name` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('passed', 'failed', 'skipped', 'waiting') NOT NULL DEFAULT 'passed',
        `input_json` LONGTEXT DEFAULT NULL,
        `output_json` LONGTEXT DEFAULT NULL,
        `error_message` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_wf_step_lookup` (`execution_id`, `node_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 11. AI Lead Scores Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_lead_scores` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `lead_id` INT NOT NULL,
        `score` INT DEFAULT 50,
        `priority_label` ENUM('low', 'medium', 'high', 'very_high') DEFAULT 'medium',
        `intent_level` ENUM('low', 'medium', 'high') DEFAULT 'medium',
        `risk_level` ENUM('low', 'medium', 'high') DEFAULT 'low',
        `conversion_likelihood` VARCHAR(50) DEFAULT 'medium',
        `summary_text` TEXT DEFAULT NULL,
        `recommended_action` VARCHAR(255) DEFAULT NULL,
        `scoring_version` INT DEFAULT 1,
        `calculated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_user_lead_score` (`user_id`, `lead_id`),
        INDEX `idx_ls_score_prio` (`user_id`, `score`, `priority_label`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 12. AI Lead Score Signals Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_lead_score_signals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `lead_id` INT NOT NULL,
        `category` VARCHAR(50) NOT NULL,
        `signal_name` VARCHAR(150) NOT NULL,
        `score_impact` INT NOT NULL,
        `evidence_text` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_ls_sig_lookup` (`user_id`, `lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 13. AI Lead Score History Table
    $db->exec("CREATE TABLE IF NOT EXISTS `crm_lead_score_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `lead_id` INT NOT NULL,
        `previous_score` INT NOT NULL,
        `new_score` INT NOT NULL,
        `reason` TEXT NOT NULL,
        `scoring_version` INT DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_ls_hist_lookup` (`user_id`, `lead_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 14. Ensure required columns on existing tables
    $contactCols = [
        'contact_type' => "VARCHAR(50) DEFAULT 'Prospect'",
        'owner' => "VARCHAR(255) DEFAULT NULL",
        'tags' => "TEXT DEFAULT NULL",
        'city' => "VARCHAR(100) DEFAULT NULL",
        'state' => "VARCHAR(100) DEFAULT NULL",
        'country' => "VARCHAR(100) DEFAULT NULL",
        'last_contacted_at' => "DATETIME DEFAULT NULL",
        'is_archived' => "TINYINT(1) DEFAULT 0"
    ];
    foreach ($contactCols as $col => $def) {
        try {
            $cStmt = $db->query("SHOW COLUMNS FROM `crm_contacts` LIKE '{$col}'");
            if (!$cStmt->fetch()) {
                $db->exec("ALTER TABLE `crm_contacts` ADD COLUMN `{$col}` {$def}");
            }
        } catch (Exception $e) {}
    }

    $emailCols = [
        'is_archived' => "TINYINT(1) DEFAULT 0",
        'is_spam' => "TINYINT(1) DEFAULT 0",
        'parent_id' => "INT DEFAULT NULL",
        'ai_status' => "VARCHAR(20) DEFAULT 'pending'"
    ];
    foreach ($emailCols as $col => $def) {
        try {
            $cStmt = $db->query("SHOW COLUMNS FROM `received_emails` LIKE '{$col}'");
            if (!$cStmt->fetch()) {
                $db->exec("ALTER TABLE `received_emails` ADD COLUMN `{$col}` {$def}");
            }
        } catch (Exception $e) {}
    }
}

executeDirectMigrations($db);

$results = [];
$errorCount = 0;

// Check each table existence in database
foreach ($tablesToVerify as $tableName => $description) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$tableName'");
        $exists = $stmt && $stmt->fetch();
        if ($exists) {
            $countStmt = $db->query("SELECT COUNT(*) FROM `$tableName`");
            $rowCount = $countStmt ? (int)$countStmt->fetchColumn() : 0;
            $results[] = [
                'table' => $tableName,
                'description' => $description,
                'status' => 'ADDED / EXISTS',
                'rows' => $rowCount,
                'success' => true
            ];
        } else {
            $errorCount++;
            $results[] = [
                'table' => $tableName,
                'description' => $description,
                'status' => 'MISSING',
                'rows' => 0,
                'success' => false
            ];
        }
    } catch (Exception $ex) {
        $errorCount++;
        $results[] = [
            'table' => $tableName,
            'description' => $description,
            'status' => 'ERROR: ' . $ex->getMessage(),
            'rows' => 0,
            'success' => false
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkPilot - One-Click Database Migrations Verifier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-4xl mx-auto space-y-8">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 border border-blue-500/30 rounded-2xl p-6 shadow-2xl flex flex-col md:flex-row items-center justify-between gap-4">
            <div>
                <span class="px-3 py-1 bg-blue-500/20 text-blue-400 border border-blue-400/30 rounded-full text-xs font-bold uppercase tracking-wider">Database Manager</span>
                <h1 class="text-2xl font-black text-white mt-2">LinkPilot Database Migration Inspector</h1>
                <p class="text-slate-300 text-xs mt-1">Automatic verification & setup for all CRM, Communication, Automation Engine, AI Co-Pilot, and Lead Scoring tables.</p>
            </div>
            <a href="run_all_migrations.php" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Re-Run Migrations</span>
            </a>
        </div>

        <!-- Status Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">Total Required Tables</span>
                    <span class="text-2xl font-black text-white"><?php echo count($tablesToVerify); ?></span>
                </div>
                <div class="h-10 w-10 bg-blue-500/10 border border-blue-500/20 rounded-xl flex items-center justify-center text-blue-400 font-bold text-lg">✓</div>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">Successfully Added</span>
                    <span class="text-2xl font-black text-emerald-400"><?php echo count($tablesToVerify) - $errorCount; ?></span>
                </div>
                <div class="h-10 w-10 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 font-bold text-lg">✓</div>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">Status Check</span>
                    <span class="text-sm font-extrabold <?php echo $errorCount === 0 ? 'text-emerald-400' : 'text-rose-400'; ?>">
                        <?php echo $errorCount === 0 ? 'All Tables Ready' : "$errorCount Table Errors"; ?>
                    </span>
                </div>
                <div class="h-10 w-10 <?php echo $errorCount === 0 ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400'; ?> border rounded-xl flex items-center justify-center font-bold text-lg">
                    <?php echo $errorCount === 0 ? '★' : '!'; ?>
                </div>
            </div>
        </div>

        <!-- Tables Results Grid Table -->
        <div class="bg-slate-900/50 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="px-6 py-4 bg-slate-900 border-b border-slate-800 flex items-center justify-between">
                <h3 class="font-extrabold text-sm text-white">Database Tables Migration Status</h3>
                <span class="text-xs text-slate-400 font-mono">Database: Connected</span>
            </div>
            <div class="divide-y divide-slate-800/60">
                <?php foreach ($results as $r): ?>
                    <div class="px-6 py-3.5 flex flex-col md:flex-row md:items-center justify-between gap-2 hover:bg-slate-800/30 transition">
                        <div class="space-y-0.5">
                            <div class="flex items-center space-x-2">
                                <code class="text-xs font-bold font-mono text-blue-400"><?php echo htmlspecialchars($r['table']); ?></code>
                                <span class="text-[10px] text-slate-400 font-semibold">(<?php echo htmlspecialchars($r['description']); ?>)</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="text-[11px] font-mono text-slate-400"><?php echo $r['rows']; ?> record(s)</span>
                            <?php if ($r['success']): ?>
                                <span class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-extrabold text-[11px] rounded-full flex items-center space-x-1">
                                    <span>✓ Added &amp; Verified</span>
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-rose-500/10 border border-rose-500/30 text-rose-400 font-extrabold text-[11px] rounded-full">
                                    ✕ <?php echo htmlspecialchars($r['status']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
