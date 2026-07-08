<?php

namespace App\Http\Controllers\WebAuthn;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Captcha\RecaptchaService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

use function response;

class WebAuthnLoginController
{
    /**
     * Returns the challenge to assertion.
     */
    public function options(AssertionRequest $request): Responsable
    {
        app(RecaptchaService::class)->verify($request);

        return $request->toVerify($request->validate(['email' => 'sometimes|email|string']));
    }

    /**
     * Log the user in and return the post-login redirect URL.
     */
    public function login(AssertedRequest $request): JsonResponse
    {
        $loggedIn = $request->login();

        if (! $loggedIn) {
            return response()->json(['message' => __('auth.passkey_error')], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive() || ($user->isAdmin() && ! $user->hasVerifiedEmail())) {
            Auth::logout();
            $request->session()->invalidate();

            return response()->json(['message' => __('auth.invalid_credentials_or_unavailable')], 403);
        }

        $request->session()->regenerate();

        AuditLogger::log(
            AuditAction::LOGIN,
            'User',
            $user->id,
            null,
            ['method' => 'passkey'],
            $user->id,
        );

        return response()->json([
            'redirect' => $user->isAdmin() ? '/admin' : '/dashboard',
        ]);
    }
}
