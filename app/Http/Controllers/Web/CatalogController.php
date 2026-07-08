<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Courses\CourseService;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    public function index(): View
    {
        $courses = $this->courseService->listActive();

        return view('public.catalog', ['courses' => $courses]);
    }
}
