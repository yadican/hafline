<?php

namespace App\Http\Controllers;

use App\Services\Api\SecureImageIngest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function __construct(private SecureImageIngest $images) {}

    public function show(string $path): BinaryFileResponse
    {
        $path = str_replace('\\', '/', $path);

        abort_if(str_contains($path, '..'), 404);
        abort_unless(preg_match('#^(haul-photos|haul-scans|machine-photos)/#', $path) === 1, 404);
        abort_unless(Storage::disk(SecureImageIngest::DISK)->exists($path), 404);

        $absolute = Storage::disk(SecureImageIngest::DISK)->path($path);

        return response()->file($absolute, [
            'Content-Type' => $this->images->mimeForPath($path),
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
