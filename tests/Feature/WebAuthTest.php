<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_route_is_named_login(): void
    {
        // Throws RouteNotFoundException if a route named "login" is missing,
        // which would also break the auth middleware's redirect behaviour.
        $url = route('login');

        $this->assertStringEndsWith('/login', $url);
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_admin_can_login_via_web_session(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@test.local',
            'password' => 'SecurePass123!',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_student_login_redirects_to_dashboard_not_admin(): void
    {
        User::factory()->create([
            'email' => 'student@test.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->post('/login', [
            'email' => 'student@test.local',
            'password' => 'SecurePass123!',
        ])->assertRedirect('/dashboard');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@test.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->post('/login', [
            'email' => 'admin@test.local',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login_via_web(): void
    {
        User::factory()->create([
            'email' => 'admin@test.local',
            'password' => Hash::make('SecurePass123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::SUSPENDED,
        ]);

        $this->post('/login', [
            'email' => 'admin@test.local',
            'password' => 'SecurePass123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unauthenticated_user_redirected_to_login_from_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_redirected_to_login_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_student_blocked_from_admin_pages(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($student)
            ->getJson('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_access_all_admin_pages(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/requests')->assertOk();
        $this->actingAs($admin)->get('/admin/telegram-access')->assertOk();
    }

    public function test_logout_clears_session(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($admin)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_register_creates_student_user(): void
    {
        $this->post('/register', [
            'name' => 'New Student',
            'email' => 'new-student@test.local',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])->assertRedirect('/email/verify');

        $user = User::where('email', 'new-student@test.local')->first();

        $this->assertNotNull($user);
        $this->assertEquals(UserRole::STUDENT, $user->role);
        $this->assertEquals(UserStatus::ACTIVE, $user->status);
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@test.local']);

        $this->post('/register', [
            'name' => 'Duplicate',
            'email' => 'taken@test.local',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])->assertSessionHasErrors('email');
    }

    public function test_register_requires_password_confirmation(): void
    {
        $this->post('/register', [
            'name' => 'Mismatch',
            'email' => 'mismatch@test.local',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
    }
}
