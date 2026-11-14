<?php
// backend/api/crm/download_temp_file.php
// Secure temporary file delivery endpoint.
// Validates 64-char token, enforces 30-min expiry, blocks directory traversal,
// streams binary file with proper attachment headers, and displays clean error states.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../temp_file_service.php';

$token = trim($_GET['token'] ?? '');

$fileInfo = TempFileService::getDownloadFile($token);

if (!empty($fileInfo['error'])) {
    $errType = $fileInfo['error'];
    $isExpired = ($errType === 'expired');
    $statusCode = $isExpired ? 410 : ($errType === 'invalid_token' ? 400 : 404);
    http_response_code($statusCode);

    $title = $isExpired ? 'Download Link Expired' : 'File Not Found';
    $message = $isExpired 
        ? 'This download link has expired. For your security and workspace privacy, LinkPilot AI automatically deletes temporary generated files exactly 30 minutes after creation.'
        : ($fileInfo['message'] ?? 'The requested file is unavailable or the download link is invalid.');

    $filename = htmlspecialchars($fileInfo['filename'] ?? 'Requested File');

    // Return JSON if caller explicitly requested JSON (e.g. API clients)
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($acceptHeader, 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'error_code' => $errType,
            'message' => $message,
            'filename' => $filename
        ]);
        exit;
    }

    // Modern, polished LinkPilot-branded user-facing error page
    ?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - LinkPilot AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.08) 0%, rgba(15, 23, 42, 0) 50%),
                        radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.06) 0%, rgba(15, 23, 42, 0) 50%),
                        #0b0f19;
        }
    </style>
</head>
<body class="h-full text-slate-100 flex flex-col justify-between antialiased selection:bg-indigo-500 selection:text-white">
    <nav class="w-full px-6 md:px-10 py-5 flex items-center justify-between border-b border-slate-900/60 bg-[#0b0f19]/40 backdrop-blur-xl">
        <a href="/index.html" class="flex items-center space-x-2 text-lg font-black tracking-wider text-white">
            <span class="bg-gradient-to-r from-blue-400 via-indigo-400 to-teal-300 bg-clip-text text-transparent">LinkPilot AI</span>
        </a>
        <a href="/dashboard/index.html" class="text-xs font-bold text-slate-300 hover:text-white transition px-4 py-2 border border-slate-800 rounded-xl hover:bg-slate-800/60">Open Assistant</a>
    </nav>

    <main class="flex-grow flex items-center justify-center p-6">
        <div class="relative w-full max-w-lg bg-slate-900/50 border border-slate-800/80 rounded-3xl p-8 md:p-10 text-center backdrop-blur-2xl shadow-2xl">
            <div class="w-16 h-16 rounded-2xl <?= $isExpired ? 'bg-amber-500/10 border border-amber-500/20 text-amber-400' : 'bg-rose-500/10 border border-rose-500/20 text-rose-400' ?> flex items-center justify-center mx-auto mb-5 shadow-inner">
                <i data-lucide="<?= $isExpired ? 'clock' : 'file-x' ?>" class="h-8 w-8"></i>
            </div>

            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider mb-3 <?= $isExpired ? 'bg-amber-500/10 text-amber-300 border border-amber-500/20' : 'bg-rose-500/10 text-rose-300 border border-rose-500/20' ?>">
                <i data-lucide="<?= $isExpired ? 'hourglass' : 'alert-circle' ?>" class="h-3 w-3"></i>
                <span><?= $isExpired ? '30-Minute Expiry Reached' : 'Unavailable' ?></span>
            </div>

            <h1 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight mb-2"><?= htmlspecialchars($title) ?></h1>
            <p class="text-xs font-mono text-slate-400 mb-4 bg-slate-950/50 py-1.5 px-3 rounded-lg border border-slate-800/60 inline-block max-w-full truncate">
                <?= $filename ?>
            </p>

            <p class="text-xs md:text-sm text-slate-400 leading-relaxed mb-6 max-w-md mx-auto">
                <?= htmlspecialchars($message) ?>
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="/dashboard/index.html" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/25 transition inline-flex items-center justify-center space-x-2">
                    <i data-lucide="bot" class="h-4 w-4"></i>
                    <span>Generate New File in AI Chat</span>
                </a>
                <a href="javascript:window.close()" class="w-full sm:w-auto px-5 py-2.5 bg-slate-800/70 hover:bg-slate-800 text-slate-300 font-semibold text-xs rounded-xl border border-slate-700/60 transition inline-flex items-center justify-center">
                    Close Window
                </a>
            </div>
        </div>
    </main>

    <footer class="py-5 text-center text-slate-500 text-xs border-t border-slate-900/60">
        &copy; <?= date('Y') ?> LinkPilot AI. Temporary file storage policy: 30 minutes retention.
    </footer>
    <script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
    <?php
    exit;
}

// Successful download: stream file securely
$filePath = $fileInfo['path'];
$filename = $fileInfo['filename'];
$mimeType = $fileInfo['mime_type'];
$size = $fileInfo['size'];

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . $size);

readfile($filePath);
exit(0);
