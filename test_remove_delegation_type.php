<?php

require_once 'vendor/autoload.php';

use App\Models\Delegation;
use App\Models\User;
use App\Http\Controllers\Api\DelegationController;
use Illuminate\Http\Request;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Remove Delegation Type ===\n\n";

$hrUser = User::where('email', 'hr@company.com')->first();
$nocmUser = User::where('email', 'nocm@company.com')->first();

echo "HR User: {$hrUser->email} (ID: {$hrUser->id})\n";
echo "NOCM User: {$nocmUser->email} (ID: {$nocmUser->id})\n\n";

// Test creating delegation without delegation_type
$controller = new DelegationController();
$mockRequest = new Request();
$mockRequest->merge([
    'delegate_id' => $hrUser->id,
    'workflow_step_id' => 21, // Manager Approval
    'reason' => 'Test delegation without delegation_type',
    'starts_at' => now()->addDay()->format('Y-m-d'),
    'expires_at' => now()->addDays(2)->format('Y-m-d')
]);
$mockRequest->setUserResolver(function () use ($nocmUser) {
    return $nocmUser;
});

try {
    $response = $controller->store($mockRequest);
    $data = $response->getData(true);

    echo "Create delegation response:\n";
    echo "- Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
    echo "- Message: " . ($data['message'] ?? 'No message') . "\n";

    if ($data['success']) {
        $delegation = Delegation::latest()->first();
        echo "- Delegation ID: {$delegation->id}\n";
        echo "- Delegation type: " . ($delegation->delegation_type ?? 'NULL') . "\n";
        echo "- Workflow step: {$delegation->workflowStep->name}\n";

        // Clean up
        $delegation->delete();
        echo "- Delegation deleted for cleanup\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
