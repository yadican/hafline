<?php

namespace Tests\Feature;

use App\Services\Api\SecureImageIngest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaServeTest extends TestCase
{
    use RefreshDatabase;

    public function test_haul_photo_is_served_under_media_path(): void
    {
        Storage::fake('data');

        $path = 'haul-photos/2026/10/23/01/example.jpg';
        Storage::disk('data')->put($path, 'fake-image-bytes');

        $url = app(SecureImageIngest::class)->url($path);

        $this->assertStringContainsString('/media/haul-photos/', $url);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_path_traversal_and_unknown_prefixes_are_rejected(): void
    {
        Storage::fake('data');

        $this->get('/media/../.env')->assertNotFound();
        $this->get('/media/secrets/file.txt')->assertNotFound();
    }
}
