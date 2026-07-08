<?php

namespace App\Services\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile server-side verification (2026).
 *
 * Stateless, replay-safe via Laravel's rate-limiter on the verify endpoint.
 * Disabled when CAPTCHA_DRIVER is not 'turnstile'.
 */
class TurnstileService
{
    public function enabled(): bool
    {
        return config('security.captcha.driver') === 'turnstile'
            || (config('security.captcha.enforce_in_testing') && app()->runningUnitTests());
    }

    private string $circuitKey = 'turnstile:circuit';

    private function isCircuitOpen(): bool
    {
        $failures = Cache::get($this->circuitKey, 0);
        $threshold = config('security.captcha.turnstile.circuit_threshold', 5);

        return $failures >= (is_numeric($threshold) ? (int) $threshold : 5);
    }

    private function recordFailure(): void
    {
        Cache::increment($this->circuitKey);
        Cache::put($this->circuitKey, Cache::get($this->circuitKey, 1), now()->addMinutes(1));
    }

    private function recordSuccess(): void
    {
        Cache::forget($this->circuitKey);
    }

    /**
     * Verify a Turnstile response token.
     *
     * @param  string|null  $token  The client-side token (cf-turnstile-response).
     * @param  string|null  $remoteIp  Optional user IP for binding.
     * @return array{success: bool, error_codes: string[], score: float|null}
     */
    public function verify(?string $token, ?string $remoteIp = null): array
    {
        if (! $this->enabled()) {
            return ['success' => true, 'error_codes' => [], 'score' => null];
        }

        if (empty($token)) {
            return ['success' => false, 'error_codes' => ['missing-input-response'], 'score' => null];
        }

        if ($this->isCircuitOpen()) {
            Log::warning('Turnstile circuit breaker open; failing fast.');

            return ['success' => false, 'error_codes' => ['turnstile-circuit-open'], 'score' => null];
        }

        $secret = config('security.captcha.turnstile.secret_key');
        if (empty($secret)) {
            $message = app()->environment('production')
                ? 'Turnstile secret key not configured in production.'
                : 'Turnstile secret key not configured; treating as pass-through.';
            Log::error($message);

            if (app()->environment('production')) {
                return ['success' => false, 'error_codes' => ['turnstile-not-configured'], 'score' => null];
            }

            return ['success' => true, 'error_codes' => [], 'score' => null];
        }

        try {
            $retryTimes = config('security.captcha.turnstile.retry_times') ?? 1;
            $retrySleep = config('security.captcha.turnstile.retry_sleep_ms') ?? 200;
            $timeout = config('security.captcha.turnstile.timeout') ?? 5;
            $verifyUrl = config('security.captcha.turnstile.verify_url');

            $response = Http::retry(
                times: is_numeric($retryTimes) ? (int) $retryTimes : 1,
                sleepMilliseconds: is_numeric($retrySleep) ? (int) $retrySleep : 200,
            )->timeout(is_numeric($timeout) ? (int) $timeout : 5)
                ->post(is_string($verifyUrl) ? $verifyUrl : 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]);

            $data = $response->json();
            $this->recordSuccess();
        } catch (ConnectionException $e) {
            $this->recordFailure();
            Log::warning('Turnstile verification connection failed', ['error' => $e->getMessage()]);
            // Production: fail-closed (secure). Non-production: fail-open (dev convenience).
            $failOpen = ! app()->environment('production')
                || config('security.captcha.fail_open_on_network_error', false);

            if ($failOpen) {
                return ['success' => true, 'error_codes' => ['timeout-or-duplicate'], 'score' => null];
            }

            return ['success' => false, 'error_codes' => ['turnstile-network-error'], 'score' => null];
        }

        if (! is_array($data)) {
            return ['success' => false, 'error_codes' => ['bad-request'], 'score' => null];
        }

        $success = filter_var($data['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $errorsRaw = $data['error-codes'] ?? [];
        $errors = is_array($errorsRaw) ? array_values(array_filter($errorsRaw, 'is_string')) : [];
        $scoreRaw = $data['score'] ?? null;
        $score = is_float($scoreRaw) || is_int($scoreRaw) ? (float) $scoreRaw : null;

        // Anti-replay: if Cloudflare says the token was already used, block it.
        if (in_array('timeout-or-duplicate', $errors, true)) {
            $success = false;
        }

        return [
            'success' => $success,
            'error_codes' => $errors,
            'score' => $score,
        ];
    }
}
