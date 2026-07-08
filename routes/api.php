<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CourseRequestController;
use App\Http\Controllers\Admin\InstructorController;
use App\Http\Controllers\Admin\PaymentProofController;
use App\Http\Controllers\Admin\TelegramAccessGrantController;
use App\Http\Controllers\Admin\TelegramChannelController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\CourseRequestController as PublicCourseRequestController;
use App\Http\Controllers\Api\PublicCourseController;
use App\Http\Controllers\Api\RequestTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::get('/courses', [PublicCourseController::class, 'index'])->middleware('throttle:30,1');
    Route::get('/courses/{slug}', [PublicCourseController::class, 'show'])->middleware('throttle:30,1');

    Route::post('/course-requests', [PublicCourseRequestController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/course-requests/{tracking_code}', [RequestTrackingController::class, 'show'])->middleware('throttle:10,1');
    Route::post('/course-requests/{tracking_code}/payment-proof', [RequestTrackingController::class, 'storePaymentProof'])->middleware('throttle:3,1');
});

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:admin-login');

    Route::middleware(['auth:sanctum', 'admin', 'throttle:30,1'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        Route::apiResource('courses', CourseController::class);
        Route::post('/courses/{id}/archive', [CourseController::class, 'destroy']);

        Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'update']);

        Route::apiResource('instructors', InstructorController::class)->only(['index', 'store', 'update']);
        Route::post('/instructors/{id}/approve', [InstructorController::class, 'approve']);
        Route::post('/instructors/{id}/reject', [InstructorController::class, 'reject']);

        Route::get('/course-requests', [CourseRequestController::class, 'index']);
        Route::get('/course-requests/{id}', [CourseRequestController::class, 'show']);
        Route::post('/course-requests/{id}/approve', [CourseRequestController::class, 'approve']);
        Route::post('/course-requests/{id}/reject', [CourseRequestController::class, 'reject']);

        Route::get('/payment-proofs', [PaymentProofController::class, 'index']);
        Route::get('/payment-proofs/{id}', [PaymentProofController::class, 'show']);
        Route::get('/payment-proofs/{id}/download', [PaymentProofController::class, 'download']);
        Route::post('/payment-proofs/{id}/approve', [PaymentProofController::class, 'approve']);
        Route::post('/payment-proofs/{id}/reject', [PaymentProofController::class, 'reject']);

        Route::get('/telegram-channels', [TelegramChannelController::class, 'index']);
        Route::post('/telegram-channels', [TelegramChannelController::class, 'store']);
        Route::get('/telegram-channels/{id}', [TelegramChannelController::class, 'show']);
        Route::put('/telegram-channels/{id}', [TelegramChannelController::class, 'update']);
        Route::post('/telegram-channels/{id}/deactivate', [TelegramChannelController::class, 'deactivate']);

        Route::get('/telegram-access-grants', [TelegramAccessGrantController::class, 'index']);
        Route::get('/telegram-access-grants/{id}', [TelegramAccessGrantController::class, 'show']);
        Route::post('/telegram-access-grants/{id}/mark-added', [TelegramAccessGrantController::class, 'markAdded']);
        Route::post('/telegram-access-grants/{id}/mark-revoked', [TelegramAccessGrantController::class, 'markRevoked']);

        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });
});
