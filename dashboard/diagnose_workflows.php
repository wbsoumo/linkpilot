<?php
// dashboard/diagnose_workflows.php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../backend/config.php';

$db = Database::getConnection();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LinkPilot Automation Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen py-10 px-6 font-sans">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="border-b border-slate-800 pb-4">
            <h1 class="text-2xl font-bold text-indigo-400">LinkPilot Automation Diagnostics</h1>
            <p class="text-xs text-slate-400 mt-1">Real-time inspection of visual workflows, execution logs, and database states.</p>
        </div>

        <!-- 1. Active Workflows -->
        <div class="bg-slate-800 border border-slate-700/50 rounded-xl p-5 space-y-3">
            <h3 class="text-sm font-bold text-white border-b border-slate-700/50 pb-2">1. Saved Workflows</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs text-slate-300">
                    <thead class="text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="px-2 py-1 text-left">ID</th>
                            <th class="px-2 py-1 text-left">Name</th>
                            <th class="px-2 py-1 text-left">Trigger</th>
                            <th class="px-2 py-1 text-left">Active</th>
                            <th class="px-2 py-1 text-left">Actions Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php
                        $wfs = $db->query("SELECT * FROM automation_workflows ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($wfs as $wf):
                        ?>
                            <tr>
                                <td class="px-2 py-2 font-mono"><?= $wf['id'] ?></td>
                                <td class="px-2 py-2 font-bold"><?= htmlspecialchars($wf['name']) ?></td>
                                <td class="px-2 py-2 font-mono text-indigo-400"><?= htmlspecialchars($wf['trigger_type']) ?> (<?= htmlspecialchars($wf['trigger_value']) ?>)</td>
                                <td class="px-2 py-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] <?= $wf['is_active'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-700 text-slate-400' ?>">
                                        <?= $wf['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="px-2 py-2 font-mono text-[10px] text-slate-500 max-w-xs truncate"><?= htmlspecialchars($wf['actions_json']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Execution Logs -->
        <div class="bg-slate-800 border border-slate-700/50 rounded-xl p-5 space-y-3">
            <h3 class="text-sm font-bold text-white border-b border-slate-700/50 pb-2">2. Recent Workflow Execution Logs</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs text-slate-300">
                    <thead class="text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="px-2 py-1 text-left">ID</th>
                            <th class="px-2 py-1 text-left">Workflow</th>
                            <th class="px-2 py-1 text-left">Status</th>
                            <th class="px-2 py-1 text-left">Duration</th>
                            <th class="px-2 py-1 text-left">Error Message</th>
                            <th class="px-2 py-1 text-left">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php
                        $logs = $db->query("SELECT * FROM workflow_execution_logs ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($logs as $log):
                            $isSuccess = ($log['status'] === 'success');
                        ?>
                            <tr class="<?= $isSuccess ? '' : 'bg-rose-500/5' ?>">
                                <td class="px-2 py-2 font-mono"><?= $log['id'] ?></td>
                                <td class="px-2 py-2 font-semibold"><?= htmlspecialchars($log['workflow_name']) ?></td>
                                <td class="px-2 py-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] <?= $isSuccess ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                                        <?= htmlspecialchars($log['status']) ?>
                                    </span>
                                </td>
                                <td class="px-2 py-2"><?= number_format($log['execution_time'], 4) ?>s</td>
                                <td class="px-2 py-2 text-rose-400 font-semibold max-w-xs break-words"><?= htmlspecialchars($log['error_message']) ?></td>
                                <td class="px-2 py-2 text-slate-400 font-mono"><?= $log['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Recent WhatsApp Queue Messages -->
        <div class="bg-slate-800 border border-slate-700/50 rounded-xl p-5 space-y-3">
            <h3 class="text-sm font-bold text-white border-b border-slate-700/50 pb-2">3. WhatsApp Queue Status</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs text-slate-300">
                    <thead class="text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="px-2 py-1 text-left">ID</th>
                            <th class="px-2 py-1 text-left">Recipient</th>
                            <th class="px-2 py-1 text-left">Status</th>
                            <th class="px-2 py-1 text-left">Attempts</th>
                            <th class="px-2 py-1 text-left">Scheduled At</th>
                            <th class="px-2 py-1 text-left">Error Message</th>
                            <th class="px-2 py-1 text-left">Created At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php
                        $queue = $db->query("SELECT * FROM whatsapp_queue ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($queue as $q):
                            $status = $q['status'];
                        ?>
                            <tr>
                                <td class="px-2 py-2 font-mono"><?= $q['id'] ?></td>
                                <td class="px-2 py-2 font-mono"><?= htmlspecialchars($q['recipient_number']) ?></td>
                                <td class="px-2 py-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] <?= $status === 'sent' ? 'bg-emerald-500/10 text-emerald-400' : ($status === 'failed' ? 'bg-rose-500/10 text-rose-400' : 'bg-amber-500/10 text-amber-400') ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                                <td class="px-2 py-2"><?= $q['attempts'] ?></td>
                                <td class="px-2 py-2 font-mono text-slate-400"><?= $q['scheduled_at'] ?: 'Immediate' ?></td>
                                <td class="px-2 py-2 text-rose-400 max-w-xs break-words"><?= htmlspecialchars($q['error_message']) ?></td>
                                <td class="px-2 py-2 font-mono text-slate-400"><?= $q['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. System Variables -->
        <div class="bg-slate-800 border border-slate-700/50 rounded-xl p-5 space-y-3">
            <h3 class="text-sm font-bold text-white border-b border-slate-700/50 pb-2">4. System Time Info</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-xs font-mono">
                <div>
                    <span class="text-slate-500">PHP Current Time</span>
                    <div class="text-slate-300 mt-1"><?= date('Y-m-d H:i:s') ?></div>
                </div>
                <div>
                    <span class="text-slate-500">PHP Configured Timezone</span>
                    <div class="text-slate-300 mt-1"><?= date_default_timezone_get() ?></div>
                </div>
                <div>
                    <span class="text-slate-500">MySQL CURRENT_TIMESTAMP</span>
                    <div class="text-slate-300 mt-1"><?= $db->query("SELECT NOW()")->fetchColumn() ?></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
