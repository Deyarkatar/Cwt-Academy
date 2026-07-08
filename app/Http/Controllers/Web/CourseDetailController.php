<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Courses\CourseService;
use Illuminate\View\View;

class CourseDetailController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    public function show(string $slug): View
    {
        $course = $this->courseService->getActiveBySlug($slug);

        abort_if($course === null, 404);

        $trackingCode = session('latest_course_request.'.$course->id);

        return view('public.course-detail', [
            'course' => $course,
            'trackingCode' => $trackingCode,
        ]);
    }
}
