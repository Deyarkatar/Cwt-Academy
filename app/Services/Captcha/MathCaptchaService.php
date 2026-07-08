<?php

namespace App\Services\Captcha;

use Illuminate\Session\Store;
use Illuminate\Support\Str;

/**
 * Self-hosted math CAPTCHA (no external API or GD extension required).
 *
 * Generates a simple arithmetic question, stores the hashed answer in the
 * session, and verifies user input on form submission.
 */
class MathCaptchaService
{
    public function enabled(): bool
    {
        return config('security.captcha.driver') === 'math'
            || (config('security.captcha.enforce_in_testing') && app()->runningUnitTests());
    }

    /**
     * Generate a new math question and store its answer in the session.
     *
     * @return array{question: string, input_name: string}
     */
    public function generate(): array
    {
        $a = random_int(2, 15);
        $b = random_int(2, 15);
        $operator = random_int(0, 1) === 0 ? '+' : '-';

        if ($operator === '-' && $a < $b) {
            [$a, $b] = [$b, $a];
        }

        $answer = $operator === '+' ? $a + $b : $a - $b;
        $token = Str::random(32);

        /** @var Store $session */
        $session = session();
        $session->put('captcha.math_token', $token);
        $session->put('captcha.math_answer', $this->hash($token, (string) $answer));
        $session->put('captcha.math_generated_at', now()->timestamp);

        $question = match ($operator) {
            '+' => "{$a} + {$b}",
            '-' => "{$a} - {$b}",
        };

        return [
            'question' => $question,
            'input_name' => 'captcha_answer',
        ];
    }

    /**
     * Verify the user-submitted CAPTCHA answer.
     *
     * @param  string|null  $answer  The raw user input.
     * @return array{success: bool, error_codes: string[]}
     */
    public function verify(?string $answer): array
    {
        if (! $this->enabled()) {
            return ['success' => true, 'error_codes' => []];
        }

        /** @var Store $session */
        $session = session();
        $tokenRaw = $session->get('captcha.math_token');
        $token = is_string($tokenRaw) ? $tokenRaw : '';
        $expectedRaw = $session->get('captcha.math_answer');
        $expected = is_string($expectedRaw) ? $expectedRaw : '';
        $generatedAt = $session->get('captcha.math_generated_at');
        $generatedAt = is_int($generatedAt) ? $generatedAt : 0;

        if (empty($token) || empty($expected)) {
            return ['success' => false, 'error_codes' => ['captcha-expired']];
        }

        // Expire after 5 minutes
        if ($generatedAt > 0 && (now()->getTimestamp() - $generatedAt) > 300) {
            $this->clear();

            return ['success' => false, 'error_codes' => ['captcha-expired']];
        }

        if (empty($answer) || ! is_numeric($answer)) {
            return ['success' => false, 'error_codes' => ['captcha-invalid']];
        }

        if (! hash_equals($expected, $this->hash($token, $answer))) {
            return ['success' => false, 'error_codes' => ['captcha-invalid']];
        }

        $this->clear();

        return ['success' => true, 'error_codes' => []];
    }

    /**
     * Clear the CAPTCHA session data.
     */
    public function clear(): void
    {
        /** @var Store $session */
        $session = session();
        $session->forget([
            'captcha.math_token',
            'captcha.math_answer',
            'captcha.math_generated_at',
        ]);
    }

    private function hash(string $token, string $answer): string
    {
        return hash_hmac('sha256', $answer, $token);
    }
}
