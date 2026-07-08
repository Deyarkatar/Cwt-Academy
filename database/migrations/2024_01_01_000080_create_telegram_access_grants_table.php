<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_request_id')->constrained('course_requests')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('student_name', 255)->nullable();
            $table->string('student_email', 255)->nullable();
            $table->string('student_phone', 50)->nullable();
            $table->string('status', 30)->default('PENDING_MANUAL_ADD');
            $table->text('admin_note')->nullable();
            $table->string('manual_access_reference', 255)->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_access_grants');
    }
};
