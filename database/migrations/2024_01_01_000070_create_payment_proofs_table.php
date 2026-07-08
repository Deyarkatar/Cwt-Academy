<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_request_id')->constrained('course_requests')->cascadeOnDelete();
            $table->unsignedInteger('amount_iqd');
            $table->string('sender_name', 255)->nullable();
            $table->string('transaction_reference', 120)->nullable()->unique();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_mime', 100)->nullable();
            $table->unsignedInteger('proof_size_bytes')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
