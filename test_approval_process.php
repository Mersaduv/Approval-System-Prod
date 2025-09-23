<?php

require_once 'vendor/autoload.php';

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Services\WorkflowService;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Approval Process\n";
echo "=======================\n\n";

// Get request 213
$request = RequestModel::find(213);
if (!$request) {
    echo "ERROR: Request 213 not found\n";
    exit(1);
}

$request->load('employee.role');

echo "Request ID: {$request->id}\n";
echo "Amount: {$request->amount}\n";
echo "Status: {$request->status}\n\n";

// Get workflow service
$notificationService = new \App\Services\NotificationService();
$workflowService = new WorkflowService($notificationService);

// Get CEO/Admin step
$ceoStep = WorkflowStep::find(22);
if (!$ceoStep) {
    echo "ERROR: CEO/Admin step not found\n";
    exit(1);
}

echo "CEO/Admin Step: {$ceoStep->name}\n";
echo "Is Completed: " . ($workflowService->isStepCompleted($request, $ceoStep) ? 'Yes' : 'No') . "\n\n";

// Get admin user (user ID 1)
$adminUser = User::find(1);
if (!$adminUser) {
    echo "ERROR: Admin user not found\n";
    exit(1);
}

echo "Admin User: {$adminUser->full_name}\n";

// Check if admin can approve this request
$canApprove = $workflowService->canApprove($request, $adminUser);
echo "Can Admin Approve: " . ($canApprove ? 'Yes' : 'No') . "\n";

// Get current step for admin
$currentStep = $workflowService->getCurrentStepForApprover($request, $adminUser);
if ($currentStep) {
    echo "Current Step for Admin: {$currentStep->name}\n";
} else {
    echo "No current step for admin\n";
}

echo "\nTesting approval process...\n";

try {
    // Simulate approval
    $result = $workflowService->approveRequest(213, 1, 'Test approval');
    echo "Approval result: " . ($result ? 'Success' : 'Failed') . "\n";

    // Refresh request
    $request->refresh();
    echo "New status: {$request->status}\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
