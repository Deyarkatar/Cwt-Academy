<?php

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Enums\TelegramAccessGrantStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use App\Models\TelegramAccessGrant;
use App\Models\TelegramChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseSpecificTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCourseWithChannel(string $slug, string $title, ?string $url): Course
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'slug' => $slug,
            'title' => $title,
            'status' => CourseStatus::ACTIVE,
        ]);

        if ($url !== null) {
            TelegramChannel::factory()->create([
                'course_id' => $course->id,
                'title' => "{$title} Channel",
                'telegram_url' => $url,
                'is_active' => true,
            ]);
        }

        return $course;
    }

    protected function makeApprovedRequestWithGrant(Course $course): CourseRequest
    {
        $request = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::APPROVED,
        ]);

        $grant = new TelegramAccessGrant([
            'course_request_id' => $request->id,
            'course_id' => $course->id,
            'student_name' => $request->student_name,
            'student_email' => $request->student_email,
            'student_phone' => $request->student_phone,
        ]);
        $grant->status = TelegramAccessGrantStatus::MANUALLY_ADDED->value;
        $grant->granted_at = now();
        $grant->save();

        return $request;
    }

    public function test_course_detail_page_does_not_link_to_global_hardcoded_telegram(): void
    {
        $course = $this->makeCourseWithChannel('alpha', 'Alpha Course', 'https://t.me/+alphaXYZ');

        $response = $this->get("/courses/{$course->slug}");
        $response->assertOk();
        $response->assertDontSee('https://t.me/cwtcourse');
        // Buy button should now go through the modal, not a Telegram URL
        $response->assertDontSeeText('https://t.me/+alphaXYZ');
    }

    public function test_tracking_page_shows_course_a_telegram_link_for_course_a_request(): void
    {
        $courseA = $this->makeCourseWithChannel('course-a', 'Course A', 'https://t.me/+aaaaa');
        $courseB = $this->makeCourseWithChannel('course-b', 'Course B', 'https://t.me/+bbbbb');

        $requestA = $this->makeApprovedRequestWithGrant($courseA);

        $response = $this->get(route('track', [
            'code' => $requestA->public_tracking_code,
            'email_hash' => hash('sha256', $requestA->student_email),
        ]));
        $response->assertOk();
        $response->assertSee('https://t.me/+aaaaa');
        $response->assertDontSee('https://t.me/+bbbbb');
    }

    public function test_tracking_page_shows_course_b_telegram_link_for_course_b_request(): void
    {
        $courseA = $this->makeCourseWithChannel('course-a2', 'Course A2', 'https://t.me/+aaaaaa');
        $courseB = $this->makeCourseWithChannel('course-b2', 'Course B2', 'https://t.me/+bbbbbb');

        $requestB = $this->makeApprovedRequestWithGrant($courseB);

        $response = $this->get(route('track', [
            'code' => $requestB->public_tracking_code,
            'email_hash' => hash('sha256', $requestB->student_email),
        ]));
        $response->assertOk();
        $response->assertSee('https://t.me/+bbbbbb');
        $response->assertDontSee('https://t.me/+aaaaaa');
    }

    public function test_tracking_page_shows_fallback_when_course_has_no_telegram_channel(): void
    {
        $course = $this->makeCourseWithChannel('no-tg', 'No Telegram', null);
        $request = $this->makeApprovedRequestWithGrant($course);

        $response = $this->get(route('track', [
            'code' => $request->public_tracking_code,
            'email_hash' => hash('sha256', $request->student_email),
        ]));
        $response->assertOk();
        $response->assertSee(__('tracking.telegram_channel_not_configured'));
    }

    public function test_tracking_page_shows_fallback_when_channel_inactive(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'slug' => 'inactive-ch',
            'status' => CourseStatus::ACTIVE,
        ]);
        TelegramChannel::factory()->create([
            'course_id' => $course->id,
            'telegram_url' => 'https://t.me/+disabled',
            'is_active' => false,
        ]);

        $request = $this->makeApprovedRequestWithGrant($course);

        $response = $this->get(route('track', [
            'code' => $request->public_tracking_code,
            'email_hash' => hash('sha256', $request->student_email),
        ]));
        $response->assertOk();
        $response->assertDontSee('https://t.me/+disabled');
        $response->assertSee(__('tracking.telegram_channel_not_configured'));
    }

    public function test_request_form_for_course_a_creates_request_with_correct_course_id(): void
    {
        Storage::fake('local');

        $courseA = $this->makeCourseWithChannel('course-aa', 'Course AA', 'https://t.me/+aa-link');
        $courseB = $this->makeCourseWithChannel('course-bb', 'Course BB', 'https://t.me/+bb-link');

        $this->post(route('course-requests.store'), [
            'course_id' => $courseA->id,
            'student_name' => 'A Student',
            'student_email' => 'a@example.com',
            'student_phone' => '+9647500000001',
            'student_city' => 'Erbil',
            'payment_proof' => $this->paymentProofFile(),
        ]);

        $requestA = CourseRequest::query()->where('course_id', $courseA->id)->first();
        $this->assertNotNull($requestA);
        $this->assertEquals('a@example.com', $requestA->student_email);

        $requestB = CourseRequest::query()->where('course_id', $courseB->id)->first();
        $this->assertNull($requestB);
    }

    public function test_modal_request_button_links_to_correct_per_course_request_route(): void
    {
        $course = $this->makeCourseWithChannel('cool-course', 'Cool Course', 'https://t.me/+cool-link');

        $response = $this->get("/courses/{$course->slug}");
        $response->assertOk();
        // Modal "Request this course" must point to per-course request URL
        $response->assertSee("/courses/{$course->slug}/request", false);
    }
}
