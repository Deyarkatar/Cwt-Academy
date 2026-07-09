<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_login_via_admin_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'student@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'student@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Invalid credentials or account unavailable.');
    }

    public function test_non_admin_user_does_not_receive_admin_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student2@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'student2@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertForbidden();
        $this->assertNull($response->json('data.token'));
    }

    public function test_inactive_admin_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'suspended-admin@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::SUSPENDED,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'suspended-admin@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Invalid credentials or account unavailable.');
    }

    public function test_unverified_admin_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'unverified-admin@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'unverified-admin@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Invalid credentials or account unavailable.');
    }

    public function test_valid_admin_can_login(): void
    {
        User::factory()->create([
            'email' => 'valid-admin@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'valid-admin@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_finance_manager_can_login_via_admin_endpoint(): void
    {
        User::factory()->create([
            'email' => 'finance@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::FINANCE_MANAGER,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'finance@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_super_admin_can_login_via_admin_endpoint(): void
    {
        User::factory()->create([
            'email' => 'superadmin@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'superadmin@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_admin_token_does_not_use_wildcard_abilities(): void
    {
        $user = User::factory()->create([
            'email' => 'token-test@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'token-test@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertOk();
        $token = $response->json('data.token');
        $this->assertNotNull($token);

        // Verify the token has 'admin' ability, not wildcard
        $accessToken = $user->tokens()->first();
        $this->assertNotNull($accessToken);
        /** @var array<int, string> $abilities */
        $abilities = $accessToken->abilities;
        $this->assertContains('admin', $abilities);
        $this->assertNotContains('*', $abilities);
    }

    public function test_student_token_cannot_access_admin_dashboard(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        // Create a token without admin ability (simulating a student token)
        $token = $student->createToken('student-app', ['student'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_token_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $admin->createToken('admin', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard');

        $response->assertOk();
    }
}
