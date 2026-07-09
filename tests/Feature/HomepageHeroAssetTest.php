<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomepageHeroAssetTest extends TestCase
{
    /**
     * The current hero uses a Spline 3D scene (real glossy black robot) rather
     * than a static image file. Therefore there is no /images/*.jpg/png robot
     * asset under public/. These tests guard the actual robot assets used by
     * the hero component and ensure the old fake SVG cannot return.
     */
    private const SPLINE_SCENE_URL = 'https://prod.spline.design/kZDDjO5HuC9GJUM2/scene.splinecode';

    private function findBuiltAsset(string $pattern): string
    {
        $files = glob(public_path('build/assets/'.$pattern));
        $this->assertIsArray($files, "Built asset scan failed for {$pattern}.");
        $this->assertNotEmpty($files, "Built asset matching {$pattern} not found in public/build/assets.");

        return $files[0];
    }

    public function test_deleted_fake_svg_robot_is_not_accessible(): void
    {
        $this->assertFileDoesNotExist(public_path('images/hero-robot.svg'));

        $this->get('/images/hero-robot.svg')->assertStatus(404);
    }

    public function test_main_css_build_asset_exists_and_is_nonempty(): void
    {
        $path = $this->findBuiltAsset('app-*.css');

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.css', $path);
        $this->assertNotEmpty(file_get_contents($path));
    }

    public function test_spline_app_build_asset_exists_and_is_nonempty(): void
    {
        $path = $this->findBuiltAsset('spline-app-*.js');

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.js', $path);
        $this->assertNotEmpty(file_get_contents($path));
    }

    public function test_spline_demo_build_asset_exists_contains_scene_url(): void
    {
        $path = $this->findBuiltAsset('spline-demo-*.js');

        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertIsString($content);
        $this->assertNotEmpty($content);
        $this->assertStringContainsString(self::SPLINE_SCENE_URL, $content, 'Built Spline demo must reference the real robot scene URL.');
    }

    public function test_spline_scene_url_is_reachable(): void
    {
        $httpResponse = Http::get(self::SPLINE_SCENE_URL);

        $this->assertTrue(
            $httpResponse->successful(),
            'The Spline scene URL must be reachable so the real robot can load.'
        );
        $this->assertNotEmpty($httpResponse->body(), 'Spline scene body must not be empty.');
    }
}
