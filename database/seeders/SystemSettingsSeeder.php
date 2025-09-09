<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'manager_only_threshold',
                'value' => '2000',
                'type' => 'number',
                'description' => 'Amount threshold for manager-only approval (no admin required) in AFN'
            ],
            [
                'key' => 'ceo_approval_threshold',
                'value' => '5000',
                'type' => 'number',
                'description' => 'Amount threshold requiring CEO (Admin) approval in AFN'
            ],
            [
                'key' => 'manager_approval_required',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Whether manager approval is required for all requests'
            ],
            [
                'key' => 'email_notifications_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable email notifications for approval requests'
            ],
            [
                'key' => 'auto_approval_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable auto-approval for small amounts'
            ],
            [
                'key' => 'auto_approval_threshold',
                'value' => '1000',
                'type' => 'number',
                'description' => 'Amount threshold for auto-approval (if enabled) in AFN'
            ],
            [
                'key' => 'approval_token_expiry_hours',
                'value' => '48',
                'type' => 'number',
                'description' => 'Approval token expiry time in hours'
            ],
            [
                'key' => 'system_name',
                'value' => 'Approval Workflow System',
                'type' => 'string',
                'description' => 'System name displayed in the application'
            ]
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
