<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users: indexes on role/status for fast authorization lookups.
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'idx_users_role');
            $table->index('status', 'idx_users_status');
            $table->index('last_login_at', 'idx_users_last_login');
        });

        // Courses: index on status/slug for public listings and lookups.
        Schema::table('courses', function (Blueprint $table) {
            $table->index('status', 'idx_courses_status');
            $table->index('category_id', 'idx_courses_category');
            $table->index('is_featured', 'idx_courses_featured');
            $table->index(['status', 'is_featured', 'published_at'], 'idx_courses_listing');
        });

        // Course requests: index on status/course_id for dashboard queries.
        Schema::table('course_requests', function (Blueprint $table) {
            $table->index('status', 'idx_course_requests_status');
            $table->index('course_id', 'idx_course_requests_course');
            $table->index('public_tracking_code', 'idx_course_requests_tracking');
            $table->index(['status', 'course_id'], 'idx_course_requests_status_course');
            $table->index('created_at', 'idx_course_requests_created');
        });

        // Payment proofs: index on status/course_request_id for admin review queries.
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->index('status', 'idx_payment_proofs_status');
            $table->index('course_request_id', 'idx_payment_proofs_request');
            $table->index(['status', 'course_request_id'], 'idx_payment_proofs_status_request');
            $table->index('reviewed_at', 'idx_payment_proofs_reviewed');
        });

        // Numeric integrity: price_iqd and amount_iqd must be non-negative.
        // Using DB::raw check constraints where the driver supports them.
        try {
            DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_price_nonnegative CHECK (price_iqd >= 0)');
        } catch (Throwable $e) {
            // Driver may not support CHECK; application-level validation remains.
        }

        try {
            DB::statement('ALTER TABLE payment_proofs ADD CONSTRAINT chk_payment_proofs_amount_nonnegative CHECK (amount_iqd >= 0)');
        } catch (Throwable $e) {
            // Driver may not support CHECK.
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_status');
            $table->dropIndex('idx_users_last_login');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('idx_courses_status');
            $table->dropIndex('idx_courses_category');
            $table->dropIndex('idx_courses_featured');
            $table->dropIndex('idx_courses_listing');
        });

        Schema::table('course_requests', function (Blueprint $table) {
            $table->dropIndex('idx_course_requests_status');
            $table->dropIndex('idx_course_requests_course');
            $table->dropIndex('idx_course_requests_tracking');
            $table->dropIndex('idx_course_requests_status_course');
            $table->dropIndex('idx_course_requests_created');
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropIndex('idx_payment_proofs_status');
            $table->dropIndex('idx_payment_proofs_request');
            $table->dropIndex('idx_payment_proofs_status_request');
            $table->dropIndex('idx_payment_proofs_reviewed');
        });

        try {
            DB::statement('ALTER TABLE courses DROP CONSTRAINT chk_courses_price_nonnegative');
        } catch (Throwable $e) {
            //
        }

        try {
            DB::statement('ALTER TABLE payment_proofs DROP CONSTRAINT chk_payment_proofs_amount_nonnegative');
        } catch (Throwable $e) {
            //
        }
    }
};
