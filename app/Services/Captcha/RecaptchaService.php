<?php

namespace App\Services\Captcha;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Google reCAPTCHA v3 server-side verification.
 *
 * No-op when RECAPTCHA_SECRET_KEY is not configured (local/testing).
 * When configured, fails closed: the request is rejected if the token
 * is missing, verification fails, or the score is below the threshold.
 */
class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Determine whether reCAPTCHA is configured and should be enforced.
     */
    public function enabled(): bool
    {
        $secretKey = config('services.recaptcha.secret_key');

        return is_string($secretKey) && $secretKey !== '';
    }

    /**
     * Verify the reCAPTCHA v3 token from the request.
     *
     * @throws ValidationException
     */
    public function verify(Request $request): void
    {
        if (! $this->enabled()) {
            return;
        }

        $token = $request->string('g-recaptcha-response')->toString();

        if ($token === '') {
            throw ValidationException::withMessages([
                'g-recaptcha-response' => __('auth.captcha_invalid'),
            ]);
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->retry(2, 200, throw: false)
                ->post(self::VERIFY_URL, [
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA verification request failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'g-recaptcha-response' => __('auth.captcha_invalid'),
            ]);
        }

        $result = $response->json();
        $result = is_array($result) ? $result : [];

        $success = ($result['success'] ?? false) === true;
        $score = is_numeric($result['score'] ?? null) ? (float) $result['score'] : 0.0;
        $minScoreRaw = config('services.recaptcha.min_score', 0.5);
        $minScore = is_numeric($minScoreRaw) ? (float) $minScoreRaw : 0.5;

        if (! $success || $score < $minScore) {
            Log::warning('reCAPTCHA verification rejected', [
                'success' => $success,
                'score' => $score,
                'error_codes' => $result['error-codes'] ?? [],
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'g-recaptcha-response' => __('auth.captcha_invalid'),
            ]);
        }
    }
}
