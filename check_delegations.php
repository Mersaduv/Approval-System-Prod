<?php

require_once 'vendor/autoload.php';

use App\Models\Delegation;
use App\Models\User;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Delegations ===\n\n";

$hrUser = User::where('email', 'hr@company.com')->first();
echo "HR User: {$hrUser->email} (ID: {$hrUser->id})\n\n";

// Check delegations for hr@company.com
$delegations = Delegation::where('delegate_id', $hrUser->id)->get();

echo "Total delegations for hr@company.com: " . $delegations->count() . "\n\n";

foreach ($delegations as $delegation) {
    echo "Delegation ID: {$delegation->id}\n";
    echo "- Active: " . ($delegation->is_active ? 'Yes' : 'No') . "\n";
    echo "- Type: {$delegation->delegation_type}\n";
    echo "- Workflow Step: {$delegation->workflow_step_id}\n";
    echo "- Delegator: {$delegation->delegator->email}\n";
    echo "- Starts: {$delegation->starts_at}\n";
    echo "- Expires: {$delegation->expires_at}\n";
    echo "- Created: {$delegation->created_at}\n";
    echo "- Updated: {$delegation->updated_at}\n";
    echo "---\n";
}

echo "\n=== Check Complete ===\n";
