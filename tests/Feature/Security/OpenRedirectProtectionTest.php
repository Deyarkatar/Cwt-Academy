<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Security\UrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OpenRedirectProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirect_uses_intended_or_default(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
            'password' => Hash::make('SecurePass123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'SecurePass123!',
            'captcha_answer' => 'skip',
        ]);

        $response->assertRedirect('/dashboard');
    }

    public function test_locale_switch_does_not_allow_open_redirect(): void
    {
        $response = $this->withHeaders(['Referer' => 'https://evil.com'])
            ->get('/locale/en?redirect=https://evil.com');

        $this->assertNotEquals('https://evil.com', $response->headers->get('Location'));
    }

    public function test_url_helper_safe_redirect_rejects_external_urls(): void
    {
        $result = UrlHelper::safeRedirect('https://evil.com/path');
        $this->assertEquals('/', $result);
    }

    public function test_url_helper_safe_redirect_rejects_protocol_relative(): void
    {
        $result = UrlHelper::safeRedirect('//evil.com/path');
        $this->assertEquals('/', $result);
    }

    public function test_url_helper_safe_redirect_allows_relative_paths(): void
    {
        $result = UrlHelper::safeRedirect('/dashboard');
        $this->assertEquals('/dashboard', $result);
    }

    public function test_url_helper_safe_redirect_rejects_null_bytes(): void
    {
        $result = UrlHelper::safeRedirect("/dashboard\x00.evil.com");
        $this->assertEquals('/', $result);
    }

    public function test_url_helper_safe_href_rejects_javascript_scheme(): void
    {
        $result = UrlHelper::safeHref('javascript:alert(1)');
        $this->assertEquals('#', $result);
    }

    public function test_url_helper_safe_href_rejects_data_scheme(): void
    {
        $result = UrlHelper::safeHref('data:text/html,<script>alert(1)</script>');
        $this->assertEquals('#', $result);
    }

    public function test_url_helper_safe_telegram_url_only_allows_t_me(): void
    {
        $this->assertEquals('https://t.me/test', UrlHelper::safeTelegramUrl('https://t.me/test'));
        $this->assertEquals('#', UrlHelper::safeTelegramUrl('https://evil.com/t.me'));
    }
}
