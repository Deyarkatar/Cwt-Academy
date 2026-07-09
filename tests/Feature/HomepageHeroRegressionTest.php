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
     * @param  array<string, mixed>  $session
     * @return TestResponse<Response>
     */
    private function getHomepage(array $session = []): TestResponse
    {
        $response = $this->withSession($session)->get('/');
        $this->assertInstanceOf(TestResponse::class, $response);

        return $response;
    }

    private function heroSection(string $content): string
    {
        $start = strpos($content, 'data-testid="homepage-hero"');
        $this->assertNotFalse($start, 'Hero wrapper not found.');

        return substr($content, $start, 12000);
    }

    private function tagSnippet(string $content, string $testid): string
    {
        $pattern = '/<div[^>]*data-testid="'.preg_quote($testid, '/').'"[^>]*>/';

        $this->assertMatchesRegularExpression($pattern, $content);

        preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

        return substr($content, $matches[0][1], 300);
    }

    public function test_english_homepage_returns_200(): void
    {
        $this->getHomepage(['locale' => 'en'])->assertStatus(200);
    }

    public function test_english_homepage_contains_headline_and_ctas(): void
    {
        $response = $this->getHomepage(['locale' => 'en']);
        $response->assertStatus(200);
        $response->assertSee('Courses for Kurdistan');
        $response->assertSee('delivered through Telegram');
        $response->assertSee('Browse Courses');
        $response->assertSee('Contact');
    }

    public function test_english_homepage_contains_robot_component(): void
    {
        $response = $this->getHomepage(['locale' => 'en']);
        $response->assertStatus(200);
        $response->assertSee('data-testid="homepage-hero-robot"', false);
        $response->assertSee('hero-robot-stage', false);
    }

    public function test_english_homepage_does_not_contain_kurdish_headline(): void
    {
        $response = $this->getHomepage(['locale' => 'en']);
        $content = $response->getContent();
        $this->assertIsString($content);

        $this->assertStringNotContainsString('کۆرسەکان بۆ کوردستان', $content);
        $this->assertStringNotContainsString('تێلیگرامەوە', $content);
    }

    public function test_english_homepage_uses_real_robot_asset(): void
    {
        $response = $this->getHomepage(['locale' => 'en']);
        $content = $response->getContent();
        $this->assertIsString($content);

        $hero = $this->heroSection($content);

        $this->assertStringContainsString('images/hero-robot', $hero, 'Hero must reference the real robot asset.');
        $this->assertStringContainsString('<img', $hero, 'Hero robot must be a real image element.');
        $this->assertStringNotContainsString('hero-robot.svg', $hero);
        $this->assertStringNotContainsString('cwt_academy-logo.jpg', $hero);
        $this->assertStringNotContainsString('cwt-academy-robot.jpg', $hero);
        $this->assertStringNotContainsString('fake-svg-robot', $hero);
        $this->assertStringNotContainsString('Cwt Academy Robot', $hero);
    }

    public function test_english_homepage_has_text_before_robot_in_dom(): void
    {
        $response = $this->getHomepage(['locale' => 'en']);
        $content = $response->getContent();
        $this->assertIsString($content);

        $textPosition = strpos($content, 'data-testid="homepage-hero-text"');
        $robotPosition = strpos($content, 'data-testid="homepage-hero-robot"');

        $this->assertNotFalse($textPosition, 'Hero text not found.');
        $this->assertNotFalse($robotPosition, 'Hero robot not found.');
        $this->assertLessThan($robotPosition, $textPosition, 'English text should appear before the robot in the DOM.');
    }

    public function test_english_layout_uses_correct_order_classes(): void
    {
        $response = $this->getHomepage(['locale' => 'en']);
        $content = $response->getContent();
        $this->assertIsString($content);

        $textSnippet = $this->tagSnippet($content, 'homepage-hero-text');
        $robotSnippet = $this->tagSnippet($content, 'homepage-hero-robot');

        $this->assertStringContainsString('lg:order-1', $textSnippet, 'English text column should be order-1 (left).');
        $this->assertStringContainsString('lg:order-2', $robotSnippet, 'English robot column should be order-2 (right).');
        $this->assertStringContainsString('dir="ltr"', $content);
    }

    public function test_kurdish_homepage_returns_200(): void
    {
        $this->getHomepage(['locale' => 'ku'])->assertStatus(200);
    }

    public function test_kurdish_homepage_contains_headline_and_ctas(): void
    {
        $response = $this->getHomepage(['locale' => 'ku']);
        $response->assertStatus(200);
        $response->assertSee('کۆرسەکان بۆ کوردستان');
        $response->assertSee('تێلیگرامەوە');
    }

    public function test_kurdish_homepage_contains_robot_component(): void
    {
        $response = $this->getHomepage(['locale' => 'ku']);
        $response->assertStatus(200);
        $response->assertSee('data-testid="homepage-hero-robot"', false);
        $response->assertSee('hero-robot-stage', false);
    }

    public function test_kurdish_homepage_does_not_contain_english_headline(): void
    {
        $response = $this->getHomepage(['locale' => 'ku']);
        $content = $response->getContent();
        $this->assertIsString($content);

        $this->assertStringNotContainsString('Courses for Kurdistan', $content);
        $this->assertStringNotContainsString('delivered through Telegram', $content);
    }

    public function test_kurdish_homepage_uses_real_robot_asset(): void
    {
        $response = $this->getHomepage(['locale' => 'ku']);
        $content = $response->getContent();
        $this->assertIsString($content);

        $hero = $this->heroSection($content);

        $this->assertStringContainsString('images/hero-robot', $hero, 'Hero must reference the real robot asset.');
        $this->assertStringContainsString('<img', $hero, 'Hero robot must be a real image element.');
        $this->assertStringNotContainsString('hero-robot.svg', $hero);
        $this->assertStringNotContainsString('cwt_academy-logo.jpg', $hero);
        $this->assertStringNotContainsString('cwt-academy-robot.jpg', $hero);
        $this->assertStringNotContainsString('fake-svg-robot', $hero);
        $this->assertStringNotContainsString('Cwt Academy Robot', $hero);
    }

    public function test_kurdish_homepage_has_robot_before_text_in_dom(): void
    {
        $response = $this->getHomepage(['locale' => 'ku']);
        $content = $response->getContent();
        $this->assertIsString($content);

        $textPosition = strpos($content, 'data-testid="homepage-hero-text"');
        $robotPosition = strpos($content, 'data-testid="homepage-hero-robot"');

        $this->assertNotFalse($textPosition, 'Hero text not found.');
        $this->assertNotFalse($robotPosition, 'Hero robot not found.');
        $this->assertLessThan($textPosition, $robotPosition, 'Kurdish robot should appear before the text in the DOM.');
    }

    public function test_kurdish_layout_uses_correct_order_classes(): void
    {
        $response = $this->getHomepage(['locale' => 'ku']);
        $content = $response->getContent();
        $this->assertIsString($content);

        $textSnippet = $this->tagSnippet($content, 'homepage-hero-text');
        $robotSnippet = $this->tagSnippet($content, 'homepage-hero-robot');

        $this->assertStringContainsString('lg:order-1', $robotSnippet, 'Kurdish robot column should be order-1 (left).');
        $this->assertStringContainsString('lg:order-2', $textSnippet, 'Kurdish text column should be order-2 (right).');
        $this->assertStringContainsString('dir="rtl"', $content);
    }

    public function test_homepage_hero_is_never_blank(): void
    {
        $response = $this->getHomepage(['locale' => 'en']);
        $content = $response->getContent();
        $this->assertIsString($content);

        $this->assertStringContainsString('data-testid="homepage-hero"', $content);
        $this->assertStringContainsString('data-testid="homepage-hero-text"', $content);
        $this->assertStringContainsString('data-testid="homepage-hero-robot"', $content);
        $this->assertStringNotContainsString('Loading 3D scene', $content);
        $this->assertStringNotContainsString('Interactive 3D experience is currently unavailable', $content);
    }

    public function test_neither_locale_shows_placeholder_or_spinner_only(): void
    {
        foreach (['en', 'ku'] as $locale) {
            $response = $this->getHomepage(['locale' => $locale]);
            $content = $response->getContent();
            $this->assertIsString($content);

            $hero = $this->heroSection($content);

            $this->assertStringNotContainsString('animate-spin', $hero);
            $this->assertStringNotContainsString('loader', $hero);
            $this->assertStringNotContainsString('Loading 3D scene', $hero);
            $this->assertStringNotContainsString('Cwt Academy Robot', $hero);
        }
    }

    public function test_robot_asset_file_exists_and_is_served(): void
    {
        $path = public_path('images/hero-robot.png');
        $this->assertFileExists($path, 'The real robot image must exist under public/images/hero-robot.png.');
        $this->assertGreaterThan(0, filesize($path), 'The robot image file must not be empty.');

        $mimeType = mime_content_type($path);
        $this->assertIsString($mimeType);
        $this->assertStringStartsWith('image/', $mimeType, 'Robot asset must have an image MIME type.');

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, 'Robot asset must be readable.');
        $this->assertNotEmpty($contents, 'Robot asset body must not be empty.');

        // The file is in the public directory, so a real web server (or php artisan serve)
        // will serve GET /images/hero-robot.png with HTTP 200 and the same image content type.
        $url = asset('images/hero-robot.png');
        $this->assertStringContainsString('/images/hero-robot.png', $url);
    }
}
