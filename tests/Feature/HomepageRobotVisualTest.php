<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageRobotVisualTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_homepage_contains_english_headline()
    {
        $response = $this->get('/locale/en');
        $response->assertRedirect('/');
        $response = $this->followRedirects($response);
        $response->assertStatus(200);
        $response->assertSee('Courses for Kurdistan');
        $response->assertSee('delivered through Telegram');
    }

    public function test_kurdish_homepage_contains_kurdish_headline()
    {
        $response = $this->get('/locale/ku');
        $response->assertRedirect('/');
        $response = $this->followRedirects($response);
        $response->assertStatus(200);
        $response->assertSee('کۆرسەکان بۆ کوردستان');
        $response->assertSee('لە ڕێگەی تێلیگرامەوە');
    }

    public function test_homepage_contains_shared_robot_component_markup()
    {
        $response = $this->get('/locale/en');
        $response = $this->followRedirects($response);
        $response->assertStatus(200);
        $response->assertSee('hero-robot');
        $response->assertSee('images/cwt-academy-robot.jpg');
    }

    public function test_kurdish_homepage_contains_shared_robot_component_markup()
    {
        $response = $this->get('/locale/ku');
        $response = $this->followRedirects($response);
        $response->assertStatus(200);
        $response->assertSee('hero-robot');
        $response->assertSee('images/cwt-academy-robot.jpg');
    }

    public function test_robot_asset_url_returns_http_200()
    {
        $response = $this->get('/images/cwt-academy-robot.jpg');
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'image/jpeg');
    }

    public function test_english_homepage_contains_robot_visual_markup()
    {
        $response = $this->get('/locale/en');
        $response = $this->followRedirects($response);
        $response->assertStatus(200);
        $response->assertSee('hero-robot-stage');
        $response->assertSee('alt="Cwt Academy Robot"');
        $response->assertSee('lg:order-2'); // Robot on the right in English
    }

    public function test_kurdish_homepage_contains_robot_visual_markup()
    {
        $response = $this->get('/locale/ku');
        $response = $this->followRedirects($response);
        $response->assertStatus(200);
        $response->assertSee('hero-robot-stage');
        $response->assertSee('alt="Cwt Academy Robot"');
        $response->assertSee('lg:order-1'); // Robot on the left in Kurdish
    }

    public function test_homepage_does_not_contain_fake_svg_robot()
    {
        $response = $this->get('/locale/en');
        $response = $this->followRedirects($response);
        $response->assertStatus(200);
        $response->assertDontSee('fake-svg-robot');
        $response->assertDontSee('3d_rotation'); // The old fake fallback icon
    }

    public function test_homepage_does_not_render_empty_robot_container(): void
    {
        $response = $this->get('/locale/en');
        $response = $this->followRedirects($response);
        $response->assertStatus(200);
        $content = $response->getContent();
        
        if ($content === false) {
            $this->fail('Response content is false');
        }
        
        // Ensure robot container has actual content
        $this->assertStringContainsString('<img', $content);
        $this->assertStringContainsString('images/cwt-academy-robot.jpg', $content);
        
        // Ensure robot stage is not empty
        $this->assertStringContainsString('hero-robot-stage', $content);
        $this->assertStringContainsString('object-contain', $content);
    }

    public function test_robot_layout_english_text_left_robot_right(): void
    {
        $response = $this->get('/locale/en');
        $response = $this->followRedirects($response);
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
        $response = $this->get('/locale/ku');
        $response = $this->followRedirects($response);
        $response->assertStatus(200);
        $content = $response->getContent();
        
        if ($content === false) {
            $this->fail('Response content is false');
        }
        
        // Kurdish should have robot first (order-1) and text second (order-2)
        $this->assertStringContainsString('lg:order-1', $content); // Robot
        $this->assertStringContainsString('lg:order-2', $content); // Text
        $this->assertStringContainsString('dir="rtl"', $content); // RTL direction
    }
}
