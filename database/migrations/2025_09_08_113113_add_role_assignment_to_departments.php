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
        Schema::table('departments', function (Blueprint $table) {
            // Add role_id to specify which role belongs to this department
            $table->unsignedBigInteger('role_id')->nullable()->after('description');

            // Add foreign key constraint
            $table->foreign('role_id')->references('id')->on('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['role_id']);

            // Drop role_id column
            $table->dropColumn('role_id');
        });
    }
};
