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
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delegator_id'); // User who is delegating
            $table->unsignedBigInteger('delegate_id'); // User who receives the delegation
            $table->unsignedBigInteger('workflow_step_id'); // Specific workflow step (required)
            $table->unsignedBigInteger('department_id')->nullable(); // Department scope (null for all departments)
            $table->string('delegation_type')->default('approval'); // approval, notification, etc.
            $table->text('reason')->nullable(); // Reason for delegation
            $table->timestamp('starts_at')->nullable(); // When delegation becomes active
            $table->timestamp('expires_at')->nullable(); // When delegation expires
            $table->boolean('is_active')->default(true);
            $table->boolean('can_delegate_further')->default(false); // Can delegate further
            $table->json('permissions')->nullable(); // Specific permissions for this delegation
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('delegator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('delegate_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('workflow_step_id')->references('id')->on('workflow_steps')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');

            // Indexes for performance
            $table->index(['delegator_id', 'is_active'], 'del_delegator_active');
            $table->index(['delegate_id', 'is_active'], 'del_delegate_active');
            $table->index(['workflow_step_id', 'is_active'], 'del_step_active');
            $table->index(['delegation_type', 'is_active'], 'del_type_active');
            $table->index(['starts_at', 'expires_at'], 'del_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegations');
    }
};
