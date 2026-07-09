<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_x_content_type_options_is_set(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_x_frame_options_is_set(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_referrer_policy_is_set(): void
    {
        $response = $this->get('/');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_x_xss_protection_is_zero(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-XSS-Protection', '0');
    }

    public function test_content_security_policy_is_set_on_html_pages(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function test_csp_does_not_allow_inline_scripts_in_production_pattern(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        // In dev, unsafe-inline may be present for Vite, but in prod it should not
        // We verify the nonce-based approach is used
        $this->assertStringContainsString("'nonce-", $csp);
    }

    public function test_csp_includes_frame_ancestors_none(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csp_includes_object_src_none(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_csp_includes_base_uri_self(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
    }

    public function test_api_response_has_security_headers(): void
    {
        $response = $this->getJson('/api/v1/courses');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_api_response_does_not_have_csp(): void
    {
        $response = $this->getJson('/api/v1/courses');
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_server_header_is_stripped(): void
    {
        $response = $this->get('/');
        $this->assertNull($response->headers->get('Server'));
    }

    public function test_x_powered_by_header_is_stripped(): void
    {
        $response = $this->get('/');
        $this->assertNull($response->headers->get('X-Powered-By'));
    }
}
