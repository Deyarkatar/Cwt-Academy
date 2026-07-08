<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear the default cache store (rate limiters, etc.) so tests are
        // isolated even when the test suite uses a persistent cache driver.
        Cache::flush();

        // Ensure a previously cached local/production config or route cache
        // cannot override the phpunit.xml environment for the test run.
        Artisan::call('config:clear');
        Artisan::call('route:clear');

        // Enable foreign key constraints for SQLite
        // (SQLite defaults to foreign keys OFF for backward compatibility)
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    /**
     * Return a real uploaded file fixture that passes content validation.
     *
     * Fixtures are small, valid JPEG/PNG/PDF files stored under tests/Fixtures.
     * Use this for any test that expects a payment proof upload to succeed.
     *
     * @param  int|null  $sizeKb  Optional target size in KB. If provided, the
     *                            fixture content is padded to reach this size.
     *                            Used for max-size validation tests; the file
     *                            will still have a valid MIME signature.
     */
    protected function paymentProofFile(string $mime = 'image/jpeg', string $name = 'receipt.jpg', ?int $sizeKb = null): UploadedFile
    {
        $fixture = match ($mime) {
            'image/jpeg' => 'receipt.jpg',
            'image/png' => 'receipt.png',
            'image/webp' => 'receipt.webp',
            'application/pdf' => 'receipt.pdf',
            default => 'receipt.jpg',
        };

        $content = file_get_contents(__DIR__.'/Fixtures/'.$fixture);

        if ($content === false || $content === '') {
            throw new \RuntimeException('Payment proof fixture not found or empty: '.$fixture);
        }

        if ($sizeKb !== null && $sizeKb > 0) {
            $targetBytes = $sizeKb * 1024;
            if (strlen($content) < $targetBytes) {
                $content .= str_repeat("\0", $targetBytes - strlen($content));
            }
        }

        return File::createWithContent($name, $content);
    }
}
