<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing API Response for Request 107\n";
echo "===================================\n\n";

// Test using controller
$workflowService = new \App\Services\WorkflowService(new \App\Services\NotificationService());
$controller = new \App\Http\Controllers\Api\RequestController($workflowService);

// Create a mock request with proper route
$request = new \Illuminate\Http\Request();
$request->setRouteResolver(function () {
    $route = new \Illuminate\Routing\Route(['GET'], '/api/requests/{id}', []);
    $route->setParameter('id', 107);
    return $route;
});

// Mock auth user (manager from NOC)
$authUser = \App\Models\User::find(32); // noc manager 2
if (!$authUser) {
    echo "Auth user not found!\n";
    exit;
}

// Set auth
\Auth::login($authUser);

try {
    $response = $controller->show($request, 107);
    $data = $response->getData(true);

    echo "API Response:\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";

    if ($data['success']) {
        $requestData = $data['data'];
        echo "Request ID: " . $requestData['id'] . "\n";
        echo "Status: " . $requestData['status'] . "\n";

        if (isset($requestData['approval_workflow'])) {
            echo "\nApproval Workflow Found!\n";
            echo "Waiting For: " . ($requestData['approval_workflow']['waiting_for'] ?? 'null') . "\n";
            echo "Can Approve: " . ($requestData['approval_workflow']['can_approve'] ? 'true' : 'false') . "\n";

            if (isset($requestData['approval_workflow']['steps'])) {
                echo "Steps Count: " . count($requestData['approval_workflow']['steps']) . "\n";
                foreach ($requestData['approval_workflow']['steps'] as $step) {
                    echo "  - Step: " . $step['name'] . " (Status: " . $step['status'] . ")\n";
                    if (isset($step['assignments'])) {
                        echo "    Assignments: " . count($step['assignments']) . "\n";
                        foreach ($step['assignments'] as $assignment) {
                            echo "      - Type: " . $assignment['assignment_type'] . ", Name: " . ($assignment['assignable_name'] ?? 'null') . "\n";
                        }
                    }
                }
            }
        } else {
            echo "\nNo approval_workflow found in response!\n";
        }
    } else {
        echo "Error: " . $data['message'] . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
