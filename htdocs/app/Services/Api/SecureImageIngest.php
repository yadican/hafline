<?php

namespace App\Services\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SecureImageIngest
{
    public const DISK = 'data';

    /** Phone field photos before server-side downscale (must stay under PHP upload_max_filesize). */
    public const MAX_BYTES = 15_728_640;

    /** Reject absurd uploads before decode. */
    public const MAX_EDGE = 4096;

    /** Longest side after resize — enough for plate review, typically lands ~100–200 KB. */
    public const STORE_EDGE = 1024;

    public const JPEG_QUALITY = 72;

    /**
     * @var list<string>
     */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * Security checks first (nothing written on failure), then downscale,
     * re-encode (EXIF stripped), and store as field_session_id-timestamp.
     *
     * @return array{path: string, checksum: string, absolute_path: string, mime: string}
     */
    public function ingest(UploadedFile $file, string $fieldSessionId): array
    {
        [$encoded, $extension, $mime] = $this->process($file, self::STORE_EDGE);

        $checksum = hash('sha256', $encoded);
        $path = $this->datedPath(
            'haul-scans',
            $this->uniqueFilename($fieldSessionId, $extension),
        );

        Storage::disk(self::DISK)->put($path, $encoded);

        return [
            'path' => $path,
            'checksum' => $checksum,
            'absolute_path' => Storage::disk(self::DISK)->path($path),
            'mime' => $mime,
        ];
    }

    /**
     * Same checks and clean re-encode as field photos, kept larger so the writing on a paper
     * document stays readable; stored as {folder}/Y/m/d/H/{name}.jpg.
     */
    public function storeDocument(UploadedFile $file, string $folder, string $name, int $edge = 1600): string
    {
        [$encoded, $extension] = $this->process($file, $edge);
        $path = $this->datedPath($folder, $name.'.'.$extension);

        Storage::disk(self::DISK)->put($path, $encoded);

        return $path;
    }

    /**
     * Security checks first (nothing written on failure), then downscale and re-encode.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function process(UploadedFile $file, int $edge): array
    {
        $this->assertAcceptable($file);

        $binary = file_get_contents($file->getRealPath());

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages(['file' => 'Geçersiz görüntü.']);
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            throw ValidationException::withMessages(['file' => 'Geçersiz görüntü.']);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < 1 || $height < 1 || $width > self::MAX_EDGE || $height > self::MAX_EDGE) {
            imagedestroy($image);

            throw ValidationException::withMessages(['file' => 'Görüntü boyutu kabul edilmiyor.']);
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, true);
        imagesavealpha($image, false);

        $image = $this->downscale($image, $width, $height, $edge);

        [$encoded, $extension, $mime] = $this->encode($image);
        imagedestroy($image);

        if ($encoded === null || $encoded === '') {
            throw ValidationException::withMessages(['file' => 'Görüntü işlenemedi.']);
        }

        if (strlen($encoded) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Görüntü çok büyük.']);
        }

        return [$encoded, $extension, $mime];
    }

    public function absolutePath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

    /**
     * Browser-openable URL under /media/{path} (project data disk).
     */
    public function url(string $path): string
    {
        return url('/media/'.ltrim(str_replace('\\', '/', $path), '/'));
    }

    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }

    public function moveAttached(string $fromPath, string $ticketId, string $folder = 'haul-photos'): string
    {
        $extension = pathinfo($fromPath, PATHINFO_EXTENSION) ?: 'jpg';
        $toPath = $this->datedPath($folder, $ticketId.'.'.$extension);

        if ($fromPath !== $toPath) {
            Storage::disk(self::DISK)->move($fromPath, $toPath);
        }

        return $toPath;
    }

    public function mimeForPath(string $path): string
    {
        return match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * haul-scans/2026/10/22/23/{field_session_id}-{YmdHisv}.jpg
     * haul-photos/2026/10/22/23/{haul_id}.jpg
     * machine-photos/2026/10/22/23/{machine_work_id}.jpg
     * fuel-photos/2026/10/22/23/{fuel_receipt_id}.jpg
     */
    private function datedPath(string $prefix, string $filename, ?Carbon $at = null): string
    {
        $at ??= now();

        return sprintf(
            '%s/%s/%s/%s/%s/%s',
            $prefix,
            $at->format('Y'),
            $at->format('m'),
            $at->format('d'),
            $at->format('H'),
            $filename,
        );
    }

    /**
     * field_session_id-20261022235600123.jpg (ms precision; rare collision → -1, -2…)
     */
    private function uniqueFilename(string $fieldSessionId, string $extension, ?Carbon $at = null): string
    {
        $at ??= now();
        $stamp = $at->format('YmdHisv');
        $base = $fieldSessionId.'-'.$stamp;
        $filename = $base.'.'.$extension;
        $attempt = 0;

        while (Storage::disk(self::DISK)->exists($this->datedPath('haul-scans', $filename, $at))) {
            $attempt++;
            $filename = $base.'-'.$attempt.'.'.$extension;
        }

        return $filename;
    }

    private function downscale(\GdImage $image, int $width, int $height, int $edge): \GdImage
    {
        $longest = max($width, $height);

        if ($longest <= $edge) {
            return $image;
        }

        $scale = $edge / $longest;
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($targetW, $targetH);

        if ($resized === false) {
            return $image;
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /**
     * @return array{0: string|null, 1: string, 2: string}
     */
    private function encode(\GdImage $image): array
    {
        ob_start();

        if (function_exists('imagejpeg')) {
            imagejpeg($image, null, self::JPEG_QUALITY);
            $binary = ob_get_clean();

            return [$binary === false ? null : $binary, 'jpg', 'image/jpeg'];
        }

        imagepng($image, null, 8);
        $binary = ob_get_clean();

        return [$binary === false ? null : $binary, 'png', 'image/png'];
    }

    public function assertAcceptable(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'Dosya yüklenemedi.']);
        }

        if ($file->getSize() === false || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'Dosya boyutu '.(int) (self::MAX_BYTES / 1024 / 1024).' MB ile sınırlıdır.',
            ]);
        }

        $mime = $file->getMimeType() ?: '';

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['file' => 'Yalnızca JPEG, PNG, WebP veya GIF kabul edilir.']);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $sniffed = $finfo->file($file->getRealPath()) ?: '';

        if (! in_array($sniffed, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['file' => 'Geçersiz görüntü türü.']);
        }

        $info = @getimagesize($file->getRealPath());

        if ($info === false) {
            throw ValidationException::withMessages(['file' => 'Geçersiz görüntü.']);
        }
    }
}
