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
        Schema::table('procurements', function (Blueprint $table) {
            // Modify the status enum to include new values
            $table->enum('status', ['Pending Procurement', 'Ordered', 'Delivered', 'Cancelled', 'Failed'])
                  ->default('Pending Procurement')
                  ->change();

            // Add procurement_user_id field
            $table->unsignedBigInteger('procurement_user_id')->nullable()->after('final_cost');
            $table->foreign('procurement_user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            // Drop the foreign key and column
            $table->dropForeign(['procurement_user_id']);
            $table->dropColumn('procurement_user_id');

            // Revert to original enum values
            $table->enum('status', ['Ordered', 'Delivered', 'Failed'])
                  ->default('Ordered')
                  ->change();
        });
    }
};
