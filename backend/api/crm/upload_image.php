<?php
// backend/api/crm/upload_image.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Authenticate user
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method. Only POST is allowed.', [], 405);
}

if (!isset($_FILES['image']) && !isset($_FILES['file'])) {
    sendJsonResponse('error', 'No image file uploaded.', [], 400);
}

$file = $_FILES['image'] ?? $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendJsonResponse('error', 'File upload error code: ' . $file['error'], [], 400);
}

// 2 MB Size Validation
$maxSizeBytes = 2 * 1024 * 1024;
if ($file['size'] > $maxSizeBytes) {
    sendJsonResponse('error', 'File size exceeds the 2MB maximum limit. Please select a smaller image.', [], 400);
}

// MIME Type Validation
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    sendJsonResponse('error', 'Invalid file type. Only JPEG, PNG, GIF, WEBP, and SVG images are allowed.', [], 400);
}

// Create uploads directory
$uploadDir = __DIR__ . '/../../uploads/email_images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$ext) $ext = 'jpg';
$uniqueName = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
$destination = $uploadDir . $uniqueName;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    $relativeUrl = 'backend/uploads/email_images/' . $uniqueName;
    
    sendJsonResponse('success', 'Image uploaded successfully to server', [
        'url' => $relativeUrl,
        'filename' => $uniqueName,
        'size' => $file['size']
    ]);
} else {
    sendJsonResponse('error', 'Failed to save uploaded image on server.', [], 500);
}
