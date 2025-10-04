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
        Schema::table('requests', function (Blueprint $table) {
            // Modify the status enum to include new values
            $table->enum('status', [
                'Pending',
                'Approved',
                'Rejected',
                'Pending Procurement',
                'Ordered',
                'Delivered',
                'Cancelled'
            ])->default('Pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            // Revert to original enum values
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Delivered'])
                  ->default('Pending')
                  ->change();
        });
    }
};
