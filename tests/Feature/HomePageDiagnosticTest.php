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

    public function test_homepage_renders_hero_headline_and_cta_buttons(): void
    {
        $response = $this->get('/');
        $content = $response->getContent();

        $response->assertStatus(200);
        $this->assertIsString($content);
        $this->assertStringContainsString(__('home.hero_title'), $content);
        $this->assertStringContainsString(__('home.hero_highlight'), $content);
        $this->assertStringContainsString(__('home.cta_browse'), $content);
        $this->assertStringContainsString(__('home.cta_contact'), $content);
    }

    public function test_homepage_is_not_blank_and_contains_hero_fallback(): void
    {
        $response = $this->get('/');
        $content = $response->getContent();

        $response->assertStatus(200);
        $this->assertIsString($content);
        $this->assertStringContainsString('hero-card', $content);
        $this->assertStringContainsString('hero-title-display', $content);
        $this->assertStringContainsString('hero-robot-stage', $content);

        // The robot stage must exist and the page must not contain the fake
        // yellow cartoon robot SVG that was temporarily overlaid on the hero.
        $this->assertStringContainsString('hero-robot-stage', $content);
        $this->assertStringNotContainsString('hero-robot.svg', $content);
        $this->assertStringNotContainsString('Interactive 3D experience is currently unavailable', $content);
        $this->assertStringNotContainsString('Loading 3D scene', $content);

        // The hero section should contain visible text immediately after the navbar.
        $this->assertGreaterThan(
            1500,
            strlen(strip_tags($content)),
            'Homepage rendered without meaningful text content.'
        );
    }

    public function test_kurdish_homepage_uses_rtl_layout_and_keeps_robot_visible(): void
    {
        $response = $this->withSession(['locale' => 'ku'])->get('/');
        $content = $response->getContent();

        $response->assertStatus(200);
        $this->assertIsString($content);

        // Kurdish headline must be present.
        $this->assertStringContainsString(__('home.hero_title', [], 'ku'), $content);
        $this->assertStringContainsString(__('home.hero_highlight', [], 'ku'), $content);

        // Robot must not be hidden by locale-specific classes and the robot
        // stage must contain the real Spline mount / loading indicator.
        $this->assertStringContainsString('hero-robot-stage', $content);
        $this->assertStringContainsString('3d_rotation', $content);
        $this->assertStringNotContainsString('hero-robot.svg', $content);

        // Kurdish layout: robot first (order-1), text second (order-2) on desktop.
        $robotDiv = substr($content, (int) strpos($content, 'hero-robot relative'), 120);
        $this->assertStringContainsString('lg:order-1', $robotDiv, 'Kurdish homepage should render robot on the left.');
        $this->assertMatchesRegularExpression(
            '/dir="rtl"\s+class="[^"]*flex-col[^"]*lg:order-2/',
            $content,
            'Kurdish homepage should render text on the right.'
        );
    }

    public function test_english_homepage_uses_ltr_layout_and_keeps_robot_visible(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get('/');
        $content = $response->getContent();

        $response->assertStatus(200);
        $this->assertIsString($content);

        // English headline must be present and the page must not show Kurdish ordering.
        $this->assertStringContainsString(__('home.hero_title', [], 'en'), $content);
        $this->assertStringContainsString(__('home.hero_highlight', [], 'en'), $content);

        // Robot must remain visible (real Spline mount / loading indicator).
        $this->assertStringContainsString('hero-robot-stage', $content);
        $this->assertStringContainsString('3d_rotation', $content);
        $this->assertStringNotContainsString('hero-robot.svg', $content);

        // English layout: text first (order-1), robot second (order-2) on desktop.
        $robotDiv = substr($content, (int) strpos($content, 'hero-robot relative'), 120);
        $this->assertStringContainsString('lg:order-2', $robotDiv, 'English homepage should render robot on the right.');
        $this->assertMatchesRegularExpression(
            '/dir="ltr"\s+class="[^"]*flex-col[^"]*lg:order-1/',
            $content,
            'English homepage should render text on the left.'
        );
    }

    public function test_homepage_injects_vite_assets(): void
    {
        $response = $this->get('/');
        $content = $response->getContent();

        $response->assertStatus(200);
        $this->assertIsString($content, 'Homepage response content is empty.');

        // In a built (production) manifest state, Vite emits /build/assets/... URLs.
        // In a dev state, it emits @vite/client and http://localhost:5173/... URLs.
        $hasBuildAsset = (bool) preg_match('/\/build\/assets\/app-[A-Za-z0-9_-]+\.css/', $content);
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
