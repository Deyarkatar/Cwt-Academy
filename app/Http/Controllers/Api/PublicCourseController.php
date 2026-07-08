<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Courses\CourseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCourseController extends Controller
{
    public function __construct(
        protected CourseService $courseService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['category', 'level', 'language', 'search']);
        $courses = $this->courseService->listActive($filters);

        return response()->json([
            'ok' => true,
            'data' => $courses->through(function ($course) {
                return $this->serializePublicCourse($course);
            }),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $course = $this->courseService->getActiveBySlug($slug);

        if (! $course) {
            return response()->json([
                'ok' => false,
                'message' => 'Course not found.',
            ], 404);
        }

        $hasTelegram = $course->telegramChannel !== null && $course->telegramChannel->is_active;

        return response()->json([
            'ok' => true,
            'data' => [
                'course' => $this->serializePublicCourse($course),
                'delivery_method' => $hasTelegram ? 'private_telegram_channel' : null,
                'delivery_explanation' => $hasTelegram
                    ? 'Course content is delivered through a private Telegram channel after payment approval.'
                    : 'Contact support for course access details.',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePublicCourse(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'short_description' => $course->short_description,
            'description' => $course->description,
            'price_iqd' => $course->price_iqd,
            'level' => $course->level->value,
            'language' => $course->language->value,
            'category' => $course->category?->name,
            'instructor' => $course->instructor?->name,
            'published_at' => $course->published_at instanceof Carbon
                ? $course->published_at->toIso8601String()
                : $course->published_at,
        ];
    }
}
