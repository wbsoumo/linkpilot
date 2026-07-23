<?php
// backend/api/auth/google_config.php

require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

sendJsonResponse('success', 'Google Client ID fetched.', [
    'client_id' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : ''
]);
