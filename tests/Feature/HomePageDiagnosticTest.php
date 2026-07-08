<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageDiagnosticTest extends TestCase
{
    public function test_homepage_returns_successful_response_with_expected_content(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Cwt Academy');
        $response->assertSee('<title>Cwt Academy - Kurdistan Course Marketplace</title>', false);
        $response->assertSee('id="spline-mount"', false);
    }

    public function test_homepage_injects_vite_assets(): void
    {
        $response = $this->get('/');
        $content = $response->getContent();

        $response->assertStatus(200);
        $this->assertIsString($content, 'Homepage response content is empty.');

        // In a built (production) manifest state, Vite emits /build/assets/... URLs.
        // In a dev state, it emits @vite/client and http://localhost:5173/... URLs.
        $hasBuildAsset = (bool) preg_match('/\/build\/assets\/app-[A-Za-z0-9]+\.css/', $content);
        $hasDevClient = str_contains($content, '@vite/client');
        $hasDevCss = (bool) preg_match('/http:\/\/localhost:5173\/resources\/css\/app\.css/', $content);
        $hasDevJs = (bool) preg_match('/http:\/\/localhost:5173\/resources\/js\/app\.js/', $content);

        $this->assertTrue(
            $hasBuildAsset || $hasDevClient || $hasDevCss || $hasDevJs,
            "Vite assets are not injected in the response.\n"
            ."Expected one of: /build/assets/app-*.css, @vite/client, or localhost:5173 resources.\n"
            ."Actual <head> snippet:\n".substr($content, 0, 2500)
        );
    }
}
