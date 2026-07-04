<?php
// backend/api/run_migration.php

// Delegate to the consolidated CRM migrations file to ensure all tables exist
require_once __DIR__ . '/crm/migrate.php';
