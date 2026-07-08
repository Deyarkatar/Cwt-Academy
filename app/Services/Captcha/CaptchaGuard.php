<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use Illuminate\Validation\ValidationException;

/**
 * Driver-agnostic CAPTCHA guard for public forms.
 *
 * Verifies whichever CAPTCHA provider is currently enabled:
 *   - Cloudflare Turnstile (CAPTCHA_DRIVER=turnstile)
 *   - Self-hosted math CAPTCHA (CAPTCHA_DRIVER=math)
 *
 * When no driver is enabled the request passes through, keeping the app
 * usable in local/test environments without external keys.
 */
class CaptchaGuard
{
    /**
     * @throws ValidationException
     */
    public function verify(?string $turnstileToken, ?string $mathAnswer): void
    {
        $this->verifyTurnstile($turnstileToken);
        $this->verifyMathCaptcha($mathAnswer);
    }

    /**
     * @throws ValidationException
     */
    private function verifyTurnstile(?string $token): void
    {
        $service = app(TurnstileService::class);

        if (! $service->enabled()) {
            return;
        }

        $token = is_string($token) ? $token : null;
        $result = $service->verify($token, request()->ip());

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => __('auth.captcha_invalid'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function verifyMathCaptcha(?string $answer): void
    {
        $service = app(MathCaptchaService::class);

        if (! $service->enabled()) {
            return;
        }

        $result = $service->verify($answer);

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'captcha_answer' => __('auth.captcha_invalid'),
            ]);
        }
    }
}
