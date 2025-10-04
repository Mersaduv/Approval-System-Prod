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
            $table->string('bill_number')->nullable()->after('procurement_notes');
            $table->timestamp('bill_printed_at')->nullable()->after('bill_number');
            $table->unsignedBigInteger('bill_printed_by')->nullable()->after('bill_printed_at');
            $table->text('bill_notes')->nullable()->after('bill_printed_by');
            $table->decimal('bill_amount', 10, 2)->nullable()->after('bill_notes');
            $table->string('bill_status')->default('pending')->after('bill_amount'); // pending, printed, approved

            $table->foreign('bill_printed_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['bill_printed_by']);
            $table->dropColumn([
                'bill_number',
                'bill_printed_at',
                'bill_printed_by',
                'bill_notes',
                'bill_amount',
                'bill_status'
            ]);
        });
    }
};
