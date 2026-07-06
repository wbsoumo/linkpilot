<?php
// dashboard/diagnose_whatsapp.php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/jwt_helper.php';
require_once __DIR__ . '/../backend/providers/whatsapp_meta_service.php';

// Try to authenticate user from cookie or request header
$userId = null;
try {
    $token = $_COOKIE['auth_token'] ?? '';
    if (empty($token)) {
        // Read auth token from localStorage if we are in iframe/ajax, but for direct view try cookie
        // fallback to first user in db for easy debugging if not logged in
        $db = Database::getConnection();
        $userId = 1;
    } else {
        $decoded = JWTHelper::decode($token);
        $userId = $decoded['user_id'] ?? 1;
    }
} catch (Exception $e) {
    $userId = 1;
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM whatsapp_accounts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$userId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Connection Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen py-10 px-6 font-sans">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="border-b border-slate-800 pb-4">
            <h1 class="text-2xl font-bold text-teal-400">LinkPilot WhatsApp Connection Diagnostics</h1>
            <p class="text-xs text-slate-400 mt-1">Real-time inspection of your Meta Access Token, Scopes, and Assets.</p>
        </div>

        <?php if (!$account): ?>
            <div class="p-6 bg-amber-500/10 border border-amber-500/20 rounded-xl text-center">
                <p class="text-sm text-amber-400 font-semibold">No active WhatsApp connection record found in the database.</p>
                <p class="text-xs text-slate-400 mt-1">Please complete the setup wizard first.</p>
            </div>
        <?php else: 
            $accessToken = decryptData($account['access_token']);
            $wabaId = $account['waba_id'];
            $phoneId = $account['phone_number_id'];
            
            $debugResult = [];
            $permissions = [];
            $wabas = [];
            $phoneDetails = null;
            $meInfo = null;
            
            // 1. Get Me Info
            try {
                $meInfo = WhatsAppMetaService::executeRequest("me?fields=id,name", "GET", null, $accessToken);
            } catch (Exception $e) {
                $debugResult['me'] = "Error: " . $e->getMessage();
            }

            // 2. Get Permissions
            try {
                $permData = WhatsAppMetaService::executeRequest("me/permissions", "GET", null, $accessToken);
                $permissions = $permData['data'] ?? [];
            } catch (Exception $e) {
                $debugResult['permissions'] = "Error: " . $e->getMessage();
            }

            // 3. Get WhatsApp Accounts
            try {
                $wabaData = WhatsAppMetaService::executeRequest("me/whatsapp_business_accounts?fields=id,name,status", "GET", null, $accessToken);
                $wabas = $wabaData['data'] ?? [];
            } catch (Exception $e) {
                $debugResult['wabas'] = "Error: " . $e->getMessage();
            }

            // 4. Get Phone details
            try {
                $phoneDetails = WhatsAppMetaService::getPhoneNumberDetails($phoneId, $accessToken);
            } catch (Exception $e) {
                $debugResult['phone'] = "Error: " . $e->getMessage();
            }
        ?>
            <!-- 1. Database Record Overview -->
            <div class="bg-slate-800 border border-slate-700/50 rounded-xl p-5 space-y-3">
                <h3 class="text-sm font-bold text-white border-b border-slate-700/50 pb-2">1. Local Database Record</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                    <div>
                        <div class="text-slate-500 font-semibold">Saved Phone ID</div>
                        <div class="font-mono text-slate-300 mt-1"><?= htmlspecialchars($phoneId) ?></div>
                    </div>
                    <div>
                        <div class="text-slate-500 font-semibold">Saved WABA ID</div>
                        <div class="font-mono text-slate-300 mt-1"><?= htmlspecialchars($wabaId) ?></div>
                    </div>
                    <div>
                        <div class="text-slate-500 font-semibold">Saved Phone Number</div>
                        <div class="text-slate-300 mt-1"><?= htmlspecialchars($account['display_phone_number']) ?></div>
                    </div>
                    <div>
                        <div class="text-slate-500 font-semibold">Connection Status</div>
                        <span class="inline-block mt-1 px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded font-bold text-[10px] uppercase"><?= htmlspecialchars($account['status']) ?></span>
                    </div>
                </div>
            </div>

            <!-- 2. Meta System User Identity -->
            <div class="bg-slate-800 border border-slate-700/50 rounded-xl p-5 space-y-3">
                <h3 class="text-sm font-bold text-white border-b border-slate-700/50 pb-2">2. Meta Token Owner (System User)</h3>
                <?php if ($meInfo): ?>
                    <div class="flex items-center space-x-3 text-xs">
                        <div class="h-9 w-9 bg-teal-500/10 text-teal-400 font-bold rounded-lg flex items-center justify-center text-sm">👤</div>
                        <div>
                            <div class="text-white font-bold"><?= htmlspecialchars($meInfo['name'] ?? 'Unknown User') ?></div>
                            <div class="text-slate-400 font-mono mt-0.5">ID: <?= htmlspecialchars($meInfo['id'] ?? '') ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-rose-400 font-semibold"><?= $debugResult['me'] ?? 'Failed loading user identity.' ?></p>
                <?php endif; ?>
            </div>

            <!-- 3. Token Scopes / Permissions -->
            <div class="bg-slate-800 border border-slate-700/50 rounded-xl p-5 space-y-3">
                <h3 class="text-sm font-bold text-white border-b border-slate-700/50 pb-2">3. Token Permissions (Scopes)</h3>
                <?php if (!empty($permissions)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                        <?php foreach ($permissions as $p): 
                            $isGranted = ($p['status'] === 'granted');
                        ?>
                            <div class="flex justify-between items-center bg-slate-900/50 border border-slate-800 p-2.5 rounded-lg">
                                <span class="font-mono text-slate-300"><?= htmlspecialchars($p['permission']) ?></span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $isGranted ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-rose-400 font-semibold"><?= $debugResult['permissions'] ?? 'No permissions returned by Meta. Token may be invalid.' ?></p>
                <?php endif; ?>
            </div>

            <!-- 4. Accessible WhatsApp Accounts (WABA Assets) -->
            <div class="bg-slate-800 border border-slate-700/50 rounded-xl p-5 space-y-3">
                <h3 class="text-sm font-bold text-white border-b border-slate-700/50 pb-2">4. Accessible WABA Accounts</h3>
                <p class="text-xs text-slate-400">These are the WABA assets that this token can actively query. If your WABA is not listed here, the asset was not assigned to the System User *prior* to token generation.</p>
                <?php if (!empty($wabas)): ?>
                    <div class="space-y-2 text-xs">
                        <?php foreach ($wabas as $w): 
                            $isCurrent = ($w['id'] === $wabaId);
                        ?>
                            <div class="flex justify-between items-center p-3 rounded-lg border <?= $isCurrent ? 'bg-teal-500/5 border-teal-500/35' : 'bg-slate-900/30 border-slate-800' ?>">
                                <div>
                                    <div class="font-bold text-white"><?= htmlspecialchars($w['name'] ?? '') ?></div>
                                    <div class="font-mono text-[10px] text-slate-500 mt-0.5">ID: <?= htmlspecialchars($w['id'] ?? '') ?></div>
                                </div>
                                <div class="flex items-center space-x-2 text-right">
                                    <span class="px-2 py-0.5 bg-slate-800 text-slate-400 rounded text-[9px] uppercase font-bold"><?= htmlspecialchars($w['status'] ?? '') ?></span>
                                    <?php if ($isCurrent): ?>
                                        <span class="px-2 py-0.5 bg-teal-500/20 text-teal-300 rounded text-[9px] uppercase font-bold">Currently Selected</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-rose-400 font-semibold"><?= $debugResult['wabas'] ?? 'No accessible WABA accounts found for this token.' ?></p>
                <?php endif; ?>
            </div>

            <!-- 5. Phone Details & Send Permission Check -->
            <div class="bg-slate-800 border border-slate-700/50 rounded-xl p-5 space-y-3">
                <h3 class="text-sm font-bold text-white border-b border-slate-700/50 pb-2">5. Phone Number & Send Permission Check</h3>
                <?php if ($phoneDetails): ?>
                    <div class="grid grid-cols-2 gap-4 text-xs border-b border-slate-700/30 pb-3">
                        <div>
                            <span class="text-slate-500 font-semibold">Display Phone Number</span>
                            <div class="text-slate-300 mt-1"><?= htmlspecialchars($phoneDetails['display_phone_number'] ?? '') ?></div>
                        </div>
                        <div>
                            <span class="text-slate-500 font-semibold">Status Badge</span>
                            <div class="text-slate-300 mt-1"><?= htmlspecialchars($phoneDetails['status'] ?? '') ?></div>
                        </div>
                    </div>
                    
                    <!-- Send Test Action -->
                    <div class="space-y-3 pt-1">
                        <div class="text-xs font-semibold text-slate-400">Verify Send API Call directly:</div>
                        <form method="POST" class="flex space-x-2">
                            <input type="text" name="test_phone" placeholder="Recipient Phone Number (with country code, e.g. 919242322991)" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs flex-1 focus:outline-none focus:border-teal-500">
                            <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white font-bold rounded-lg text-xs transition">Send Test Message</button>
                        </form>
                        
                        <?php
                        if (!empty($_POST['test_phone'])) {
                            $to = trim($_POST['test_phone']);
                            try {
                                echo "<div class='p-3 bg-slate-900 border border-slate-800 rounded-lg text-xs space-y-2 mt-2'>";
                                echo "<div class='font-bold text-teal-400'>Sending request...</div>";
                                
                                $payload = [
                                    "messaging_product" => "whatsapp",
                                    "recipient_type" => "individual",
                                    "to" => $to,
                                    "type" => "text",
                                    "text" => [
                                        "preview_url" => false,
                                        "body" => "Hello, this is a connection check message from LinkPilot Diagnostics."
                                    ]
                                ];
                                
                                $res = WhatsAppMetaService::executeRequest("{$phoneId}/messages", "POST", $payload, $accessToken);
                                echo "<div class='text-emerald-400 font-bold'>Success! Meta Response:</div>";
                                echo "<pre class='text-[10px] text-slate-400 overflow-x-auto bg-slate-950 p-2.5 rounded font-mono'>" . htmlspecialchars(print_r($res, true)) . "</pre>";
                                echo "</div>";
                            } catch (Exception $e) {
                                echo "<div class='p-3 bg-rose-500/10 border border-rose-500/20 rounded-lg text-xs space-y-1 mt-2'>";
                                echo "<div class='text-rose-400 font-bold'>Failed! Meta Error:</div>";
                                echo "<p class='text-rose-500 font-semibold'>" . htmlspecialchars($e->getMessage()) . "</p>";
                                echo "</div>";
                            }
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-rose-400 font-semibold"><?= $debugResult['phone'] ?? 'Failed retrieving phone details.' ?></p>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
