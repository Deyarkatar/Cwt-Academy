<?php

namespace Tests\Feature\Security;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_cannot_be_mass_assigned_with_sensitive_fields(): void
    {
        $audit = new AuditLog([
            'actor_id' => 1,
            'actor_type' => 'User',
            'action' => AuditAction::LOGIN,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'hacked_field' => 'malicious',
        ]);

        $this->assertNull($audit->getAttribute('hacked_field'));
    }

    public function test_audit_log_cannot_be_updated_after_creation(): void
    {
        $audit = AuditLog::create([
            'actor_id' => 1,
            'actor_type' => 'User',
            'action' => AuditAction::LOGIN,
            'entity_type' => 'User',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');

        $audit->action = AuditAction::LOGIN_FAILED;
        $audit->save();
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $audit = AuditLog::create([
            'actor_id' => 1,
            'actor_type' => 'User',
            'action' => AuditAction::LOGIN,
            'entity_type' => 'User',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');

        $audit->delete();
    }

    public function test_failed_login_is_logged(): void
    {
        $this->postJson('/api/admin/login', [
            'email' => 'audit-test@cwtacademy.local',
            'password' => 'WrongPass123!',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LOGIN_FAILED->value,
        ]);
    }

    public function test_sensitive_data_is_redacted_from_audit_payload(): void
    {
        $redactKeys = config('security.audit_redact_keys', []);
        $this->assertContains('password', $redactKeys);
        $this->assertContains('token', $redactKeys);
        $this->assertContains('secret', $redactKeys);
    }
}
