<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use App\Models\TelegramChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Cache::remember('api:admin:dashboard:stats', 30, function () {
            return [
                'courses_count' => Course::count(),
                'active_courses_count' => Course::where('status', 'ACTIVE')->count(),
                'pending_requests_count' => CourseRequest::where('status', 'PENDING_REVIEW')->count(),
                'approved_requests_count' => CourseRequest::where('status', 'APPROVED')->count(),
                'pending_proofs_count' => PaymentProof::where('status', 'PENDING')->count(),
                'telegram_channels_count' => TelegramChannel::where('is_active', true)->count(),
            ];
        });

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }
}
