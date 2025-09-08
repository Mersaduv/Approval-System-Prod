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
        Schema::create('approval_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('approver_id');
            $table->string('action_type'); // approve, reject, forward
            $table->timestamp('expires_at');
            $table->integer('usage_count')->default(0);
            $table->integer('max_usage')->default(1);
            $table->boolean('is_used')->default(false);
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('requests');
            $table->foreign('approver_id')->references('id')->on('users');
            $table->index(['token', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_tokens');
    }
};
