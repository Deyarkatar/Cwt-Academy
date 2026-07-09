<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfCorsSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_cookie_is_httponly(): void
    {
        $response = $this->get('/');
        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === config('session.cookie')) {
                $this->assertTrue($cookie->isHttpOnly(), 'Session cookie must be HttpOnly');
            }
        }
    }

    public function test_session_cookie_has_same_site(): void
    {
        $response = $this->withSession(['test' => true])->get('/');
        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === config('session.cookie')) {
                $sameSite = $cookie->getSameSite();
                $this->assertNotNull($sameSite, 'Session cookie must have SameSite set');
                $this->assertEquals('strict', strtolower($sameSite), 'Session cookie SameSite should be strict');
            }
        }
    }

    public function test_post_without_csrf_token_is_rejected(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Should get 419 (Page Expired) or validation error, not 200
        $this->assertContains($response->getStatusCode(), [419, 302]);
    }

    public function test_api_preflight_options_returns_ok(): void
    {
        $response = $this->withHeaders([
            'Origin' => config('app.url'),
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/v1/courses');

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    public function test_cors_rejects_disallowed_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.example.com',
        ])->getJson('/api/v1/courses');

        // The CORS middleware should not echo back the disallowed origin
        $origin = $response->headers->get('Access-Control-Allow-Origin');
        $this->assertNotEquals('https://evil.example.com', $origin);
    }

    public function test_session_is_encrypted(): void
    {
        $this->assertTrue(config('session.encrypt'), 'Session data must be encrypted');
    }

    public function test_session_driver_config_defaults_to_redis(): void
    {
        // phpunit.xml overrides SESSION_DRIVER=array for tests.
        // Verify the config file itself defaults to redis.
        $configFile = file_get_contents(config_path('session.php'));
        $this->assertStringContainsString("'redis'", $configFile);
    }

    public function test_session_lifetime_is_limited(): void
    {
        $lifetime = config('session.lifetime');
        $this->assertLessThanOrEqual(120, $lifetime, 'Session lifetime should be at most 120 minutes');
        $this->assertGreaterThan(0, $lifetime, 'Session lifetime should be positive');
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);
        $this->withSession(['test' => 'value']);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertNull(auth()->user());
    }
}
