<?php

namespace App\Services\Api\Ocr;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PlateScanClient
{
    /**
     * @return array{success: bool, plate: ?string, confidence: ?float, matched_regex: bool, message: ?string}
     */
    public function scan(string $absolutePath): array
    {
        $url = (string) config('services.ocr.scan_url');

        if ($url === '') {
            throw new RuntimeException('OCR_SCAN_URL yapılandırılmamış.');
        }

        try {
            $response = Http::timeout((int) config('services.ocr.timeout', 30))
                ->attach(
                    'file',
                    file_get_contents($absolutePath),
                    basename($absolutePath),
                    ['Content-Type' => str_ends_with($absolutePath, '.png') ? 'image/png' : 'image/jpeg'],
                )
                ->post($url);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Plaka servisine bağlanılamadı.', 0, $exception);
        }

        if ($response->serverError() || $response->failed()) {
            throw new RuntimeException('Plaka servisi yanıt vermiyor.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        $plate = isset($payload['plate']) ? (string) $payload['plate'] : null;
        $success = (bool) ($payload['success'] ?? ($plate !== null && $plate !== ''));

        return [
            'success' => $success && $plate !== null && $plate !== '',
            'plate' => $plate !== '' ? mb_strtoupper((string) $plate, 'UTF-8') : null,
            'confidence' => isset($payload['confidence']) ? (float) $payload['confidence'] : null,
            'matched_regex' => (bool) ($payload['matched_regex'] ?? false),
            'message' => $success ? null : (string) ($payload['message'] ?? 'Plaka okunamadı.'),
        ];
    }
}
