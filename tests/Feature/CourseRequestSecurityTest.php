<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseRequestSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    public function test_invalid_status_filter_returns_422(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('admin', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/course-requests?status=INVALID_STATUS');

        $response->assertStatus(422);
    }

    public function test_valid_status_filter_returns_results(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('admin', ['admin'])->plainTextToken;

        $course = Course::factory()->create();
        CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::APPROVED,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/course-requests?status=APPROVED');

        $response->assertOk();
    }

    public function test_no_status_filter_returns_all_requests(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('admin', ['admin'])->plainTextToken;

        $course = Course::factory()->create();
        CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);
        CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::APPROVED,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/course-requests');

        $response->assertOk();
    }

    public function test_student_cannot_access_admin_course_requests(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $token = $student->createToken('test', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/course-requests');

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_admin_course_requests(): void
    {
        $response = $this->getJson('/api/admin/course-requests');
        $response->assertUnauthorized();
    }

    public function test_sql_injection_attempt_in_status_filter_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('admin', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/course-requests?status=\' OR 1=1--');

        $response->assertStatus(422);
    }
}
