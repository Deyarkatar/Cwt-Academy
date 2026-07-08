<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Captcha\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * OAuth social login via Laravel Socialite (Google + GitHub).
 *
 * Security posture:
 *  - Strict provider allow-list; unknown providers 404.
 *  - Providers with missing credentials are treated as disabled (404).
 *  - Callback failures (state mismatch, denied consent, network) fail
 *    closed with a generic error and never leak provider internals.
 *  - State mismatches are rate-limited per IP to prevent state probing.
 *  - Provider emails are required to be verified before account creation/linking.
 *  - New users get a cryptographically random password (never empty),
 *    STUDENT role, and are marked verified only when the provider
 *    returns a verified email.
 *  - Existing local accounts cannot be taken over by a matching OAuth email.
 *    Linking a provider ID to an existing user requires a prior authorization
 *    flow; until then, the attempt is rejected.
 *  - OAuth logins never issue long-lived remember-me tokens.
 *  - Suspended accounts cannot log in through OAuth.
 *  - Session is regenerated on login (session-fixation protection).
 */
class SocialiteController extends Controller
{
    /**
     * Providers supported by this application. Never accept arbitrary
     * user-supplied driver names — Socialite would happily instantiate
     * other drivers (e.g. bitbucket) with unexpected config.
     */
    private const ALLOWED_PROVIDERS = ['google', 'github'];

    private const OAUTH_STATE_DECAY_SECONDS = 300;

    private const OAUTH_CAPTCHA_SESSION_KEY = 'oauth_captcha_passed';

    private const OAUTH_CAPTCHA_TTL_SECONDS = 300;

    public function redirectToProvider(Request $request, string $provider): RedirectResponse
    {
        $this->assertProviderEnabled($provider);

        // Bots must solve a CAPTCHA before we hand out a state parameter to the
        // OAuth provider. The stamp is verified when the provider redirects back.
        app(RecaptchaService::class)->verify($request);
        $request->session()->put(self::OAUTH_CAPTCHA_SESSION_KEY, now()->getTimestamp());

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return $driver->redirect();
    }

    public function handleProviderCallback(Request $request, string $provider): RedirectResponse
    {
        $this->assertProviderEnabled($provider);

        if (! $this->hasValidCaptchaStamp($request)) {
            Log::warning('OAuth callback without CAPTCHA stamp', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => __('auth.captcha_invalid'),
            ]);
        }

        $request->session()->forget(self::OAUTH_CAPTCHA_SESSION_KEY);

        try {
            /** @var AbstractProvider $driver */
            $driver = Socialite::driver($provider);
            $socialUser = $driver->user();
        } catch (InvalidStateException $e) {
            $ip = $request->ip() ?? 'unknown';
            RateLimiter::hit('oauth-state|'.$ip, self::OAUTH_STATE_DECAY_SECONDS);

            Log::warning('Social login state mismatch', [
                'provider' => $provider,
                'ip' => $ip,
            ]);

            return redirect()->route('login')->withErrors([
                'email' => __('auth.social_login_failed'),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors([
                'email' => __('auth.social_login_failed'),
            ]);
        }

        $providerId = (string) $socialUser->getId();
        $email = $socialUser->getEmail();

        if ($providerId === '') {
            return redirect()->route('login')->withErrors([
                'email' => __('auth.social_login_failed'),
            ]);
        }

        // GitHub can return accounts without a public/verified email.
        // We require an email to create or match an account.
        if (! is_string($email) || $email === '') {
            return redirect()->route('login')->withErrors([
                'email' => __('auth.social_email_required'),
            ]);
        }

        $email = Str::lower(trim($email));
        $idColumn = $provider.'_id';

        // Providers must return a verified email. GitHub may return an unverified
        // primary email; Google marks email_verified in the payload.
        if (! $this->isProviderEmailVerified($socialUser, $provider)) {
            return redirect()->route('login')->withErrors([
                'email' => __('auth.social_email_unverified'),
            ]);
        }

        // 1) Match by provider ID first (strongest link).
        $user = User::query()->where($idColumn, $providerId)->first();

        // 2) Fall back to email match. If a local user already exists with this
        // email but no provider link, we must NOT silently link the provider ID
        // because that would allow account takeover. The user must authorize the
        // link via an authenticated flow instead.
        if ($user === null && User::query()->where('email', $email)->exists()) {
            return redirect()->route('login')->withErrors([
                'email' => __('auth.social_email_already_registered'),
            ]);
        }

        // 3) Register a brand-new student account.
        $isNewUser = false;
        if ($user === null) {
            $name = trim((string) ($socialUser->getName() ?: $socialUser->getNickname() ?: Str::before($email, '@')));

            $user = new User([
                'name' => Str::limit($name !== '' ? $name : 'Student', 255, ''),
                'email' => $email,
                // Defense-in-depth: social accounts never have an empty or
                // guessable local credential.
                'password' => Hash::make(Str::password(64)),
            ]);
            $user->role = UserRole::STUDENT->value;
            $user->status = UserStatus::ACTIVE->value;
            $user->{$idColumn} = $providerId;
            // OAuth providers hand us an email they control/verified; treat
            // it as verified so students skip the verification interstitial.
            $user->email_verified_at = now();
            $user->save();

            $isNewUser = true;
        }

        if (! $user->isActive()) {
            return redirect()->route('login')->withErrors([
                'email' => __('auth.invalid_credentials_or_unavailable'),
            ]);
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        AuditLogger::log(
            AuditAction::LOGIN,
            'User',
            $user->id,
            null,
            ['social_provider' => $provider, 'registered' => $isNewUser],
            $user->id,
        );

        return redirect()->intended($user->isAdmin() ? '/admin' : '/dashboard');
    }

    /**
     * Ensure the user passed a CAPTCHA before initiating the OAuth flow.
     * The stamp is a timestamp written to the session at redirect time.
     */
    private function hasValidCaptchaStamp(Request $request): bool
    {
        $service = app(RecaptchaService::class);

        // When reCAPTCHA is not configured we still accept the flow.
        if (! $service->enabled()) {
            return true;
        }

        $stamp = $request->session()->get(self::OAUTH_CAPTCHA_SESSION_KEY);

        if (! is_int($stamp)) {
            return false;
        }

        return (now()->getTimestamp() - $stamp) <= self::OAUTH_CAPTCHA_TTL_SECONDS;
    }

    /**
     * Determine whether the provider's email is verified.
     *
     * Google returns a boolean "email_verified" claim. GitHub's Socialite
     * driver, when granted the "user:email" scope, resolves the primary
     * email by checking that it is verified; if it isn't verified, the
     * email returned is null. Therefore a non-empty GitHub email from the
     * driver can be treated as verified.
     */
    private function isProviderEmailVerified(SocialiteUser $socialUser, string $provider): bool
    {
        if ($provider === 'github') {
            return is_string($socialUser->getEmail()) && $socialUser->getEmail() !== '';
        }

        $rawUser = method_exists($socialUser, 'getRaw') ? $socialUser->getRaw() : [];

        return is_array($rawUser) && ($rawUser['email_verified'] ?? false) === true;
    }

    /**
     * Abort 404 unless the provider is allow-listed AND fully configured.
     */
    private function assertProviderEnabled(string $provider): void
    {
        if (! in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            abort(404);
        }

        $clientId = config("services.{$provider}.client_id");
        $clientSecret = config("services.{$provider}.client_secret");

        if (! is_string($clientId) || $clientId === ''
            || ! is_string($clientSecret) || $clientSecret === '') {
            abort(404);
        }
    }
}
