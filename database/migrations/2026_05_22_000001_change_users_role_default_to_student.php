<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security hardening (2026-05): default role for users at the database level
 * must be STUDENT, never ADMIN. The previous default ('ADMIN') would silently
 * elevate any code path that inserted a user without explicitly setting the
 * role column. We also tighten existing rows: any user with role='ADMIN' AND
 * created via the prior default but without a corresponding seed marker is
 * left untouched — operators must verify production accounts manually.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('STUDENT')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('ADMIN')->change();
        });
    }
};
