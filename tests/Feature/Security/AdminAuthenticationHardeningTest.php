<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_obtain_admin_token(): void
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

    public function test_inactive_admin_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::SUSPENDED,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'inactive@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertForbidden();
    }

    public function test_unverified_admin_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'unverified@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'unverified@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_token_has_admin_ability_not_wildcard(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertOk();
        $token = $user->tokens()->first();
        $this->assertNotNull($token);
        $abilities = $token->abilities;
        $this->assertIsArray($abilities);
        $this->assertContains('admin', $abilities);
        $this->assertNotContains('*', $abilities);
    }

    public function test_nonexistent_user_returns_invalid_credentials(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => 'nobody@cwtacademy.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('admin', ['admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson('/api/admin/logout');

        $response->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_session_is_regenerated_on_web_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@cwtacademy.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->withSession(['old-session-id' => 'abc123'])
            ->post('/login', [
                'email' => 'admin@cwtacademy.local',
                'password' => 'SecurePass123!',
                'captcha_answer' => 'skip',
            ]);

        $response->assertRedirect('/admin');
        $this->assertNotEquals('abc123', session()->getId());
    }
}
