<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\View\View;

class CourseRequestFormController extends Controller
{
    public function show(string $slug): View
    {
        $course = Course::active()
            ->with(['category', 'instructor'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.request-form', ['course' => $course]);
    }
}
