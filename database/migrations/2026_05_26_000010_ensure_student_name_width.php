<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure student_name column can hold at least 255 characters
     * to match the FormRequest validation limit.
     */
    public function up(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            $table->string('student_name', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            $table->string('student_name', 120)->change();
        });
    }
};
