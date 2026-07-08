<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\Instructor;
use App\Services\Captcha\MathCaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicCourseRequestCaptchaTest extends TestCase
{
    use RefreshDatabase;

    private function activeCourse(): Course
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();

        return Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);
    }

    public function test_course_request_passes_when_no_captcha_driver_is_enabled(): void
    {
        Storage::fake('local');

        $course = $this->activeCourse();
        $file = $this->paymentProofFile();

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('course_requests', 1);
    }

    public function test_math_captcha_missing_answer_blocks_submission(): void
    {
        Storage::fake('local');
        Config::set('security.captcha.driver', 'math');

        $course = $this->activeCourse();
        $file = $this->paymentProofFile();

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
        ]);

        $response->assertSessionHasErrors('captcha_answer');
        $this->assertDatabaseCount('course_requests', 0);
    }

    public function test_math_captcha_invalid_answer_blocks_submission(): void
    {
        Storage::fake('local');
        Config::set('security.captcha.driver', 'math');

        $course = $this->activeCourse();
        $file = $this->paymentProofFile();

        // Generate a question so the session has an expected answer.
        app(MathCaptchaService::class)->generate();

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
            'captcha_answer' => '9999',
        ]);

        $response->assertSessionHasErrors('captcha_answer');
        $this->assertDatabaseCount('course_requests', 0);
    }

    public function test_math_captcha_valid_answer_allows_submission(): void
    {
        Storage::fake('local');
        Config::set('security.captcha.driver', 'math');

        $course = $this->activeCourse();
        $file = $this->paymentProofFile();

        $math = app(MathCaptchaService::class);
        $captcha = $math->generate();

        // Retrieve the stored answer from the session.
        $tokenRaw = session('captcha.math_token');
        $expectedHashRaw = session('captcha.math_answer');
        $this->assertIsString($tokenRaw);
        $this->assertIsString($expectedHashRaw);

        $token = $tokenRaw;
        $expectedHash = $expectedHashRaw;

        // Reverse the answer by brute-forcing the small integer space.
        $correctAnswer = null;
        for ($i = 0; $i <= 30; $i++) {
            if (hash_hmac('sha256', (string) $i, $token) === $expectedHash) {
                $correctAnswer = (string) $i;
                break;
            }
        }
        $this->assertNotNull($correctAnswer, 'Could not determine the generated math CAPTCHA answer.');

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
            'captcha_answer' => $correctAnswer,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('course_requests', 1);
    }

    public function test_turnstile_missing_token_blocks_submission(): void
    {
        Storage::fake('local');
        Config::set('security.captcha.driver', 'turnstile');

        $course = $this->activeCourse();
        $file = $this->paymentProofFile();

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
        ]);

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseCount('course_requests', 0);
    }

    public function test_turnstile_invalid_token_blocks_submission(): void
    {
        Storage::fake('local');
        Config::set('security.captcha.driver', 'turnstile');
        Config::set('security.captcha.turnstile.secret_key', 'test-secret');

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $course = $this->activeCourse();
        $file = $this->paymentProofFile();

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
            'cf-turnstile-response' => 'invalid-token',
        ]);

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseCount('course_requests', 0);
    }

    public function test_turnstile_valid_token_allows_submission(): void
    {
        Storage::fake('local');
        Config::set('security.captcha.driver', 'turnstile');
        Config::set('security.captcha.turnstile.secret_key', 'test-secret');

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'error-codes' => [],
            ]),
        ]);

        $course = $this->activeCourse();
        $file = $this->paymentProofFile();

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
            'cf-turnstile-response' => 'valid-token',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('course_requests', 1);
    }
}
