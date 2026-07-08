<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_response_includes_universal_security_headers(): void
    {
        $response = $this->get('/');

        // Universal: sent in ALL environments.
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-XSS-Protection', '0');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_non_production_does_not_send_hsts(): void
    {
        $response = $this->get('/');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_non_production_does_not_send_permissions_policy(): void
    {
        $response = $this->get('/');
        $response->assertHeaderMissing('Permissions-Policy');
    }

    public function test_production_enforces_cache_control_on_sensitive_routes(): void
    {
        // Mock production environment so cache-control is applied.
        app()->detectEnvironment(function () {
            return 'production';
        });

        $response = $this->get('/admin');

        $response->assertHeader('Cache-Control');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertTrue(
            str_contains($cacheControl, 'no-store') || str_contains($cacheControl, 'no-cache'),
            "Expected Cache-Control to contain no-store or no-cache, got: {$cacheControl}"
        );
    }
}
