<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HoneyTokenGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.honey_tokens', [
            'fake_aws_access_key' => 'AKIAIOSFODNN7EXAMPLE',
            'fake_aws_secret_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            'fake_db_password' => 'honey-password-example-12345',
        ]);
    }

    public function test_request_without_honey_token_is_allowed(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_request_with_exact_honey_token_is_blocked(): void
    {
        $response = $this->get('/', [
            'X-Custom-Header' => 'AKIAIOSFODNN7EXAMPLE',
        ]);

        $response->assertForbidden();
    }

    public function test_request_with_honey_token_substring_is_blocked(): void
    {
        $response = $this->postJson('/', [
            'note' => 'leaked wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY in body',
        ]);

        $response->assertForbidden();
    }

    public function test_large_body_does_not_cause_cpu_exhaustion(): void
    {
        $largeBody = str_repeat('A', 1_000_000);

        $start = microtime(true);
        $response = $this->post('/register', ['payload' => $largeBody]);
        $elapsedMs = (microtime(true) - $start) * 1000;

        // We only care that the guard finishes quickly; the route may reject
        // the malformed registration for any reason.
        $this->assertLessThan(1000, $elapsedMs, 'HoneyTokenGuard took too long on a large body.');
    }
}
