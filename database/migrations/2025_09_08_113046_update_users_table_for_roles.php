<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add role_id column
            $table->unsignedBigInteger('role_id')->after('department_id');

            // Remove the old role enum column
            $table->dropColumn('role');

            // Add foreign key constraint
            $table->foreign('role_id')->references('id')->on('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['role_id']);

            // Drop role_id column
            $table->dropColumn('role_id');

            // Add back the old role enum column
            $table->enum('role', ['Employee', 'Manager', 'SalesManager', 'CEO', 'Procurement', 'Admin'])->default('Employee');
        });
    }
};
