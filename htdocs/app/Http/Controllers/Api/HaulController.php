<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\HaulScanRequest;
use App\Http\Requests\Api\HaulStoreRequest;
use App\Http\Resources\Api\HaulPrintResource;
use App\Models\Haul;
use App\Services\Api\FieldHaulService;
use App\Services\Api\SecureImageIngest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HaulController extends Controller
{
    public function __construct(
        private FieldHaulService $fieldHauls,
        private SecureImageIngest $images,
    ) {}

    #[OA\Get(
        path: '/api/v1/hauls/options',
        operationId: 'haulsOptions',
        summary: 'Adım 2 · Şantiye ve vardiya listesi',
        description: 'Oturum açma ekranındaki seçimler için. `sites[].id` > `site_id`, `shifts[].value` > `shift`.',
        security: [['bearerAuth' => []]],
        tags: ['2. Saha oturumu'],
        responses: [
            new OA\Response(response: 200, description: 'Listeler', content: new OA\JsonContent(ref: '#/components/schemas/HaulOptionsResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function options(): JsonResponse
    {
        return response()->json($this->fieldHauls->options());
    }

    #[OA\Get(
        path: '/api/v1/hauls/dump-sites',
        operationId: 'haulsDumpSites',
        summary: 'Adım 3 · Şantiyenin döküm yerleri',
        description: 'Sadece seçilen şantiyeyle aktif güzergahı olan döküm yerleri döner. `dump_sites[].id` > `dump_site_id`.',
        security: [['bearerAuth' => []]],
        tags: ['2. Saha oturumu'],
        parameters: [
            new OA\Parameter(name: 'site_id', in: 'query', required: true, description: 'GET /hauls/options > sites[].id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Döküm yeri listesi (boş olabilir)', content: new OA\JsonContent(ref: '#/components/schemas/DumpSitesResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'site_id eksik veya UUID değil', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function dumpSites(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'uuid'],
        ]);

        return response()->json([
            'dump_sites' => $this->fieldHauls->dumpSites($validated['site_id']),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/hauls/scan',
        operationId: 'haulsScan',
        summary: 'Adım 5 · Plaka fotoğrafını oku (scan_id üretir)',
        description: 'Fotoğraf saklanır ve OCR\'dan geçirilir. Okunan plaka kayıtlı araçlarla eşleştirilir. `can_create` true ise okunan plakayla doğrudan fiş kesilebilir; false ise kullanıcı plakayı düzeltip `POST /hauls` ile gönderir. Dönen `scan_id` 1 saat geçerlidir. Dakikada en fazla 20 istek.',
        security: [['bearerAuth' => []]],
        tags: ['3. Plaka ve fiş'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        required: ['file', 'field_session_id'],
                        properties: [
                            new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'JPEG, PNG, WebP veya GIF; en fazla 15 MB ve 4096 px'),
                            new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid', description: 'POST /field-sessions > data.id'),
                        ]
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 200, description: 'Okuma sonucu (plaka okunamasa da 200; success alanına bakın)', content: new OA\JsonContent(ref: '#/components/schemas/PlateScanResult')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Geçersiz görsel veya açık olmayan oturum', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Dakikada 20 istek sınırı aşıldı', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 502, description: 'OCR servisine ulaşılamadı (tarama kaydı oluşmaz)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function scan(HaulScanRequest $request): JsonResponse
    {
        try {
            return response()->json(
                $this->fieldHauls->scan(
                    $request->file('file'),
                    $request->string('field_session_id')->value(),
                    $request->user(),
                )
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }

    #[OA\Post(
        path: '/api/v1/hauls',
        operationId: 'haulsStore',
        summary: 'Adım 6 · Fişi kaydet',
        description: 'Şantiye, döküm yeri ve vardiya oturumdan alınır; fiş numarası, tarih, fiyatlar ve araç sahibi sunucuda hesaplanır. Cevap fiş yazdırma verisidir. Dakikada en fazla 30 istek.',
        security: [['bearerAuth' => []]],
        tags: ['3. Plaka ve fiş'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/HaulStoreRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Fiş kaydedildi', content: new OA\JsonContent(ref: '#/components/schemas/HaulTicketResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Kayıtlı olmayan plaka, aracın bugün sahibi yok, süresi dolmuş scan_id veya kapalı oturum', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Dakikada 30 istek sınırı aşıldı', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(HaulStoreRequest $request): JsonResponse
    {
        try {
            $payload = $this->fieldHauls->store($request->validated(), $request->user());
        } catch (OperationRejectedException $exception) {
            throw ValidationException::withMessages(['plate' => $exception->getMessage()]);
        } catch (OperationException $exception) {
            throw ValidationException::withMessages(['plate' => $exception->getMessage()]);
        }

        return (new HaulPrintResource($payload))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/v1/hauls/session',
        operationId: 'haulsSession',
        summary: 'Açık oturumun bugünkü fişleri',
        description: 'Kullanıcının açık oturumundaki şantiye, döküm yeri ve vardiyaya göre bugünkü fişler; en yeni önce, en fazla 100.',
        security: [['bearerAuth' => []]],
        tags: ['3. Plaka ve fiş'],
        responses: [
            new OA\Response(response: 200, description: 'Oturum ve fiş listesi', content: new OA\JsonContent(ref: '#/components/schemas/SessionHaulsResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Açık oturum yok', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function session(Request $request): JsonResponse
    {
        return response()->json(
            $this->fieldHauls->sessionHauls($request->user())
        );
    }

    #[OA\Get(
        path: '/api/v1/hauls/{haul}',
        operationId: 'haulsShow',
        summary: 'Adım 7 · Fişi tekrar getir (yazdırma verisi)',
        description: 'Fişi kesen kullanıcı, açık oturumunun şantiye, döküm yeri ve vardiyası fişinkiyle aynıyken görebilir; `yonetici` rolü her fişi görür.',
        security: [['bearerAuth' => []]],
        tags: ['3. Plaka ve fiş'],
        parameters: [
            new OA\Parameter(name: 'haul', in: 'path', required: true, description: 'POST /hauls > data.id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fiş yazdırma verisi', content: new OA\JsonContent(ref: '#/components/schemas/HaulTicketResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Fiş yok veya bu kullanıcı/oturum için görünmüyor', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, Haul $haul): HaulPrintResource
    {
        return new HaulPrintResource($this->fieldHauls->showFor($request->user(), $haul));
    }

    #[OA\Get(
        path: '/api/v1/hauls/{haul}/photo',
        operationId: 'haulsPhoto',
        summary: 'Fişin plaka fotoğrafı (imzalı adres)',
        description: 'Token gerekmez; adres fiş verisindeki `photo_url` alanından olduğu gibi kullanılır. İmza ve süre adresin içindedir.',
        tags: ['3. Plaka ve fiş'],
        parameters: [
            new OA\Parameter(name: 'haul', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Görsel', content: new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 403, description: 'İmza geçersiz veya süresi dolmuş'),
            new OA\Response(response: 404, description: 'Fotoğraf yok'),
        ]
    )]
    public function photo(Haul $haul): BinaryFileResponse
    {
        $path = $this->fieldHauls->photoPathFor($haul);

        return response()->file($path, [
            'Content-Type' => $this->images->mimeForPath($path),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
