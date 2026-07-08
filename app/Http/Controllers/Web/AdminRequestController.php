<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CourseRequest;
use Illuminate\View\View;

class AdminRequestController extends Controller
{
    public function index(): View
    {
        $requests = CourseRequest::with([
            'course.telegramChannel',
            'latestPaymentProof',
        ])
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.requests', ['requests' => $requests]);
    }
}
