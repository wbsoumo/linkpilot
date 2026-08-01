<?php
// backend/force_update.php
header('Content-Type: text/plain');
echo "1. Resetting live server changes...\n";
echo shell_exec("git reset --hard origin/main 2>&1");
echo "\n2. Pulling latest code from GitHub...\n";
echo shell_exec("git pull origin main 2>&1");
