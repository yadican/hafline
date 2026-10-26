<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FuelReceiptRequest;
use App\Http\Requests\Api\HaulScanRequest;
use App\Http\Resources\Api\FuelReceiptPrintResource;
use App\Models\FuelReceipt;
use App\Services\Api\FieldFuelReceiptService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use RuntimeException;

class FuelReceiptController extends Controller
{
    public function __construct(private FieldFuelReceiptService $fieldReceipts) {}

    #[OA\Get(
        path: '/api/v1/fuel-receipts/options',
        operationId: 'fuelReceiptsOptions',
        summary: 'Yakıt yeri listesi',
        description: 'Açık oturumdaki şantiyeye hizmet veren aktif yakıt yerleri (şantiye bağlanmamış istasyonlar her şantiyede görünür). `stations[].id` > `fuel_station_id`. `unit_price` istasyonun güncel fiyatıdır; forma ön değer olarak konur.',
        security: [['bearerAuth' => []]],
        tags: ['5. Yakıt fişi'],
        responses: [
            new OA\Response(response: 200, description: 'Yakıt yerleri', content: new OA\JsonContent(ref: '#/components/schemas/FuelReceiptOptionsResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Açık oturum yok', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function options(Request $request): JsonResponse
    {
        return response()->json($this->fieldReceipts->options($request->user()));
    }

    #[OA\Get(
        path: '/api/v1/fuel-receipts/vehicles',
        operationId: 'fuelReceiptsVehicles',
        summary: 'Araç plakası ara (elle giriş için)',
        description: 'Aktif araçlar; plakada geçen en az 2 karakter. En fazla 10 sonuç.',
        security: [['bearerAuth' => []]],
        tags: ['5. Yakıt fişi'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '34 AB')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Eşleşen araçlar (boş olabilir)', content: new OA\JsonContent(ref: '#/components/schemas/FuelVehicleSearchResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function vehicles(Request $request): JsonResponse
    {
        return response()->json([
            'vehicles' => $this->fieldReceipts->vehicles((string) $request->query('q', '')),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/fuel-receipts/check-plate',
        operationId: 'fuelReceiptsCheckPlate',
        summary: 'Elle girilen plakayı doğrula',
        description: 'Plaka kayıtlı bir araç mı ve bugün sahibi var mı kontrol eder. `can_create` false ise `message` sebebi söyler.',
        security: [['bearerAuth' => []]],
        tags: ['5. Yakıt fişi'],
        parameters: [
            new OA\Parameter(name: 'plate', in: 'query', required: true, schema: new OA\Schema(type: 'string', maxLength: 20, example: '34 AB 123')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Kontrol sonucu', content: new OA\JsonContent(ref: '#/components/schemas/FuelPlateCheck')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'plate eksik', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function checkPlate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plate' => ['required', 'string', 'max:20'],
        ]);

        return response()->json($this->fieldReceipts->checkPlate($validated['plate']));
    }

    #[OA\Post(
        path: '/api/v1/fuel-receipts/scan',
        operationId: 'fuelReceiptsScan',
        summary: 'Araç plakasını fotoğraftan oku (isteğe bağlı)',
        description: 'Fotoğraf saklanır ve OCR\'dan geçirilir; sonuç `check-plate` ile aynı kontrolleri içerir. Dönen `scan_id` 1 saat geçerlidir ve `POST /fuel-receipts` isteğine eklenirse fotoğraf fişe bağlanır. Dakikada en fazla 20 istek.',
        security: [['bearerAuth' => []]],
        tags: ['5. Yakıt fişi'],
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
            new OA\Response(response: 200, description: 'Okuma sonucu (plaka okunamasa da 200; success alanına bakın)', content: new OA\JsonContent(ref: '#/components/schemas/FuelPlateScanResult')),
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
                $this->fieldReceipts->scan(
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
        path: '/api/v1/fuel-receipts',
        operationId: 'fuelReceiptsStore',
        summary: 'Yakıt fişini kaydet',
        description: 'Şantiye ve vardiya oturumdan, tarih bugünden alınır; fiş numarası (`Y-` önekli), araç sahibi ve istasyonun firması sunucuda belirlenir. `unit_price` boşsa istasyonun güncel fiyatı kullanılır. Yakıt yeri oturumdaki şantiyeye bağlı olmalıdır. `scan_id` yoksa plaka elle girilmiş sayılır. Dakikada en fazla 30 istek.',
        security: [['bearerAuth' => []]],
        tags: ['5. Yakıt fişi'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/FuelReceiptStoreRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Fiş kaydedildi', content: new OA\JsonContent(ref: '#/components/schemas/FuelReceiptTicketResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Kayıtlı olmayan plaka, sahibi olmayan araç, şantiyeye bağlı olmayan yakıt yeri, eksik fiyat, süresi dolmuş scan_id veya kapalı oturum', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Dakikada 30 istek sınırı aşıldı', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(FuelReceiptRequest $request): JsonResponse
    {
        $payload = $this->rejectingOnPlate(fn (): array => $this->fieldReceipts->store($request->validated(), $request->user()));

        return (new FuelReceiptPrintResource($payload))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/v1/fuel-receipts/session',
        operationId: 'fuelReceiptsSession',
        summary: 'Açık oturumun bugünkü yakıt fişleri',
        description: 'Kullanıcının açık oturumundaki şantiye ve vardiyaya göre bugünkü yakıt fişleri; en yeni önce, en fazla 100.',
        security: [['bearerAuth' => []]],
        tags: ['5. Yakıt fişi'],
        responses: [
            new OA\Response(response: 200, description: 'Oturum ve fiş listesi', content: new OA\JsonContent(ref: '#/components/schemas/SessionFuelReceiptsResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Açık oturum yok', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function session(Request $request): JsonResponse
    {
        return response()->json(
            $this->fieldReceipts->sessionReceipts($request->user())
        );
    }

    #[OA\Get(
        path: '/api/v1/fuel-receipts/{fuelReceipt}',
        operationId: 'fuelReceiptsShow',
        summary: 'Yakıt fişini tekrar getir (yazdırma verisi)',
        description: 'Fişi kesen kullanıcı, açık oturumunun şantiye ve vardiyası fişinkiyle aynıyken görebilir; `yonetici` rolü her fişi görür.',
        security: [['bearerAuth' => []]],
        tags: ['5. Yakıt fişi'],
        parameters: [
            new OA\Parameter(name: 'fuelReceipt', in: 'path', required: true, description: 'POST /fuel-receipts > data.id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fiş yazdırma verisi', content: new OA\JsonContent(ref: '#/components/schemas/FuelReceiptTicketResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Fiş yok veya bu kullanıcı/oturum için görünmüyor', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, FuelReceipt $fuelReceipt): FuelReceiptPrintResource
    {
        return new FuelReceiptPrintResource($this->fieldReceipts->showFor($request->user(), $fuelReceipt));
    }

    #[OA\Put(
        path: '/api/v1/fuel-receipts/{fuelReceipt}',
        operationId: 'fuelReceiptsUpdate',
        summary: 'Yakıt fişini düzenle',
        description: 'Fiş numarası, tarih, şantiye ve vardiya değişmez. Plaka, yakıt yeri, litre ve fiyat kayıttaki kurallarla yeniden doğrulanır; cari kaydı güncellenir. Görme kuralı `GET /fuel-receipts/{fuelReceipt}` ile aynıdır.',
        security: [['bearerAuth' => []]],
        tags: ['5. Yakıt fişi'],
        parameters: [
            new OA\Parameter(name: 'fuelReceipt', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/FuelReceiptUpdateRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Fiş güncellendi', content: new OA\JsonContent(ref: '#/components/schemas/FuelReceiptTicketResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Fiş yok veya bu kullanıcı/oturum için görünmüyor', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Geçersiz alan', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(FuelReceiptRequest $request, FuelReceipt $fuelReceipt): FuelReceiptPrintResource
    {
        $payload = $this->rejectingOnPlate(fn (): array => $this->fieldReceipts->update($request->user(), $fuelReceipt, $request->validated()));

        return new FuelReceiptPrintResource($payload);
    }

    #[OA\Delete(
        path: '/api/v1/fuel-receipts/{fuelReceipt}',
        operationId: 'fuelReceiptsDestroy',
        summary: 'Yakıt fişini sil',
        description: 'Fiş ve cari kaydı geri alınır. Görme kuralı `GET /fuel-receipts/{fuelReceipt}` ile aynıdır.',
        security: [['bearerAuth' => []]],
        tags: ['5. Yakıt fişi'],
        parameters: [
            new OA\Parameter(name: 'fuelReceipt', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fiş silindi', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Fiş yok veya bu kullanıcı/oturum için görünmüyor', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Request $request, FuelReceipt $fuelReceipt): JsonResponse
    {
        $this->fieldReceipts->delete($request->user(), $fuelReceipt);

        return response()->json(['message' => 'Yakıt fişi silindi.']);
    }

    /**
     * @param  Closure(): array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function rejectingOnPlate(Closure $action): array
    {
        try {
            return $action();
        } catch (OperationException $exception) {
            throw ValidationException::withMessages(['plate' => $exception->getMessage()]);
        }
    }
}
