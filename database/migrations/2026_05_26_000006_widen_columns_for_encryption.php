<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            $table->string('student_name', 512)->change();
            $table->string('student_email', 512)->change();
            $table->string('student_phone', 512)->nullable()->change();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->string('sender_name', 512)->nullable()->change();
            $table->string('transaction_reference', 512)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            $table->string('student_name', 255)->change();
            $table->string('student_email', 255)->change();
            $table->string('student_phone', 50)->change();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->string('sender_name', 255)->change();
            $table->string('transaction_reference', 255)->change();
        });
    }
};
