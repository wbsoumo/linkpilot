<?php
// backend/api/crm/diagnose_booking.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config.php';

try {
    $db = Database::getConnection();
    echo "Database Connected.\n";
    
    // Find active meeting_scheduled workflows
    $stmt = $db->query("SELECT * FROM automation_workflows WHERE trigger_type = 'meeting_scheduled' AND is_active = 1");
    $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($workflows) . " active workflows.\n";
    
    // Try requiring workflow_runner.php
    require_once __DIR__ . '/../../workflow_runner.php';
    echo "workflow_runner.php required successfully.\n";
    
    if (count($workflows) > 0) {
        $wf = $workflows[0];
        echo "Executing workflow ID: " . $wf['id'] . "\n";
        
        $vContext = [
            'sender_email' => 'test@example.com',
            'sender_name' => 'Diagnostic Test',
            'sender_phone' => '1234567890',
            'guest_email' => 'test@example.com',
            'guest_name' => 'Diagnostic Test',
            'meeting_title' => 'Diagnostic Test Meeting',
            'meeting_start' => date('Y-m-d H:i:s'),
            'meeting_end' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'meeting_location' => 'Google Meet',
            'time' => date('h:i A'),
            'date' => date('Y-m-d'),
            'contact_id' => 1
        ];
        
        WorkflowRunner::execute($wf['user_id'], $wf, $vContext);
        echo "Workflow executed successfully.\n";
    } else {
        echo "No workflows to execute.\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "TRACE:\n" . $e->getTraceAsString() . "\n";
}
