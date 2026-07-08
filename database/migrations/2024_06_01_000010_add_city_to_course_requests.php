<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('course_requests', 'student_city')) {
                $table->string('student_city', 80)->nullable()->after('student_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            if (Schema::hasColumn('course_requests', 'student_city')) {
                $table->dropColumn('student_city');
            }
        });
    }
};
