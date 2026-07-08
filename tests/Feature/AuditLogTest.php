<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_written_for_sensitive_actions(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => AuditAction::LOGIN->value,
            'entity_type' => 'User',
        ]);
    }

    public function test_audit_logs_are_queryable_by_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        AuditLog::create([
            'action' => AuditAction::COURSE_CREATED,
            'entity_type' => 'Course',
            'entity_id' => 1,
            'actor_id' => $admin->id,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/audit-logs');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data.data');
    }
}
