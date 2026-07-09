<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard_api(): void
    {
        $response = $this->getJson('/api/admin/dashboard');
        $response->assertUnauthorized();
    }

    public function test_student_cannot_access_admin_dashboard_api(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $student->createToken('test', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_dashboard_api(): void
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

    public function test_super_admin_can_access_admin_dashboard_api(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $admin->createToken('admin', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard');

        $response->assertOk();
    }

    public function test_finance_manager_can_access_admin_dashboard_api(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::FINANCE_MANAGER,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $manager->createToken('admin', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard');

        $response->assertOk();
    }

    public function test_token_without_admin_ability_cannot_access_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        // Token with non-admin ability
        $token = $admin->createToken('test', ['student'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_unverified_admin_cannot_access_admin_dashboard_api(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => null,
        ]);

        $token = $admin->createToken('admin', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }
}
