<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Auth\AccountLockoutService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests for Fix 4: Admin account lockout and suspicious login detection.
 */
class AdminAccountLockoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
            'password' => Hash::make('CorrectPassword123!'),
        ]);
    }

    public function test_account_lockout_after_five_failures(): void
    {
        $service = app(AccountLockoutService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->recordFailure('10.0.0.1', 'test@example.com');
        }

        $this->assertTrue($service->isLocked('10.0.0.1', 'test@example.com'));
    }

    public function test_success_clears_lockout(): void
    {
        $service = app(AccountLockoutService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->recordFailure('10.0.0.1', 'test@example.com');
        }

        $this->assertTrue($service->isLocked('10.0.0.1', 'test@example.com'));

        $service->recordSuccess('10.0.0.1', 'test@example.com');

        $this->assertFalse($service->isLocked('10.0.0.1', 'test@example.com'));
    }

    public function test_admin_login_returns_423_when_locked(): void
    {
        $service = app(AccountLockoutService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->recordFailure('127.0.0.1', 'admin@example.com');
        }

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
            'captcha_token' => 'fake-token',
        ]);

        $response->assertStatus(423);
        $response->assertJsonPath('message', 'Account temporarily locked due to too many failed attempts. Please try again later.');
    }

    public function test_repeated_failed_api_admin_login_triggers_lockout(): void
    {
        $admin = $this->createAdmin();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/admin/login', [
                'email' => $admin->email,
                'password' => 'wrong-password',
                'captcha_token' => 'fake-token',
            ]);

            $response->assertUnprocessable();
        }

        $response = $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
            'captcha_token' => 'fake-token',
        ]);

        $response->assertStatus(423);
    }

    public function test_successful_api_admin_login_clears_lockout(): void
    {
        $admin = $this->createAdmin();
        $service = app(AccountLockoutService::class);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/admin/login', [
                'email' => $admin->email,
                'password' => 'wrong-password',
                'captcha_token' => 'fake-token',
            ]);
        }

        $this->assertFalse($service->isLocked('127.0.0.1', strtolower($admin->email)));

        $response = $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'CorrectPassword123!',
            'captcha_token' => 'fake-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_web_login_lockout_returns_redirect_with_flash(): void
    {
        $admin = $this->createAdmin();
        $service = app(AccountLockoutService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->recordFailure('127.0.0.1', strtolower($admin->email));
        }

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
            'captcha_answer' => '',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Account temporarily locked due to too many failed attempts. Please try again later.')
            ->assertSessionHasInput('email', $admin->email);
    }

    public function test_lockout_records_failure_count(): void
    {
        $service = app(AccountLockoutService::class);

        $service->recordFailure('10.0.0.1', 'test@example.com');
        $service->recordFailure('10.0.0.1', 'test@example.com');

        $this->assertFalse($service->isLocked('10.0.0.1', 'test@example.com'));
    }

    public function test_suspicious_login_logging_on_high_attempts(): void
    {
        $service = app(AccountLockoutService::class);

        for ($i = 0; $i < 10; $i++) {
            $service->recordFailure('10.0.0.1', 'target@example.com');
        }

        $this->assertTrue($service->isLocked('10.0.0.1', 'target@example.com'));
    }
}
