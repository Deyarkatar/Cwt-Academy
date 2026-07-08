<?php

namespace App\Http\Controllers\Web;

use App\Enums\CourseRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user) {
            return redirect('/login');
        }

        if ($user->isAdmin()) {
            return redirect('/admin');
        }

        $userId = $user->id;

        // Optimized count using two simple queries (still faster than 3 round-trips)
        $totalRequests = CourseRequest::where('user_id', $userId)->count();
        $activeCount = CourseRequest::where('user_id', $userId)
            ->where('status', CourseRequestStatus::APPROVED)
            ->count();
        $pendingCount = $totalRequests - $activeCount;

        // Separate paginated queries to avoid collection filtering
        $approvedRequests = CourseRequest::with([
            'course.telegramChannel',
            'latestPaymentProof',
            'telegramAccessGrant',
        ])
            ->where('user_id', $userId)
            ->where('status', CourseRequestStatus::APPROVED)
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'approved_page');

        $pendingRequests = CourseRequest::with([
            'course.telegramChannel',
            'latestPaymentProof',
            'telegramAccessGrant',
        ])
            ->where('user_id', $userId)
            ->where('status', '!=', CourseRequestStatus::APPROVED)
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'pending_page');

        $requests = CourseRequest::with([
            'course.telegramChannel',
            'latestPaymentProof',
            'telegramAccessGrant',
        ])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('student.dashboard', [
            'requests' => $requests,
            'approvedRequests' => $approvedRequests,
            'pendingRequests' => $pendingRequests,
            'totalRequests' => $totalRequests,
            'activeCount' => $activeCount,
            'pendingCount' => $pendingCount,
        ]);
    }
}
