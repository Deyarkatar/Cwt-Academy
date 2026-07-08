<?php

namespace App\Http\Controllers\Web;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendVerificationEmailJob;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Captcha\MathCaptchaService;
use App\Services\Captcha\RecaptchaService;
use App\Support\Security\PasswordPolicy;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthWebController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): View|RedirectResponse
    {
        $ipKey = 'web-login|ip|'.$request->ip();
        $emailKey = 'web-login|email|'.$request->string('email')->lower()->trim()->toString();

        if (RateLimiter::tooManyAttempts($ipKey, 5)) {
            AuditLogger::logLogin(null, true);
            throw ValidationException::withMessages([
                'email' => __('auth.too_many_attempts'),
            ]);
        }

        if (RateLimiter::tooManyAttempts($emailKey, 5)) {
            AuditLogger::logLogin(null, true);
            throw ValidationException::withMessages([
                'email' => __('auth.too_many_attempts'),
            ]);
        }

        $captcha = app(MathCaptchaService::class);
        $captchaResult = $captcha->verify($request->string('captcha_answer')->toString());
        if (! $captchaResult['success']) {
            $error = in_array('captcha-expired', $captchaResult['error_codes'], true)
                ? __('auth.captcha_expired')
                : __('auth.captcha_invalid');

            throw ValidationException::withMessages([
                'captcha_answer' => $error,
            ]);
        }

        $this->verifyRecaptcha($request);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($ipKey, decaySeconds: 60);
            RateLimiter::hit($emailKey, decaySeconds: 300);
            AuditLogger::logLogin(null, true);

            throw ValidationException::withMessages([
                'email' => __('auth.invalid_credentials_or_unavailable'),
            ]);
        }

        RateLimiter::clear($ipKey);
        RateLimiter::clear($emailKey);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive() || ($user->isAdmin() && ! $user->hasVerifiedEmail())) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __('auth.invalid_credentials_or_unavailable'),
            ]);
        }

        return redirect()->intended($user->isAdmin() ? '/admin' : '/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $userId = $user?->id;

        $user?->currentAccessToken()?->delete();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId !== null) {
            AuditLogger::log(
                AuditAction::LOGOUT,
                'User',
                $userId,
                null,
                null,
                $userId,
            );
        }

        return redirect('/');
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $captcha = app(MathCaptchaService::class);
        $captchaResult = $captcha->verify($request->string('captcha_answer')->toString());
        if (! $captchaResult['success']) {
            $error = in_array('captcha-expired', $captchaResult['error_codes'], true)
                ? __('auth.captcha_expired')
                : __('auth.captcha_invalid');

            throw ValidationException::withMessages([
                'captcha_answer' => $error,
            ]);
        }

        $this->verifyRecaptcha($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,strict', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordPolicy::rule()],
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->role = UserRole::STUDENT->value;
        $user->status = UserStatus::ACTIVE->value;
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        SendVerificationEmailJob::dispatch($user->id)->afterResponse();

        return redirect()->route('verification.notice');
    }

    public function verificationNotice(): View
    {
        return view('auth.verify-email');
    }

    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect('/dashboard');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    public function forgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    public function forgotPassword(Request $request): RedirectResponse
    {
        $captcha = app(MathCaptchaService::class);
        $captchaResult = $captcha->verify($request->string('captcha_answer')->toString());
        if (! $captchaResult['success']) {
            $error = in_array('captcha-expired', $captchaResult['error_codes'], true)
                ? __('auth.captcha_expired')
                : __('auth.captcha_invalid');

            throw ValidationException::withMessages([
                'captcha_answer' => $error,
            ]);
        }

        $this->verifyRecaptcha($request);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($validated);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => __($status),
        ]);
    }

    public function resetPasswordForm(string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => request('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordPolicy::rule()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                AuditLogger::log(
                    AuditAction::PASSWORD_RESET,
                    'User',
                    $user->id,
                    null,
                    null,
                    $user->id,
                );
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => is_string($status) ? __($status) : '',
        ]);
    }

    /**
     * Verify the Google reCAPTCHA v3 token server-side.
     */
    private function verifyRecaptcha(Request $request): void
    {
        app(RecaptchaService::class)->verify($request);
    }
}
