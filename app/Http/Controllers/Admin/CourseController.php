<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Courses\ArchiveCourseAction;
use App\Actions\Courses\UpdateCourseAction;
use App\Enums\AuditAction;
use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Models\Course;
use App\Services\Audit\AuditLogger;
use App\Services\Courses\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Course::class);

        $courses = Course::query()
            ->with(['category', 'instructor'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $courses,
        ]);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $this->authorize('create', Course::class);

        $course = new Course($request->validated());
        $course->status = CourseStatus::ACTIVE->value;
        $course->is_featured = false;
        $course->save();

        app(CourseService::class)->flushListCache();

        AuditLogger::logModelChange(AuditAction::COURSE_CREATED, $course);

        return response()->json([
            'ok' => true,
            'data' => $course,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $course = Course::query()->with(['category', 'instructor', 'telegramChannel'])->findOrFail($id);

        $this->authorize('view', $course);

        return response()->json([
            'ok' => true,
            'data' => $course,
        ]);
    }

    public function update(StoreCourseRequest $request, int $id, UpdateCourseAction $action): JsonResponse
    {
        $course = Course::query()->findOrFail($id);

        $this->authorize('update', $course);

        $course = $action->execute($course, $request->validated(), auth()->user()?->id);

        return response()->json([
            'ok' => true,
            'data' => $course,
        ]);
    }

    public function destroy(int $id, ArchiveCourseAction $action): JsonResponse
    {
        $course = Course::query()->findOrFail($id);

        $this->authorize('archive', $course);

        $action->execute($course, auth()->user()?->id);

        return response()->json([
            'ok' => true,
            'message' => 'Course archived.',
        ]);
    }
}
