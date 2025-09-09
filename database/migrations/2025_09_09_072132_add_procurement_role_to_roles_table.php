<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert the Procurement role
        DB::table('roles')->insert([
            'name' => 'procurement',
            'description' => 'Procurement team member responsible for handling approved purchase requests',
            'permissions' => json_encode([
                'view_approved_requests',
                'update_procurement_status',
                'manage_procurement',
                'view_procurement_reports',
                'view_own_requests'
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the Procurement role
        DB::table('roles')->where('name', 'procurement')->delete();
    }
};
