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
        Schema::create('workflow_step_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')->constrained()->onDelete('cascade');
            $table->string('assignable_type'); // نوع تخصیص: user, role, department
            $table->unsignedBigInteger('assignable_id'); // ID کاربر، نقش یا دپارتمان
            $table->boolean('is_required')->default(true); // اجباری/اختیاری بودن تخصیص
            $table->integer('priority')->default(1); // اولویت (1 = بالاترین)
            $table->json('conditions')->nullable(); // شرایط اضافی برای تخصیص
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_step_assignments');
    }
};
