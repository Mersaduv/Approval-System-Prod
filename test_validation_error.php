<?php

require_once 'vendor/autoload.php';

use App\Models\Delegation;
use App\Models\User;
use App\Http\Controllers\Api\DelegationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Validation Error ===\n\n";

$hrUser = User::where('email', 'hr@company.com')->first();
$nocmUser = User::where('email', 'nocm@company.com')->first();

echo "HR User: {$hrUser->email} (ID: {$hrUser->id})\n";
echo "NOCM User: {$nocmUser->email} (ID: {$nocmUser->id})\n\n";

// Test validation manually
$requestData = [
    'delegate_id' => $hrUser->id,
    'workflow_step_id' => 21, // Manager Approval
    'reason' => 'Test delegation without delegation_type',
    'starts_at' => now()->format('Y-m-d'),
    'expires_at' => now()->addHours(2)->format('Y-m-d')
];

$validator = Validator::make($requestData, [
    'delegate_id' => 'required|exists:users,id',
    'workflow_step_id' => 'required|exists:workflow_steps,id',
    'reason' => 'nullable|string|max:1000',
    'starts_at' => 'nullable|date|after_or_equal:yesterday',
    'expires_at' => 'nullable|date|after:starts_at',
    'permissions' => 'nullable|array'
]);

if ($validator->fails()) {
    echo "Validation errors:\n";
    foreach ($validator->errors()->all() as $error) {
        echo "- {$error}\n";
    }
} else {
    echo "Validation passed!\n";
}

echo "\n=== Test Complete ===\n";
