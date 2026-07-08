<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Security\TrustedProxyValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests for Fix 4: Strict trusted proxy validation.
 */
class TrustedProxyValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_requires_trusted_proxies(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TRUSTED_PROXIES must be configured in production');

        (new TrustedProxyValidator)->validate('production', []);
    }

    public function test_wildcard_proxies_rejected_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TRUSTED_PROXIES contains wildcard entries in production');

        (new TrustedProxyValidator)->validate('production', ['173.245.48.0/20', '*']);
    }

    public function test_empty_proxies_allowed_in_non_production(): void
    {
        $this->assertNotTrue(App::environment('production'));

        (new TrustedProxyValidator)->validate('local', []);
    }
}
