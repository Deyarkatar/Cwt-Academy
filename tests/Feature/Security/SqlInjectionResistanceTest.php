<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SqlInjectionResistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_code_with_sql_injection_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/course-requests/1\' OR \'1\'=\'1');

        $response->assertNotFound();
    }

    public function test_tracking_code_with_union_injection_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/course-requests/UNIONSELECT12345');

        $response->assertNotFound();
    }

    public function test_course_request_status_filter_with_injection_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/course-requests?status=\' OR 1=1--');

        $response->assertStatus(422);
    }

    public function test_payment_proof_status_filter_with_injection_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/payment-proofs?status=\' OR 1=1--');

        $response->assertStatus(422);
    }

    public function test_login_email_with_sql_injection_does_not_error(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => "' OR 1=1--@cwtacademy.local",
            'password' => 'SecurePass123!',
        ]);

        // Should get normal validation error, not a 500 or successful login
        $this->assertContains($response->getStatusCode(), [422, 429]);
    }

    public function test_tracking_web_page_with_sql_injection_code_is_safe(): void
    {
        $response = $this->get('/track?code=\' OR \'1\'=\'1');

        $response->assertOk();
    }
}
