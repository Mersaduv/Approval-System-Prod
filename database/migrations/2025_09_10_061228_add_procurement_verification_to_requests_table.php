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
            // Add procurement verification fields
            $table->enum('procurement_status', ['Pending Verification', 'Verified', 'Not Available', 'Rejected'])->default('Pending Verification')->after('status');
            $table->decimal('final_price', 10, 2)->nullable()->after('amount');
            $table->text('procurement_notes')->nullable()->after('final_price');
            $table->unsignedBigInteger('verified_by')->nullable()->after('procurement_notes');
            $table->timestamp('verified_at')->nullable()->after('verified_by');

            // Add foreign key constraint
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['verified_by']);

            // Drop the added columns
            $table->dropColumn([
                'procurement_status',
                'final_price',
                'procurement_notes',
                'verified_by',
                'verified_at'
            ]);
        });
    }
};
