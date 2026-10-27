<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\HaulScanRequest;
use App\Http\Requests\Api\MachineWorkRequest;
use App\Http\Resources\Api\MachineWorkPrintResource;
use App\Models\MachineWork;
use App\Services\Api\FieldMachineWorkService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use RuntimeException;

class MachineWorkController extends Controller
{
    public function __construct(private FieldMachineWorkService $fieldWorks) {}

    #[OA\Get(
        path: '/api/v1/machine-works/options',
        operationId: 'machineWorksOptions',
        summary: 'Operatör listesi',
        description: '`operators[].id` > `operator_id`. `salaried` true ise operatör personeldir, `operator_rate` gönderilmez. Değilse `last_rate` operatörün son fişindeki ücrettir; forma ön değer olarak konur.',
        security: [['bearerAuth' => []]],
        tags: ['4. Makine fişi'],
        responses: [
            new OA\Response(response: 200, description: 'Operatörler', content: new OA\JsonContent(ref: '#/components/schemas/MachineWorkOptionsResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function options(): JsonResponse
    {
        return response()->json($this->fieldWorks->options());
    }

    #[OA\Get(
        path: '/api/v1/machine-works/machines',
        operationId: 'machineWorksMachines',
        summary: 'Makine plakası ara (elle giriş için)',
        description: 'Saatlik çalışan aktif makineler; plakada geçen en az 2 karakter. En fazla 10 sonuç.',
        security: [['bearerAuth' => []]],
        tags: ['4. Makine fişi'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '34 MK')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Eşleşen makineler (boş olabilir)', content: new OA\JsonContent(ref: '#/components/schemas/MachineSearchResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function machines(Request $request): JsonResponse
    {
        return response()->json([
            'machines' => $this->fieldWorks->machines((string) $request->query('q', '')),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/machine-works/check-plate',
        operationId: 'machineWorksCheckPlate',
        summary: 'Elle girilen plakayı doğrula',
        description: 'Plaka kayıtlı, saatlik çalışan bir makine mi ve bugün sahibi var mı kontrol eder. `can_create` false ise `message` sebebi söyler. `rate_required` true ise makinenin bugün geçerli saat ücreti yoktur; fişte `hourly_rate` girilmelidir.',
        security: [['bearerAuth' => []]],
        tags: ['4. Makine fişi'],
        parameters: [
            new OA\Parameter(name: 'plate', in: 'query', required: true, schema: new OA\Schema(type: 'string', maxLength: 20, example: '34 MK 100')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Kontrol sonucu', content: new OA\JsonContent(ref: '#/components/schemas/MachinePlateCheck')),
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

        return response()->json($this->fieldWorks->checkPlate($validated['plate']));
    }

    #[OA\Post(
        path: '/api/v1/machine-works/scan',
        operationId: 'machineWorksScan',
        summary: 'Makine plakasını fotoğraftan oku (isteğe bağlı)',
        description: 'Fotoğraf saklanır ve OCR\'dan geçirilir; sonuç `check-plate` ile aynı kontrolleri içerir. Dönen `scan_id` 1 saat geçerlidir ve `POST /machine-works` isteğine eklenirse fotoğraf fişe bağlanır. Dakikada en fazla 20 istek.',
        security: [['bearerAuth' => []]],
        tags: ['4. Makine fişi'],
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
            new OA\Response(response: 200, description: 'Okuma sonucu (plaka okunamasa da 200; success alanına bakın)', content: new OA\JsonContent(ref: '#/components/schemas/MachinePlateScanResult')),
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
                $this->fieldWorks->scan(
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
        path: '/api/v1/machine-works',
        operationId: 'machineWorksStore',
        summary: 'Makine çalışma fişini kaydet',
        description: 'Şantiye ve vardiya oturumdan, tarih bugünden alınır; fiş numarası (`M-` önekli) ve makine sahibi sunucuda belirlenir. `hourly_rate` boşsa makinenin bugün geçerli saat ücreti kullanılır. Personel olmayan operatör seçildiyse `operator_rate` zorunludur. `scan_id` yoksa plaka elle girilmiş sayılır. Dakikada en fazla 30 istek.',
        security: [['bearerAuth' => []]],
        tags: ['4. Makine fişi'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/MachineWorkStoreRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Fiş kaydedildi', content: new OA\JsonContent(ref: '#/components/schemas/MachineWorkTicketResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Kayıtlı/saatlik olmayan plaka, sahibi olmayan makine, eksik ücret, süresi dolmuş scan_id veya kapalı oturum', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Dakikada 30 istek sınırı aşıldı', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(MachineWorkRequest $request): JsonResponse
    {
        $payload = $this->rejectingOnPlate(fn (): array => $this->fieldWorks->store($request->validated(), $request->user()));

        return (new MachineWorkPrintResource($payload))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/v1/machine-works/session',
        operationId: 'machineWorksSession',
        summary: 'Açık oturumun bugünkü makine fişleri',
        description: 'Kullanıcının açık oturumundaki şantiye ve vardiyaya göre bugünkü makine fişleri; en yeni önce, en fazla 100.',
        security: [['bearerAuth' => []]],
        tags: ['4. Makine fişi'],
        responses: [
            new OA\Response(response: 200, description: 'Oturum ve fiş listesi', content: new OA\JsonContent(ref: '#/components/schemas/SessionMachineWorksResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Açık oturum yok', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function session(Request $request): JsonResponse
    {
        return response()->json(
            $this->fieldWorks->sessionWorks($request->user())
        );
    }

    #[OA\Get(
        path: '/api/v1/machine-works/{machineWork}',
        operationId: 'machineWorksShow',
        summary: 'Makine fişini tekrar getir (yazdırma verisi)',
        description: 'Fişi kesen kullanıcı, açık oturumunun şantiye ve vardiyası fişinkiyle aynıyken görebilir; `yonetici` rolü her fişi görür.',
        security: [['bearerAuth' => []]],
        tags: ['4. Makine fişi'],
        parameters: [
            new OA\Parameter(name: 'machineWork', in: 'path', required: true, description: 'POST /machine-works > data.id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fiş yazdırma verisi', content: new OA\JsonContent(ref: '#/components/schemas/MachineWorkTicketResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Fiş yok veya bu kullanıcı/oturum için görünmüyor', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, MachineWork $machineWork): MachineWorkPrintResource
    {
        return new MachineWorkPrintResource($this->fieldWorks->showFor($request->user(), $machineWork));
    }

    #[OA\Put(
        path: '/api/v1/machine-works/{machineWork}',
        operationId: 'machineWorksUpdate',
        summary: 'Makine fişini düzenle',
        description: 'Fiş numarası, tarih, şantiye ve vardiya değişmez. Plaka, saat, ücret ve operatör kayıttaki kurallarla yeniden doğrulanır; cari kayıtları güncellenir. Görme kuralı `GET /machine-works/{machineWork}` ile aynıdır.',
        security: [['bearerAuth' => []]],
        tags: ['4. Makine fişi'],
        parameters: [
            new OA\Parameter(name: 'machineWork', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/MachineWorkUpdateRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Fiş güncellendi', content: new OA\JsonContent(ref: '#/components/schemas/MachineWorkTicketResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Fiş yok veya bu kullanıcı/oturum için görünmüyor', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Geçersiz alan', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(MachineWorkRequest $request, MachineWork $machineWork): MachineWorkPrintResource
    {
        $payload = $this->rejectingOnPlate(fn (): array => $this->fieldWorks->update($request->user(), $machineWork, $request->validated()));

        return new MachineWorkPrintResource($payload);
    }

    #[OA\Delete(
        path: '/api/v1/machine-works/{machineWork}',
        operationId: 'machineWorksDestroy',
        summary: 'Makine fişini sil',
        description: 'Fiş ve cari kayıtları geri alınır. Görme kuralı `GET /machine-works/{machineWork}` ile aynıdır.',
        security: [['bearerAuth' => []]],
        tags: ['4. Makine fişi'],
        parameters: [
            new OA\Parameter(name: 'machineWork', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fiş silindi', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Fiş yok veya bu kullanıcı/oturum için görünmüyor', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Request $request, MachineWork $machineWork): JsonResponse
    {
        $this->fieldWorks->delete($request->user(), $machineWork);

        return response()->json(['message' => 'Makine çalışma fişi silindi.']);
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
