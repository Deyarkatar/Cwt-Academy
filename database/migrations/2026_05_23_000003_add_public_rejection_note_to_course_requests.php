<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            $table->string('public_rejection_note', 500)->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            $table->dropColumn('public_rejection_note');
        });
    }
};
