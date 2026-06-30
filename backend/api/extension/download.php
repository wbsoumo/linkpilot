<?php
// backend/api/extension/download.php
// Redirects the user directly to the official Chrome Web Store listing for the extension.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth (forces user to be logged in to access the link)
$user = JWTHelper::requireAuth();

header('Location: https://chromewebstore.google.com/detail/gnemddfomigfkpidiakgcdpighonkjga?utm_source=item-share-cb');
exit;
