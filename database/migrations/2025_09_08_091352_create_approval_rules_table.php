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
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->decimal('min_amount', 10, 2);
            $table->decimal('max_amount', 10, 2);
            $table->enum('approver_role', ['Employee', 'Manager', 'SalesManager', 'CEO', 'Procurement', 'Admin']);
            $table->integer('order');
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
