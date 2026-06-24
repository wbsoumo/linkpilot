<?php
// backend/api/extension/download.php
// Compresses the extension folder on-the-fly and sends it as a zip download.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth (forces user to be logged in to download the extension)
$user = JWTHelper::requireAuth();

$sourceDir = realpath(__DIR__ . '/../../../extension');
if (!$sourceDir || !is_dir($sourceDir)) {
    sendJsonResponse('error', 'Extension source folder not found on server.', [], 404);
}

// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    // If zip is not supported on this server configuration, fallback to redirecting to GitHub zip download link
    // E.g., redirect to: https://github.com/wbsoumo/linkpilot/archive/refs/heads/main.zip
    header('Location: https://github.com/wbsoumo/linkpilot/archive/refs/heads/main.zip');
    exit;
}

$zipName = 'linkpilot-chrome-extension.zip';
$tempZipFile = tempnam(sys_get_temp_dir(), 'zip');

$zip = new ZipArchive();
if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    sendJsonResponse('error', 'Failed to create temporary zip archive.', [], 500);
}

// Create recursive directory iterator
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    // Skip directories (they are added automatically)
    if (!$file->isDir()) {
        // Get real and relative path for current file
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($sourceDir) + 1);

        // Skip system/hidden files
        if (basename($filePath)[0] === '.') {
            continue;
        }

        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();

// Send the file to the browser
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tempZipFile));
header('Pragma: no-cache');
header('Expires: 0');

readfile($tempZipFile);

// Delete the temporary file
unlink($tempZipFile);
exit;
