<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_requests', function (Blueprint $table) {
            $table->id();
            $table->string('public_tracking_code', 64)->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('student_name', 120);
            $table->string('student_email', 255);
            $table->string('student_phone', 40)->nullable();
            $table->string('status', 30)->default('PENDING_PAYMENT');
            $table->string('payment_method', 20)->default('MANUAL');
            $table->text('student_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_requests');
    }
};
