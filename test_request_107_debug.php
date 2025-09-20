<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Request 107 Manager Actions Debug\n";
echo "========================================\n\n";

// Test with request 107
$request = \App\Models\Request::with(['employee.department'])->find(107);

if (!$request) {
    echo "Request 107 not found!\n";
    exit;
}

echo "Request Details:\n";
echo "ID: " . $request->id . "\n";
echo "Status: " . $request->status . "\n";
echo "Employee: " . $request->employee->full_name . "\n";
echo "Department: " . $request->employee->department->name . "\n";
echo "Department ID: " . $request->employee->department_id . "\n\n";

// Get workflow steps
$workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);
echo "Workflow steps count: " . $workflowSteps->count() . "\n\n";

// Test each step
foreach ($workflowSteps as $index => $step) {
    echo "Step " . ($index + 1) . ": " . $step->name . "\n";
    echo "  Type: " . $step->step_type . "\n";
    echo "  Order: " . $step->order_index . "\n";

    $assignedUsers = $step->getAssignedUsers($request);
    echo "  Assigned users: " . $assignedUsers->count() . "\n";
    foreach ($assignedUsers as $user) {
        echo "    - " . $user->full_name . " (" . $user->role->name . " - " . $user->department->name . ")\n";
    }
    echo "\n";
}

// Test with manager users
$managers = \App\Models\User::whereHas('role', function($query) {
    $query->where('name', 'manager');
})->where('department_id', 1)->get(); // NOC department

echo "NOC Managers:\n";
foreach ($managers as $manager) {
    echo "  - " . $manager->full_name . " (ID: " . $manager->id . ")\n";
}

echo "\nTesting canPerformAction logic for each manager:\n";

foreach ($managers as $manager) {
    echo "\n=== Testing with manager: " . $manager->full_name . " ===\n";

    // Simulate frontend logic
    $user = $manager;
    $requestData = $request->toArray();

    // Check basic conditions
    if (!$user || !$requestData) {
        echo "  - Failed: No user or request\n";
        continue;
    }

    // Check status
    if (!in_array($requestData['status'], ['Pending', 'Pending Approval', 'Pending Procurement Verification'])) {
        echo "  - Failed: Wrong status (" . $requestData['status'] . ")\n";
        continue;
    }
    echo "  - Status check passed: " . $requestData['status'] . "\n";

    // Check if admin
    if ($user->role->name === 'admin') {
        echo "  - Passed: Admin user\n";
        continue;
    }

    // Check if manager
    if ($user->role->name === 'manager') {
        echo "  - Manager user detected\n";

        // Check department
        if ($request->employee->department_id === $user->department_id) {
            echo "  - Department check passed: Same department\n";
        } else {
            echo "  - Failed: Different department (Request: " . $request->employee->department_id . ", User: " . $user->department_id . ")\n";
            continue;
        }
    }

    // Check if user has already approved/rejected
    $hasApproved = \App\Models\AuditLog::where('request_id', $request->id)
        ->where('user_id', $user->id)
        ->whereIn('action', ['Approved', 'Rejected'])
        ->exists();

    if ($hasApproved) {
        echo "  - Failed: User has already approved/rejected\n";
        continue;
    }
    echo "  - No previous approval/rejection found\n";

    // Check isUserTurnToApprove
    echo "  - Checking isUserTurnToApprove...\n";

    // Simulate approval_workflow data
    $approvalWorkflow = [
        'waiting_for' => 'Manager',
        'steps' => []
    ];

    foreach ($workflowSteps as $index => $step) {
        $assignedUsers = $step->getAssignedUsers($request);
        $stepStatus = $index === 0 ? 'completed' : ($index === 1 ? 'pending' : 'waiting');

        $stepInfo = [
            'id' => $step->id,
            'name' => $step->name,
            'status' => $stepStatus,
            'assignments' => []
        ];

        foreach ($assignedUsers as $assignedUser) {
            $stepInfo['assignments'][] = [
                'assignment_type' => 'role',
                'assignable_name' => $assignedUser->role->name
            ];
        }

        $approvalWorkflow['steps'][] = $stepInfo;
    }

    // Find pending step
    $pendingStep = null;
    foreach ($approvalWorkflow['steps'] as $step) {
        if ($step['status'] === 'pending') {
            $pendingStep = $step;
            break;
        }
    }

    if (!$pendingStep) {
        echo "  - Failed: No pending step found\n";
        continue;
    }

    echo "  - Pending step found: " . $pendingStep['name'] . "\n";

    // Check if user is assigned to current step
    $isAssignedToCurrentStep = false;
    if (isset($pendingStep['assignments'])) {
        foreach ($pendingStep['assignments'] as $assignment) {
            if ($assignment['assignment_type'] === 'role' && $assignment['assignable_name'] === $user->role->name) {
                $isAssignedToCurrentStep = true;
                break;
            }
        }
    }

    if (!$isAssignedToCurrentStep) {
        echo "  - Failed: User not assigned to current step\n";
        continue;
    }
    echo "  - User assigned to current step\n";

    // Check department for manager role
    if ($user->role->name === 'manager') {
        if ($request->employee->department_id !== $user->department_id) {
            echo "  - Failed: Different department for manager\n";
            continue;
        }
        echo "  - Department check passed for manager\n";
    }

    echo "  - ✅ CAN PERFORM ACTION!\n";
}

echo "\nTest completed.\n";
