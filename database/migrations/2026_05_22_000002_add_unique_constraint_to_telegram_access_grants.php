<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security hardening (2026-05): enforce at the database level that a single
 * CourseRequest can only ever have one TelegramAccessGrant row. Application
 * code already prevents duplicates via lockForUpdate(), but the constraint
 * is defense-in-depth for any future background job or console command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_access_grants', function (Blueprint $table) {
            $table->unique('course_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_access_grants', function (Blueprint $table) {
            $table->dropUnique(['course_request_id']);
        });
    }
};
