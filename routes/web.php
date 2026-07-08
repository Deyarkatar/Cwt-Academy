<?php

use App\Http\Controllers\Admin\Web\CourseRequestActionController as WebCourseRequestActionController;
use App\Http\Controllers\Admin\Web\TelegramAccessActionController as WebTelegramAccessActionController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminPaymentProofDownloadController;
use App\Http\Controllers\Web\AdminRequestController;
use App\Http\Controllers\Web\AdminTelegramAccessController;
use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\CourseDetailController;
use App\Http\Controllers\Web\CourseRequestController;
use App\Http\Controllers\Web\CourseRequestFormController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\PaymentProofController;
use App\Http\Controllers\Web\PendingVerificationController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\TrackingController;
use App\Http\Controllers\WebAuthn\WebAuthnAssetController;
use App\Http\Controllers\WebAuthn\WebAuthnLoginController;
use App\Http\Controllers\WebAuthn\WebAuthnPasskeyController;
use App\Http\Controllers\WebAuthn\WebAuthnRegisterController;
use Illuminate\Support\Facades\Route;

/* --------------------------------------------------------------------------
 * Public Routes
 * -------------------------------------------------------------------------- */

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])
    ->where('locale', 'en|ku')
    ->name('locale.switch');

Route::get('/', [HomeController::class, 'index']);
Route::get('/courses', [CatalogController::class, 'index']);
Route::get('/courses/{slug}', [CourseDetailController::class, 'show'])->where('slug', '[a-z0-9-]+');
Route::get('/courses/{slug}/request', [CourseRequestFormController::class, 'show'])->where('slug', '[a-z0-9-]+');

Route::get('/track', [TrackingController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('track');

Route::post('/course-requests/store', [CourseRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('course-requests.store');
Route::get('/request-success/{code}', [CourseRequestController::class, 'success'])
    ->name('request.success');
Route::post('/request-success/{code}/payment-proof', [PaymentProofController::class, 'store'])
    ->middleware('throttle:3,1')
    ->name('payment-proof.store');

Route::get('/contact', [ContactController::class, 'index'])->name('contact');
// Route::get('/health', [HealthCheckController::class, '__invoke'])->name('health');

/* --------------------------------------------------------------------------
 * Auth Routes (web)
 * -------------------------------------------------------------------------- */

Route::get('/login', [AuthWebController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthWebController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
Route::get('/register', [AuthWebController::class, 'registerForm'])->name('register');
Route::post('/register', [AuthWebController::class, 'register'])->middleware('throttle:login');

Route::post('auth/{provider}/redirect', [SocialiteController::class, 'redirectToProvider'])
    ->where('provider', 'google|github')
    ->middleware('throttle:10,1')
    ->name('social.redirect');
Route::get('auth/{provider}/callback', [SocialiteController::class, 'handleProviderCallback'])
    ->where('provider', 'google|github')
    ->middleware('throttle:10,1')
    ->name('social.callback');

/* --------------------------------------------------------------------------
 * WebAuthn / Passkey Routes
 * -------------------------------------------------------------------------- */

Route::get('/webauthn-helper', WebAuthnAssetController::class)
    ->name('webauthn.js');

// Login (guest-only).
Route::post('/webauthn/login/options', [WebAuthnLoginController::class, 'options'])
    ->middleware(['throttle:login'])
    ->name('webauthn.login.options');
Route::post('/webauthn/login', [WebAuthnLoginController::class, 'login'])
    ->middleware(['throttle:login'])
    ->name('webauthn.login');

// Registration (authenticated).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/webauthn/register/options', [WebAuthnRegisterController::class, 'options'])
        ->name('webauthn.register.options');
    Route::post('/webauthn/register', [WebAuthnRegisterController::class, 'register'])
        ->name('webauthn.register');
    Route::get('/webauthn/passkeys', [WebAuthnPasskeyController::class, 'index'])
        ->name('webauthn.passkeys.index');
    Route::delete('/webauthn/passkeys/{id}', [WebAuthnPasskeyController::class, 'destroy'])
        ->name('webauthn.passkeys.destroy');
});

Route::get('/forgot-password', [AuthWebController::class, 'forgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [AuthWebController::class, 'forgotPassword'])->middleware('throttle:3,1')->name('password.email');
Route::get('/reset-password/{token}', [AuthWebController::class, 'resetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [AuthWebController::class, 'resetPassword'])
    ->middleware('throttle:3,1')
    ->name('password.update');

Route::middleware(['auth'])->group(function () {
    Route::get('/email/verify', [AuthWebController::class, 'verificationNotice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthWebController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [AuthWebController::class, 'resendVerification'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

/* --------------------------------------------------------------------------
 * Authenticated User Routes
 * -------------------------------------------------------------------------- */

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
});

Route::middleware(['auth'])->get('/pending-verification', [PendingVerificationController::class, 'index'])
    ->name('pending.verification');

/* --------------------------------------------------------------------------
 * Admin Routes
 * -------------------------------------------------------------------------- */

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index']);
    Route::get('/requests', [AdminRequestController::class, 'index']);
    Route::get('/telegram-access', [AdminTelegramAccessController::class, 'index']);

    Route::post('/course-requests/{id}/approve', [WebCourseRequestActionController::class, 'approve'])
        ->name('admin.requests.approve');
    Route::post('/course-requests/{id}/reject', [WebCourseRequestActionController::class, 'reject'])
        ->name('admin.requests.reject');
    Route::post('/telegram-access-grants/{id}/mark-added', [WebTelegramAccessActionController::class, 'markAdded'])
        ->name('admin.telegram.mark_added');
    Route::post('/telegram-access-grants/{id}/revoke', [WebTelegramAccessActionController::class, 'revoke'])
        ->name('admin.telegram.revoke');

    Route::get('/payment-proofs/{id}/download', [AdminPaymentProofDownloadController::class, 'download'])
        ->name('admin.payment-proofs.download');
});
