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

$results = [];
$errorCount = 0;

try {
    // 1. Run migrations in migrate.php
    require_once __DIR__ . '/api/crm/migrate.php';
} catch (Throwable $e) {
    // Migration output processed
}

// Check each table existence in database
foreach ($tablesToVerify as $tableName => $description) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$tableName'");
        $exists = $stmt && $stmt->fetch();
        if ($exists) {
            // Count rows
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
