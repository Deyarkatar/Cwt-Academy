<?php

namespace App\Http\Controllers\Web;

use App\Enums\CourseRequestStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\TelegramAccessGrantStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $stats = Cache::remember('admin:dashboard:stats', 60, function () {
            return [
                'pending_requests' => CourseRequest::where('status', CourseRequestStatus::PENDING_REVIEW)->count(),
                'pending_proofs' => PaymentProof::where('status', PaymentProofStatus::PENDING)->count(),
                'approved_waiting' => $this->approvedWaitingCount(),
                'rejected' => CourseRequest::where('status', CourseRequestStatus::REJECTED)->count(),
            ];
        });

        $recentRequests = CourseRequest::with('course')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentRequests' => $recentRequests,
        ]);
    }

    /**
     * Optimized count for approved requests waiting for manual Telegram add.
     * Uses an indexed join instead of whereHas correlated subquery.
     */
    private function approvedWaitingCount(): int
    {
        return CourseRequest::where('status', CourseRequestStatus::APPROVED)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('telegram_access_grants')
                    ->whereColumn('telegram_access_grants.course_request_id', 'course_requests.id')
                    ->where('status', TelegramAccessGrantStatus::PENDING_MANUAL_ADD);
            })
            ->count();
    }
}
