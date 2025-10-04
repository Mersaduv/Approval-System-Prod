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
        // First, delete any delegations with null workflow_step_id
        DB::table('delegations')->whereNull('workflow_step_id')->delete();

        // Then make the column not nullable
        Schema::table('delegations', function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_step_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delegations', function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_step_id')->nullable()->change();
        });
    }
};
