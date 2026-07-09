<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class HomepageHeroRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return TestResponse<Response>
     */
    private function getHomepage(string $locale): TestResponse
    {
        $response = $this->get('/?lang='.$locale);
        $this->assertInstanceOf(TestResponse::class, $response);

        return $response;
    }

    private function heroSection(string $content): string
    {
        $start = strpos($content, 'data-testid="homepage-hero"');
        $this->assertNotFalse($start, 'Hero wrapper not found.');

        return substr($content, $start, 2000);
    }

    public function test_english_homepage_returns_200(): void
    {
        $response = $this->getHomepage('en');
        $response->assertStatus(200);
    }

    public function test_english_homepage_contains_headline_and_ctas(): void
    {
        $response = $this->getHomepage('en');
        $response->assertStatus(200);
        $response->assertSee('Courses for Kurdistan');
        $response->assertSee('delivered through Telegram');
        $response->assertSee('Browse Courses');
        $response->assertSee('Contact');
    }

    public function test_english_homepage_contains_robot_component(): void
    {
        $response = $this->getHomepage('en');
        $content = $response->getContent();
        $this->assertIsString($content);

        $response->assertSee('data-testid="homepage-hero-robot"', false);
        $response->assertSee('hero-robot-stage', false);
        $response->assertSee('data-testid="homepage-hero-text"', false);
    }

    public function test_english_homepage_does_not_contain_kurdish_text(): void
    {
        $response = $this->getHomepage('en');
        $content = $response->getContent();
        $this->assertIsString($content);

        $this->assertStringNotContainsString('کۆرسەکان بۆ کوردستان', $content);
        $this->assertStringNotContainsString('تێلیگرامەوە', $content);
    }

    public function test_english_homepage_does_not_contain_fake_robot_or_logo(): void
    {
        $response = $this->getHomepage('en');
        $content = $response->getContent();
        $this->assertIsString($content);

        $hero = $this->heroSection($content);

        $this->assertStringNotContainsString('hero-robot.svg', $hero);
        $this->assertStringNotContainsString('cwt_academy-logo.jpg', $hero);
        $this->assertStringNotContainsString('cwt-academy-robot.jpg', $hero);
        $this->assertStringNotContainsString('fake-svg-robot', $hero);
        $this->assertStringNotContainsString('Cwt Academy Robot', $hero);
        $this->assertStringNotContainsString('<img', $hero);
    }

    public function test_english_homepage_has_text_before_robot_in_dom(): void
    {
        $response = $this->getHomepage('en');
        $content = $response->getContent();
        $this->assertIsString($content);

        $textPosition = strpos($content, 'data-testid="homepage-hero-text"');
        $robotPosition = strpos($content, 'data-testid="homepage-hero-robot"');

        $this->assertNotFalse($textPosition, 'Hero text not found.');
        $this->assertNotFalse($robotPosition, 'Hero robot not found.');
        $this->assertLessThan($robotPosition, $textPosition, 'English text should appear before the robot in the DOM.');
    }

    public function test_english_layout_classes_indicate_text_left_robot_right(): void
    {
        $response = $this->getHomepage('en');
        $content = $response->getContent();
        $this->assertIsString($content);

        $textPosition = strpos($content, 'data-testid="homepage-hero-text"');
        $robotPosition = strpos($content, 'data-testid="homepage-hero-robot"');

        $this->assertNotFalse($textPosition);
        $this->assertNotFalse($robotPosition);

        $textSnippet = substr($content, $textPosition, 250);
        $robotSnippet = substr($content, $robotPosition, 250);

        $this->assertStringContainsString('lg:order-1', $textSnippet, 'English text column should be order-1 (left).');
        $this->assertStringContainsString('lg:order-2', $robotSnippet, 'English robot column should be order-2 (right).');
        $this->assertStringContainsString('dir="ltr"', $content);
    }

    public function test_kurdish_homepage_returns_200(): void
    {
        $response = $this->getHomepage('ku');
        $response->assertStatus(200);
    }

    public function test_kurdish_homepage_contains_headline_and_ctas(): void
    {
        $response = $this->getHomepage('ku');
        $response->assertStatus(200);
        $response->assertSee('کۆرسەکان بۆ کوردستان');
        $response->assertSee('تێلیگرامەوە');
    }

    public function test_kurdish_homepage_contains_robot_component(): void
    {
        $response = $this->getHomepage('ku');
        $response->assertStatus(200);
        $response->assertSee('data-testid="homepage-hero-robot"', false);
        $response->assertSee('hero-robot-stage', false);
        $response->assertSee('data-testid="homepage-hero-text"', false);
    }

    public function test_kurdish_homepage_does_not_contain_english_text(): void
    {
        $response = $this->getHomepage('ku');
        $content = $response->getContent();
        $this->assertIsString($content);

        $this->assertStringNotContainsString('Courses for Kurdistan', $content);
        $this->assertStringNotContainsString('delivered through Telegram', $content);
    }

    public function test_kurdish_homepage_does_not_contain_fake_robot_or_logo(): void
    {
        $response = $this->getHomepage('ku');
        $content = $response->getContent();
        $this->assertIsString($content);

        $hero = $this->heroSection($content);

        $this->assertStringNotContainsString('hero-robot.svg', $hero);
        $this->assertStringNotContainsString('cwt_academy-logo.jpg', $hero);
        $this->assertStringNotContainsString('cwt-academy-robot.jpg', $hero);
        $this->assertStringNotContainsString('fake-svg-robot', $hero);
        $this->assertStringNotContainsString('Cwt Academy Robot', $hero);
        $this->assertStringNotContainsString('<img', $hero);
    }

    public function test_kurdish_homepage_has_robot_before_text_in_dom(): void
    {
        $response = $this->getHomepage('ku');
        $content = $response->getContent();
        $this->assertIsString($content);

        $textPosition = strpos($content, 'data-testid="homepage-hero-text"');
        $robotPosition = strpos($content, 'data-testid="homepage-hero-robot"');

        $this->assertNotFalse($textPosition, 'Hero text not found.');
        $this->assertNotFalse($robotPosition, 'Hero robot not found.');
        $this->assertLessThan($textPosition, $robotPosition, 'Kurdish robot should appear before the text in the DOM.');
    }

    public function test_kurdish_layout_classes_indicate_robot_left_text_right(): void
    {
        $response = $this->getHomepage('ku');
        $content = $response->getContent();
        $this->assertIsString($content);

        $textPosition = strpos($content, 'data-testid="homepage-hero-text"');
        $robotPosition = strpos($content, 'data-testid="homepage-hero-robot"');

        $this->assertNotFalse($textPosition);
        $this->assertNotFalse($robotPosition);

        $textSnippet = substr($content, $textPosition, 250);
        $robotSnippet = substr($content, $robotPosition, 250);

        $this->assertStringContainsString('lg:order-1', $robotSnippet, 'Kurdish robot column should be order-1 (left).');
        $this->assertStringContainsString('lg:order-2', $textSnippet, 'Kurdish text column should be order-2 (right).');
        $this->assertStringContainsString('dir="rtl"', $content);
    }

    public function test_hero_data_locale_matches_language(): void
    {
        $en = $this->getHomepage('en')->getContent();
        $ku = $this->getHomepage('ku')->getContent();

        $this->assertIsString($en);
        $this->assertIsString($ku);

        $this->assertStringContainsString('data-locale="en"', $en);
        $this->assertStringContainsString('data-locale="ku"', $ku);
    }

    public function test_homepage_hero_is_never_blank_after_navbar(): void
    {
        $response = $this->getHomepage('en');
        $content = $response->getContent();
        $this->assertIsString($content);

        $this->assertStringContainsString('data-testid="homepage-hero"', $content);
        $this->assertStringContainsString('data-testid="homepage-hero-text"', $content);
        $this->assertStringContainsString('data-testid="homepage-hero-robot"', $content);
        $this->assertStringNotContainsString('Loading 3D scene', $content);
        $this->assertStringNotContainsString('Interactive 3D experience is currently unavailable', $content);
    }

    public function test_neither_locale_shows_placeholder_or_spinner_only_robot(): void
    {
        foreach (['en', 'ku'] as $locale) {
            $response = $this->getHomepage($locale);
            $content = $response->getContent();
            $this->assertIsString($content);

            $hero = $this->heroSection($content);

            // No spinner/loader inside the hero robot area.
            $this->assertStringNotContainsString('animate-spin', $hero);
            $this->assertStringNotContainsString('loader', $hero);
            $this->assertStringNotContainsString('Loading 3D scene', $hero);

            // No broken image alt fallback text.
            $this->assertStringNotContainsString('Cwt Academy Robot', $hero);
        }
    }
}
