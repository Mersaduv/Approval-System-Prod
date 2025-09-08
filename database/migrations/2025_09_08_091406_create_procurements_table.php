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
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->enum('status', ['Ordered', 'Delivered', 'Failed'])->default('Ordered');
            $table->decimal('final_cost', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('requests');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
