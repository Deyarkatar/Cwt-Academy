<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.email', 'admin@cwtacademy.local')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_unauthorized_user_cannot_access_admin_apis(): void
    {
        $response = $this->getJson('/api/admin/dashboard');
        $response->assertUnauthorized();
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'suspended@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::SUSPENDED,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'suspended@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Invalid credentials or account unavailable.');
    }

    public function test_finance_manager_can_access_payment_endpoints(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FINANCE_MANAGER,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/payment-proofs');

        $response->assertOk()->assertJsonPath('ok', true);
    }

    public function test_finance_manager_cannot_manage_admin_users(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FINANCE_MANAGER,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/audit-logs');

        $response->assertForbidden(); // Audit logs restricted to super admins only
    }
}
