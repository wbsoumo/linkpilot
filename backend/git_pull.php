<?php
// backend/git_pull.php
header('Content-Type: text/plain');
echo shell_exec("git pull origin main 2>&1");
