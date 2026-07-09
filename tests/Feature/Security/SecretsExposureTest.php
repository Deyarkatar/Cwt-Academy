<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretsExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_env_file_is_not_web_accessible(): void
    {
        $response = $this->get('/.env');
        $response->assertNotFound();
    }

    public function test_git_directory_is_not_web_accessible(): void
    {
        $response = $this->get('/.git/config');
        $response->assertNotFound();
    }

    public function test_composer_json_is_not_web_accessible(): void
    {
        $response = $this->get('/composer.json');
        $response->assertNotFound();
    }

    public function test_artisan_is_not_web_accessible(): void
    {
        $response = $this->get('/artisan');
        $response->assertNotFound();
    }

    public function test_env_example_does_not_contain_real_secrets(): void
    {
        $content = file_get_contents(base_path('.env.example'));
        $this->assertNotFalse($content);

        $this->assertStringNotContainsString('AKIA', $content, 'No AWS keys in .env.example');
        $this->assertStringNotContainsString('ghp_', $content, 'No GitHub tokens in .env.example');
        $this->assertStringNotContainsString('sk-', $content, 'No API keys in .env.example');
        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $content, 'No private keys in .env.example');
    }

    public function test_app_debug_is_false_in_env_example(): void
    {
        $content = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('APP_DEBUG=false', $content);
    }

    public function test_session_secure_cookie_is_true_in_env_example(): void
    {
        $content = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $content);
    }

    public function test_redis_password_is_not_hardcoded_in_env_example(): void
    {
        $content = file_get_contents(base_path('.env.example'));
        $this->assertStringNotContainsString('REDIS_PASSWORD=change-me', $content);
    }

    public function test_admin_default_password_is_commented_out_in_env_example(): void
    {
        $content = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('# ADMIN_DEFAULT_PASSWORD', $content);
    }

    public function test_error_response_does_not_leak_stack_traces(): void
    {
        $response = $this->getJson('/api/v1/course-requests/NONEXISTENTCODE1');

        $content = $response->getContent();
        $this->assertStringNotContainsString('Stack trace', $content);
        $this->assertStringNotContainsString('/var/www/', $content);
        $this->assertStringNotContainsString('/home/', $content);
    }
}
