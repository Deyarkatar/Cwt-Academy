<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Jobs\AuditLogJob;
use App\Jobs\CacheBustJob;
use App\Jobs\NotificationJob;
use App\Jobs\SendVerificationEmailJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests for Fix 2: Queue infrastructure.
 */
class QueueInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_job_is_dispatchable(): void
    {
        Queue::fake();

        AuditLogJob::dispatch(
            action: AuditAction::LOGIN,
            entityType: 'User',
            entityId: 1,
            oldValues: null,
            newValues: null,
            actorId: 1,
            actorType: 'App\Models\User',
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            requestId: 'test-req-1',
        );

        Queue::assertPushed(AuditLogJob::class, 1);
    }

    public function test_send_verification_email_job_is_dispatchable(): void
    {
        Queue::fake();

        SendVerificationEmailJob::dispatch(1);

        Queue::assertPushed(SendVerificationEmailJob::class, 1);
    }

    public function test_cache_bust_job_is_dispatchable(): void
    {
        Queue::fake();

        CacheBustJob::dispatch(
            keys: ['test-key'],
            tags: ['test-tag'],
            bumpVersion: true,
        );

        Queue::assertPushed(CacheBustJob::class, 1);
    }

    public function test_notification_job_is_dispatchable(): void
    {
        Queue::fake();

        NotificationJob::dispatch(
            recipientUserId: 1,
            type: 'info',
            title: 'Test',
            body: 'Body',
            actionUrl: '/test',
        );

        Queue::assertPushed(NotificationJob::class, 1);
    }

    public function test_send_verification_email_job_handles_missing_user(): void
    {
        $job = new SendVerificationEmailJob(999999);
        $job->handle();

        $this->assertNull(User::find(999999));
    }

    public function test_send_verification_email_job_skips_verified_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $job = new SendVerificationEmailJob($user->id);
        $job->handle();

        $freshUser = User::find($user->id);
        $this->assertNotNull($freshUser);
        $this->assertTrue($freshUser->hasVerifiedEmail());
    }

    public function test_audit_log_job_unique_id_is_deterministic(): void
    {
        $job1 = new AuditLogJob(
            AuditAction::LOGIN,
            'User',
            1,
            null,
            null,
            1,
            'App\Models\User',
            '127.0.0.1',
            'Test',
            'req-1',
        );

        $this->assertEquals('audit-log-req-1-LOGIN-1', $job1->uniqueId());
    }
}
