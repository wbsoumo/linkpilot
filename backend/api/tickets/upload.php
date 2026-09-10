<?php
// backend/api/tickets/upload.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

$user = JWTHelper::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error occurred.']);
    exit;
}

$file = $_FILES['attachment'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

if (!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid file format. Allowed: JPG, PNG, GIF, WEBP, PDF.']);
    exit;
}

if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File size exceeds 10MB limit.']);
    exit;
}

$uploadDir = __DIR__ . '/../../../dashboard/uploads/tickets/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$filename = 'tck_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
$targetPath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $fileUrl = 'uploads/tickets/' . $filename;
    echo json_encode([
        'status' => 'success',
        'file_url' => $fileUrl,
        'file_name' => $file['name']
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded file.']);
}
