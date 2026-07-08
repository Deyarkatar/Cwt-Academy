<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteBlankPageDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private function assertNotBlank(string $message, string $content): void
    {
        $this->assertNotEmpty(
            trim(strip_tags($content)),
            $message.' Response body is empty or contains only whitespace.'
        );
    }

    public function test_public_routes_render_content(): void
    {
        $routes = [
            '/' => 'home',
            '/courses' => 'courses',
            '/contact' => 'contact',
            '/track' => 'tracking',
            '/login' => 'login',
            '/register' => 'register',
            '/forgot-password' => 'forgot-password',
        ];

        foreach ($routes as $route => $label) {
            $response = $this->get($route);
            $content = $response->getContent();

            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 302], true),
                "Route {$route} ({$label}) returned HTTP {$response->getStatusCode()}."
            );

            if ($response->isOk()) {
                $this->assertIsString($content);
                $this->assertNotBlank(
                    "Route {$route} ({$label}) rendered a blank page.",
                    $content
                );
            }
        }
    }

    public function test_dynamic_course_routes_render_content(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'slug' => 'test-course',
            'title' => 'Test Course',
        ]);

        $routes = [
            '/courses/test-course' => 'course detail',
            '/courses/test-course/request' => 'request form',
        ];

        foreach ($routes as $route => $label) {
            $response = $this->get($route);
            $content = $response->getContent();

            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 302], true),
                "Route {$route} ({$label}) returned HTTP {$response->getStatusCode()}."
            );

            if ($response->isOk()) {
                $this->assertIsString($content);
                $this->assertNotBlank(
                    "Route {$route} ({$label}) rendered a blank page.",
                    $content
                );
            }
        }
    }

    public function test_authenticated_student_routes_render_content(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::STUDENT->value,
            'status' => UserStatus::ACTIVE->value,
            'email_verified_at' => now(),
        ]);

        $routes = [
            '/dashboard' => 'student dashboard',
            '/profile' => 'student profile',
        ];

        foreach ($routes as $route => $label) {
            $response = $this->actingAs($user)->get($route);
            $content = $response->getContent();

            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 302], true),
                "Route {$route} ({$label}) returned HTTP {$response->getStatusCode()}."
            );

            if ($response->isOk()) {
                $this->assertIsString($content);
                $this->assertNotBlank(
                    "Route {$route} ({$label}) rendered a blank page.",
                    $content
                );
            }
        }
    }

    public function test_authenticated_admin_routes_render_content(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'status' => UserStatus::ACTIVE->value,
            'email_verified_at' => now(),
        ]);

        $routes = [
            '/admin' => 'admin dashboard',
            '/admin/requests' => 'admin requests',
            '/admin/telegram-access' => 'admin telegram access',
        ];

        foreach ($routes as $route => $label) {
            $response = $this->actingAs($admin)->get($route);
            $content = $response->getContent();

            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 302], true),
                "Route {$route} ({$label}) returned HTTP {$response->getStatusCode()}."
            );

            if ($response->isOk()) {
                $this->assertIsString($content);
                $this->assertNotBlank(
                    "Route {$route} ({$label}) rendered a blank page.",
                    $content
                );
            }
        }
    }
}
