<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\AccountLockoutService;
use App\Services\Captcha\TurnstileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $ip = $request->ip() ?? 'unknown';
        $email = $request->string('email')->lower()->trim()->toString();
        $ipKey = 'login|ip|'.$ip;
        $emailKey = 'login|email|'.$email;

        $lockout = app(AccountLockoutService::class);

        if ($lockout->isLocked($ip, $email)) {
            AuditLogger::logLogin(null, true);

            return response()->json([
                'ok' => false,
                'message' => 'Account temporarily locked due to too many failed attempts. Please try again later.',
            ], 423);
        }

        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            AuditLogger::logLogin(null, true);

            return response()->json([
                'ok' => false,
                'message' => 'Too many login attempts. Please try again later.',
            ], 429);
        }

        if (RateLimiter::tooManyAttempts($emailKey, 5)) {
            AuditLogger::logLogin(null, true);

            return response()->json([
                'ok' => false,
                'message' => 'Too many login attempts for this account. Please try again later.',
            ], 429);
        }

        $turnstile = app(TurnstileService::class);
        $token = $request->input('cf-turnstile-response') ?? $request->input('captcha_token');
        $token = is_string($token) ? $token : null;
        $turnstileResult = $turnstile->verify($token, $ip);

        if (! $turnstileResult['success']) {
            $error = in_array('timeout-or-duplicate', $turnstileResult['error_codes'], true)
                ? 'CAPTCHA expired. Please refresh and try again.'
                : 'CAPTCHA verification failed. Please try again.';

            return response()->json([
                'ok' => false,
                'message' => $error,
            ], 422);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($ipKey, decaySeconds: 60);
            RateLimiter::hit($emailKey, decaySeconds: 900);
            $lockout->recordFailure($ip, $email);
            AuditLogger::logLogin($user?->id, true);

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials or account unavailable.'],
            ]);
        }

        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            AuditLogger::logLogin($user->id, true);

            return response()->json([
                'ok' => false,
                'message' => 'Invalid credentials or account unavailable.',
            ], 403);
        }

        if (! $user->isAdmin()) {
            AuditLogger::logLogin($user->id, true);

            return response()->json([
                'ok' => false,
                'message' => 'Invalid credentials or account unavailable.',
            ], 403);
        }

        $user->last_login_at = now();
        $user->save();

        $tokenExpiresHours = is_numeric(config('sanctum.expiration')) ? (int) config('sanctum.expiration') : 480;
        $token = $user->createToken('admin', ['admin'], expiresAt: now()->addMinutes($tokenExpiresHours))->plainTextToken;

        RateLimiter::clear($ipKey);
        RateLimiter::clear($emailKey);
        $lockout->recordSuccess($ip, $email);
        AuditLogger::logLogin($user->id);

        return response()->json([
            'ok' => true,
            'data' => [
                'token' => $token,
                'expires_in' => $tokenExpiresHours * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userId = $user->id;

        // Revoke the current token only (principle of least surprise)
        $user->currentAccessToken()->delete();

        AuditLogger::log(
            AuditAction::LOGOUT,
            'User',
            $userId,
            null,
            null,
            $userId,
        );

        return response()->json([
            'ok' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'status' => $user->status->value,
            ],
        ]);
    }
}
