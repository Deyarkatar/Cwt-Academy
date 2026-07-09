<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_from_admin_pages(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/admin/requests')->assertRedirect('/login');
    }

    public function test_student_blocked_from_admin_pages(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($student)->get('/admin')->assertRedirect('/dashboard');
        $this->actingAs($student)->get('/admin/requests')->assertRedirect('/dashboard');
    }

    public function test_admin_can_access_admin_pages(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_unverified_admin_redirected_from_admin_pages(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('verification.notice'));
    }

    public function test_suspended_admin_status_does_not_affect_admin_middleware(): void
    {
        // The admin middleware checks role, not status. Suspended admins
        // are still redirected by the admin middleware based on role.
        // Status-based blocking should be enforced at the auth guard level.
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::SUSPENDED,
            'email_verified_at' => now(),
        ]);

        // Admin middleware checks isAdmin() which checks role, not status
        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
