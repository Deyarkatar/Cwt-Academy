<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only apply CHECK constraints if database supports them
        // SQLite supports CHECK but uses different ALTER syntax
        // MySQL 8.0+ supports CHECK constraints

        if (DB::connection()->getDriverName() !== 'sqlite') {
            // Course requests status check
            DB::statement("ALTER TABLE course_requests ADD CONSTRAINT chk_course_request_status CHECK (status IN ('PENDING_PAYMENT', 'PENDING_REVIEW', 'APPROVED', 'REJECTED', 'EXPIRED', 'REVOKED'))");

            // Payment proofs status check
            DB::statement("ALTER TABLE payment_proofs ADD CONSTRAINT chk_payment_proof_status CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED'))");

            // Telegram access grants status check
            DB::statement("ALTER TABLE telegram_access_grants ADD CONSTRAINT chk_telegram_access_status CHECK (status IN ('PENDING_MANUAL_ADD', 'MANUALLY_ADDED', 'REVOKED'))");
        }
        // Note: For SQLite, CHECK constraints must be added during CREATE TABLE.
        // The base migration tables already have ENUM casting which provides
        // application-level validation, so skipping CHECK for SQLite is safe.
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE course_requests DROP CONSTRAINT IF EXISTS chk_course_request_status');
            DB::statement('ALTER TABLE payment_proofs DROP CONSTRAINT IF EXISTS chk_payment_proof_status');
            DB::statement('ALTER TABLE telegram_access_grants DROP CONSTRAINT IF EXISTS chk_telegram_access_status');
        }
    }
};
