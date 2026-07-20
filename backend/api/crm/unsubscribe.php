<?php
// backend/api/crm/unsubscribe.php

require_once __DIR__ . '/../../config.php';

$trackingId = $_GET['id'] ?? '';
$success = false;
$recipientEmail = '';

if (!empty($trackingId)) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT campaign_log_id, recipient_email FROM email_tracking WHERE tracking_id = ?");
        $stmt->execute([$trackingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $recipientEmail = $row['recipient_email'];
            $campaignLogId = $row['campaign_log_id'];

            if (!empty($campaignLogId)) {
                $db->prepare("UPDATE email_campaign_logs SET is_unsubscribed = 1 WHERE id = ?")->execute([$campaignLogId]);

                $stmtEvent = $db->prepare("
                    INSERT INTO email_activity_events (campaign_log_id, tracking_id, event_type, event_label, event_data, created_at)
                    VALUES (?, ?, 'Unsubscribed', 'Recipient requested unsubscribe', ?, NOW())
                ");
                $stmtEvent->execute([$campaignLogId, $trackingId, json_encode(['email' => $recipientEmail])]);
            }
            $success = true;
        }
    } catch (Exception $e) {
        // Handled below
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribed | LinkPilot AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 font-sans text-slate-800">
    <div class="bg-white max-w-md w-full rounded-2xl border border-slate-200 shadow-xl p-8 text-center space-y-4">
        <div class="h-14 w-14 rounded-2xl bg-indigo-50 text-indigo-650 flex items-center justify-center mx-auto text-2xl">
            📬
        </div>
        <h1 class="text-xl font-black text-slate-900">Unsubscribe Confirmation</h1>
        <?php if ($success): ?>
            <p class="text-sm text-slate-600 leading-relaxed">
                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($recipientEmail); ?></span> has been successfully unsubscribed from this campaign mailing list.
            </p>
            <div class="pt-2">
                <span class="inline-block px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-extrabold">
                    ✓ List Preferences Updated
                </span>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-500">
                Invalid or expired unsubscribe link. If you continue to receive unwanted emails, please contact support.
            </p>
        <?php endif; ?>
        <div class="pt-4 border-t border-slate-100 text-xs text-slate-400">
            Powered by LinkPilot AI Outreach Systems
        </div>
    </div>
</body>
</html>
