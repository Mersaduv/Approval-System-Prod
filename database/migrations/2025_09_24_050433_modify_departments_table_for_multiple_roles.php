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
        // First, migrate existing data to the pivot table (only if not already migrated)
        $departments = DB::table('departments')->whereNotNull('role_id')->get();

        foreach ($departments as $department) {
            // Check if this combination already exists
            $exists = DB::table('department_role')
                ->where('department_id', $department->id)
                ->where('role_id', $department->role_id)
                ->exists();

            if (!$exists) {
                DB::table('department_role')->insert([
                    'department_id' => $department->id,
                    'role_id' => $department->role_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Remove the old role_id column
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the role_id column
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained()->onDelete('set null');
        });

        // Migrate data back from pivot table
        $departmentRoles = DB::table('department_role')->get();

        foreach ($departmentRoles as $departmentRole) {
            DB::table('departments')
                ->where('id', $departmentRole->department_id)
                ->update(['role_id' => $departmentRole->role_id]);
        }
    }
};
