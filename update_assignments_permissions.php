<?php

require_once 'vendor/autoload.php';

use App\Models\WorkflowStepAssignment;
use App\Models\WorkflowStep;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Updating Workflow Step Assignments Permissions ===\n\n";

// Update assignments based on workflow step type
$assignments = WorkflowStepAssignment::all();

foreach ($assignments as $assignment) {
    $step = $assignment->workflowStep;

    if (!$step) {
        continue;
    }

    $canApprove = false;
    $canVerify = false;
    $canNotify = false;

    // Set permissions based on step name/type
    $stepName = strtolower($step->name);

    if (strpos($stepName, 'approval') !== false || strpos($stepName, 'approve') !== false) {
        $canApprove = true;
    } elseif (strpos($stepName, 'verification') !== false || strpos($stepName, 'verify') !== false) {
        $canVerify = true;
    } elseif (strpos($stepName, 'notification') !== false || strpos($stepName, 'notify') !== false) {
        $canNotify = true;
    } else {
        // Default to approval for manager/CEO steps
        if (strpos($stepName, 'manager') !== false || strpos($stepName, 'ceo') !== false || strpos($stepName, 'admin') !== false) {
            $canApprove = true;
        } else {
            // Default to verification for other steps
            $canVerify = true;
        }
    }

    $assignment->update([
        'can_approve' => $canApprove,
        'can_verify' => $canVerify,
        'can_notify' => $canNotify
    ]);

    echo "Updated assignment for step '{$step->name}' (ID: {$assignment->id}): Approve={$canApprove}, Verify={$canVerify}, Notify={$canNotify}\n";
}

echo "\n=== Update Complete ===\n";
