<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `php artisan serve` routes every request through the project's server.php.
 * Static files must carry caching headers (the bare built-in server sends none,
 * so phones re-download everything on each full page load) and JS/CSS should
 * be compressed. Runs a real `php -S` with that router; no database involved.
 */
class DevServerTest extends TestCase
{
    private $process;
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $port = 18000 + random_int(0, 999);
        $this->base = "http://127.0.0.1:{$port}";
        $this->process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", base_path('server.php')],
            [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']],
            $pipes,
            public_path(),
        );

        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', $port)) {
                return;
            }
            usleep(100_000);
        }
        $this->fail('The test server did not start.');
    }

    protected function tearDown(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
        parent::tearDown();
    }

    private function buildAsset(string $extension): string
    {
        $file = collect(File::glob(public_path("build/assets/*.{$extension}")))->first();
        $this->assertNotNull($file, 'Run `npm run build` first.');

        return '/build/assets/' . basename($file);
    }

    public function test_build_files_are_cached_for_a_year_and_compressed(): void
    {
        // decode_content off: otherwise the client unzips and drops the Content-Encoding header.
        $response = Http::withOptions(['decode_content' => false])
            ->withHeaders(['Accept-Encoding' => 'gzip'])->get($this->base . $this->buildAsset('js'));

        $this->assertSame(200, $response->status());
        $this->assertSame('public, max-age=31536000, immutable', $response->header('Cache-Control'));
        $this->assertStringContainsString('javascript', $response->header('Content-Type'));
        $this->assertSame('gzip', $response->header('Content-Encoding'));
    }

    public function test_photos_are_cached_and_revalidated_cheaply(): void
    {
        $first = Http::get($this->base . '/candidates/female/1.webp');

        $this->assertSame(200, $first->status());
        $this->assertSame('image/webp', $first->header('Content-Type'));
        $this->assertSame('public, max-age=86400', $first->header('Cache-Control'));
        $this->assertSame(filesize(public_path('candidates/female/1.webp')), strlen($first->body()));
        $this->assertNotEmpty($first->header('ETag'));

        $again = Http::withHeaders(['If-None-Match' => $first->header('ETag')])->get($this->base . '/candidates/female/1.webp');
        $this->assertSame(304, $again->status());
        $this->assertSame('', $again->body());
    }

    /** Uploaded photos get a fresh random name when replaced, so phones keep them for good. */
    public function test_uploaded_photos_are_cached_for_a_year(): void
    {
        $dir = public_path('uploads/candidates/devserver-test');
        File::ensureDirectoryExists($dir);
        File::copy(public_path('candidates/female/1-thumb.webp'), "{$dir}/photo-thumb.webp");

        try {
            $response = Http::get($this->base . '/uploads/candidates/devserver-test/photo-thumb.webp');

            $this->assertSame(200, $response->status());
            $this->assertSame('public, max-age=31536000, immutable', $response->header('Cache-Control'));
            $this->assertSame(filesize("{$dir}/photo-thumb.webp"), strlen($response->body()));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_app_pages_still_go_through_laravel(): void
    {
        $response = Http::get($this->base . '/login');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('data-page', $response->body());
    }
}
