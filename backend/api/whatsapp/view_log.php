<?php
// backend/api/whatsapp/view_log.php

$logFile = __DIR__ . '/../../ai_debug.log';
if (!file_exists($logFile)) {
    echo "Log file does not exist yet. Please trigger the AI suggested reply first.";
    exit;
}

header('Content-Type: text/plain');
echo file_get_contents($logFile);
