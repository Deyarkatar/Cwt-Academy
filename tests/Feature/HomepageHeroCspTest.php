<?php

namespace Tests\Feature;

use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class HomepageHeroCspTest extends TestCase
{
    /**
     * @return TestResponse<Response>
     */
    private function getHomepageWithHost(string $host): TestResponse
    {
        $response = $this->call('GET', '/', [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => explode(':', $host)[0],
            'SERVER_PORT' => (string) (explode(':', $host)[1] ?? '8000'),
        ]);
        $this->assertInstanceOf(TestResponse::class, $response);

        return $response;
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function cspHeader(TestResponse $response): string
    {
        $header = $response->headers->get('Content-Security-Policy');
        $this->assertIsString($header, 'CSP header missing from homepage response.');

        return $header;
    }

    public function test_homepage_sends_content_security_policy_header(): void
    {
        $response = $this->getHomepageWithHost('127.0.0.1:8000');
        $response->assertStatus(200);

        $csp = $this->cspHeader($response);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
    }

    public function test_local_csp_allows_127_0_0_1_dev_server(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $response = $this->getHomepageWithHost('127.0.0.1:8000');
        $csp = $this->cspHeader($response);

        // Vite HMR dev server origin must be present so HMR assets are not blocked.
        $this->assertStringContainsString('127.0.0.1:5173', $csp);

        // The configured APP_URL origin must be included so asset URLs generated for
        // 127.0.0.1:8000 are not blocked by CSP.
        $this->assertStringContainsString('127.0.0.1:8000', $csp);
    }

    public function test_local_csp_allows_localhost_dev_server(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $response = $this->getHomepageWithHost('localhost:8000');
        $csp = $this->cspHeader($response);

        $this->assertStringContainsString('localhost:5173', $csp);
        $this->assertStringContainsString('localhost:8000', $csp);
    }

    public function test_built_css_asset_exists_for_csp_self_origin(): void
    {
        $files = glob(public_path('build/assets/app-*.css'));
        $this->assertIsArray($files, 'Built CSS asset scan failed.');
        $this->assertNotEmpty($files, 'Built CSS asset not found.');

        $path = $files[0];
        $this->assertFileExists($path);
        $this->assertStringEndsWith('.css', $path);
        $this->assertNotEmpty(file_get_contents($path));

        // Because 'self' is in style-src, a built CSS file at this public path
        // must be present and non-empty so the page is not unstyled.
    }

    public function test_csp_allows_spline_robot_cdn_origins(): void
    {
        $response = $this->getHomepageWithHost('127.0.0.1:8000');
        $csp = $this->cspHeader($response);

        // Spline scene + WASM + Draco decoder origins required for the real robot.
        $this->assertStringContainsString('https://prod.spline.design', $csp);
        $this->assertStringContainsString('https://unpkg.com', $csp);
        $this->assertStringContainsString('https://www.gstatic.com', $csp);

        // WebAssembly evaluation is required by the Spline runtime.
        $this->assertStringContainsString("'wasm-unsafe-eval'", $csp);
    }
}
