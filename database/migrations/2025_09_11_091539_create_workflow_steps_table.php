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
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام مرحله (مثل: Procurement Verification, Manager Approval, CEO Approval)
            $table->text('description')->nullable(); // توضیحات مرحله
            $table->integer('order_index'); // ترتیب مرحله (0, 1, 2, ...)
            $table->boolean('is_active')->default(true); // فعال/غیرفعال بودن مرحله
            $table->boolean('is_required')->default(true); // اجباری/اختیاری بودن مرحله
            $table->json('conditions')->nullable(); // شرایط اجرای مرحله (مثل: amount > 1000)
            $table->string('step_type')->default('approval'); // نوع مرحله: approval, verification, notification
            $table->integer('timeout_hours')->nullable(); // زمان انقضای مرحله (ساعت)
            $table->boolean('auto_approve_if_condition_met')->default(false); // تایید خودکار در صورت برقراری شرایط
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
