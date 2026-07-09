<?php

namespace Tests\Feature\Security;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XssProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_name_with_script_is_escaped_on_tracking_page(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'student_name' => '<script>alert("xss")</script>',
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $response = $this->get('/track?code='.$courseRequest->public_tracking_code);

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $content);
    }

    public function test_rejection_note_with_html_is_escaped(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'student_email' => 'xss@example.com',
            'status' => CourseRequestStatus::REJECTED,
            'public_rejection_note' => '<img src=x onerror=alert(1)>',
        ]);

        $hash = hash('sha256', 'xss@example.com');

        $response = $this->get('/track?code='.$courseRequest->public_tracking_code.'&email_hash='.$hash);

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $content);
    }

    public function test_course_request_form_does_not_reflect_xss_in_errors(): void
    {
        $response = $this->get('/courses?error=<script>alert(1)</script>');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
    }

    public function test_tracking_page_does_not_reflect_xss_in_code_param(): void
    {
        $response = $this->get('/track?code=<script>alert(1)</script>');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
    }
}
