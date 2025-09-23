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
        Schema::table('workflow_step_assignments', function (Blueprint $table) {
            $table->boolean('can_approve')->default(false)->after('is_required');
            $table->boolean('can_verify')->default(false)->after('can_approve');
            $table->boolean('can_notify')->default(false)->after('can_verify');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_step_assignments', function (Blueprint $table) {
            $table->dropColumn(['can_approve', 'can_verify', 'can_notify']);
        });
    }
};
