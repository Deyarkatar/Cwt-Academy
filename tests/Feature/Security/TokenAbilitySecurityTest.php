<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenAbilitySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_token_cannot_access_wildcard_only_routes(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $admin->createToken('admin', ['admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/dashboard');

        $response->assertOk();
    }

    public function test_student_token_cannot_access_admin_routes(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $student->createToken('student', ['*']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_token_without_admin_ability_cannot_access_admin_routes(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $admin->createToken('limited', ['read-only']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_expired_token_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $admin->createToken('expired', ['admin'], expiresAt: now()->subHour());

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/dashboard');

        $response->assertUnauthorized();
    }

    public function test_revoked_token_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $admin->createToken('test', ['admin']);
        $plainText = $token->plainTextToken;

        $admin->tokens()->delete();

        $response = $this->withHeader('Authorization', "Bearer {$plainText}")
            ->getJson('/api/admin/dashboard');

        $response->assertUnauthorized();
    }
}
