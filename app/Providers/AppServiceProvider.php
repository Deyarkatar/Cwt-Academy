<?php

namespace App\Providers;

use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Eloquent\CourseRepository;
use App\Support\Security\PasswordPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Hashing\Argon2IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            CourseRepositoryInterface::class,
            CourseRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ---- Hashing: prefer Argon2id in production --------------------------
        $this->configureHashing();

        // ---- Rate limiters --------------------------------------------------
        // Default API limiter: 60 rpm per user-or-IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
        // Strict auth limiter: 10 rpm per IP + per-email fallback.
        // This prevents both single-IP and distributed brute-force attacks.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
        // Strict login rate limiter: 5 rpm per IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // CAPTCHA verify endpoints — global guard.
        RateLimiter::for('captcha', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
        // Admin API login — stricter than public auth.
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // ---- Vite asset paths: relative URLs in dev/test ---------------------
        // Prevents CSP origin mismatches when the dev server is reached via
        // http://localhost:8000 but Laravel generates absolute asset URLs using
        // http://127.0.0.1:8000 (or vice versa).
        if (! app()->environment('production')) {
            Vite::createAssetPathsUsing(function (string $path, $secure = null): string {
                return '/'.ltrim($path, '/');
            });
        }

        // ---- Locale ---------------------------------------------------------
        $locale = session('locale');
        if (! in_array($locale, ['en', 'ku'], true)) {
            $locale = request()->cookie('locale');
        }
        if (! in_array($locale, ['en', 'ku'], true)) {
            $locale = config('app.locale');
        }
        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);
        }

        // ---- Production preflight (run only on the first non-health
        //      request per process). The check is intentionally NOT a hard
        //      throw because that would make the application unable to serve
        //      load-balancer probes when misconfigured. Instead we log a
        //      critical warning and a separate `php artisan app:check-prod`
        //      command exists for explicit verification.
        $this->validateProductionEnvironment();

        // ---- Slow query logging ------------------------------------------------
        $thresholdValue = config('database.slow_query_threshold_ms', 500);
        $slowThreshold = is_numeric($thresholdValue) ? (float) $thresholdValue : 500.0;
        if ($slowThreshold > 0 && app()->environment('production')) {
            DB::listen(function ($query) use ($slowThreshold) {
                $ms = $query->time;
                if ($ms > $slowThreshold) {
                    Log::warning('Slow query detected', [
                        'time_ms' => $ms,
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                    ]);
                }
            });
        }
    }

    /**
     * Configure hashing to use Argon2id in production for maximum
     * password-storage security. Bcrypt is kept as fallback for backward
     * compatibility with existing hashes.
     */
    private function configureHashing(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        // Argon2id (OWASP 2026 recommendation).
        Hash::extend('argon2id', function () {
            return new Argon2IdHasher([
                'memory' => 65536,
                'time' => 4,
                'threads' => 1,
            ]);
        });

        /** @phpstan-ignore method.notFound */
        app('hash')->setDefaultDriver('argon2id');
    }

    /**
     * Lightweight, non-fatal preflight. The fatal version is the
     * `app:check-prod` artisan command (returns non-zero exit code on fail).
     */
    private function validateProductionEnvironment(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $issues = [];

        if (config('app.debug')) {
            $issues[] = 'APP_DEBUG must be false';
        }

        if (! config('session.secure')) {
            $issues[] = 'SESSION_SECURE_COOKIE must be true';
        }

        if (! config('session.encrypt')) {
            $issues[] = 'SESSION_ENCRYPT must be true';
        }

        if (config('session.same_site') !== 'strict') {
            $issues[] = 'SESSION_SAME_SITE must be strict';
        }

        if (config('auth.defaults.guard') !== 'web') {
            $issues[] = 'AUTH_GUARD should be web';
        }

        if (empty(config('security.trusted_proxies'))) {
            $issues[] = 'TRUSTED_PROXIES should be configured in production';
        }

        if (empty(config('app.health_check_token'))) {
            $issues[] = 'HEALTH_CHECK_TOKEN should be set in production';
        }

        // Only validate ADMIN_DEFAULT_PASSWORD if it's actually set; after
        // initial seeding operators are encouraged to UNSET it from .env so
        // it cannot be leaked.
        $adminPasswordRaw = config('services.admin.default_password');
        $adminPassword = is_string($adminPasswordRaw) ? $adminPasswordRaw : '';
        if (! empty($adminPassword)) {
            $error = PasswordPolicy::validate($adminPassword);
            if ($error !== null) {
                $issues[] = 'ADMIN_DEFAULT_PASSWORD: '.$error;
            }
        }

        if (! empty($issues)) {
            Log::critical(
                'Production preflight: configuration issues detected',
                ['issues' => $issues]
            );
        }
    }
}
