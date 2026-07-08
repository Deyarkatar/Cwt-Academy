<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Courses\CourseService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    public function index(): View
    {
        try {
            $courses = $this->courseService->featuredForHome(3);
        } catch (\Throwable $e) {
            report($e);
            $courses = collect();
        }

        return view('public.home', ['courses' => $courses]);
    }
}
