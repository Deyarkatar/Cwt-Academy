<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class HomepageRobotVisualTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return TestResponse<Response>
     */
    private function getHomepage(string $locale): TestResponse
    {
        $response = $this->followRedirects($this->get('/locale/'.$locale));
        $this->assertInstanceOf(TestResponse::class, $response);

        return $response;
    }

    public function test_english_homepage_contains_english_headline(): void
    {
        $response = $this->getHomepage('en');
        $response->assertStatus(200);
        $response->assertSee('Courses for Kurdistan');
        $response->assertSee('delivered through Telegram');
    }

    public function test_kurdish_homepage_contains_kurdish_headline(): void
    {
        $response = $this->getHomepage('ku');
        $response->assertStatus(200);
        $response->assertSee('کۆرسەکان بۆ کوردستان');
        $response->assertSee('لە ڕێگەی تێلیگرامەوە');
    }

    public function test_homepage_contains_shared_robot_component_markup(): void
    {
        $response = $this->getHomepage('en');
        $response->assertStatus(200);
        $response->assertSee('hero-robot');
        $response->assertSee('hero-robot-stage');
        $response->assertDontSee('images/hero-robot.svg');
    }

    public function test_kurdish_homepage_contains_shared_robot_component_markup(): void
    {
        $response = $this->getHomepage('ku');
        $response->assertStatus(200);
        $response->assertSee('hero-robot');
        $response->assertSee('hero-robot-stage');
        $response->assertDontSee('images/hero-robot.svg');
    }

    public function test_homepage_does_not_contain_cwt_logo_in_hero(): void
    {
        $response = $this->getHomepage('en');
        $response->assertStatus(200);
        $response->assertDontSee('cwt-academy-robot.jpg');
        $response->assertDontSee('cwt_academy-logo.jpg');
    }

    public function test_homepage_does_not_contain_fake_svg_robot(): void
    {
        $response = $this->getHomepage('en');
        $response->assertStatus(200);
        $response->assertDontSee('fake-svg-robot');
        $response->assertDontSee('3d_rotation'); // The old fake fallback icon
    }

    public function test_homepage_does_not_render_empty_robot_container(): void
    {
        $response = $this->getHomepage('en');
        $response->assertStatus(200);
        $content = $response->getContent();

        if ($content === false) {
            $this->fail('Response content is false');
        }

        // The robot is rendered by the shared Spline runtime; the SSR stage
        // must exist and must not contain the deleted fake SVG robot.
        $this->assertStringContainsString('hero-robot-stage', $content);
        $this->assertStringNotContainsString('images/hero-robot.svg', $content);
    }

    public function test_robot_layout_english_text_left_robot_right(): void
    {
        $response = $this->getHomepage('en');
        $response->assertStatus(200);
        $content = $response->getContent();

        if ($content === false) {
            $this->fail('Response content is false');
        }

        // English should have text first (order-1) and robot second (order-2)
        $this->assertStringContainsString('lg:order-1', $content); // Text
        $this->assertStringContainsString('lg:order-2', $content); // Robot
        $this->assertStringContainsString('dir="ltr"', $content); // LTR direction
    }

    public function test_robot_layout_kurdish_robot_left_text_right(): void
    {
        $response = $this->getHomepage('ku');
        $response->assertStatus(200);
        $content = $response->getContent();

        if ($content === false) {
            $this->fail('Response content is false');
        }

        // Kurdish should have robot first (order-1) and text second (order-2)
        $this->assertStringContainsString('lg:order-1', $content); // Robot
        $this->assertStringContainsString('lg:order-2', $content); // Text
        $this->assertStringContainsString('dir="rtl"', $content); // RTL direction

        // The robot markup must appear before the visible headline text in the raw HTML.
        // (The headline also exists earlier in a data attribute, so the <h1> tag is the
        // correct anchor for layout order.)
        $robotPosition = strpos($content, 'hero-robot relative');
        $textPosition = strpos($content, '<h1 class="font-extrabold text-white tracking-tight hero-title-display" data-rtl="true">');
        $this->assertNotFalse($robotPosition, 'Robot markup not found.');
        $this->assertNotFalse($textPosition, 'Kurdish headline <h1> not found.');
        $this->assertLessThan($textPosition, $robotPosition, 'Kurdish robot should appear before the visible text in the HTML.');
    }

    public function test_kurdish_homepage_does_not_use_cwt_logo_or_fake_robot(): void
    {
        $response = $this->getHomepage('ku');
        $response->assertStatus(200);

        $response->assertDontSee('cwt-academy-robot.jpg');
        $response->assertDontSee('cwt_academy-logo.jpg');
        $response->assertDontSee('images/hero-robot.svg');
    }
}
