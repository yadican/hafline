<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FieldHaulSessionRequest;
use App\Services\Api\FieldHaulService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FieldHaulSessionController extends Controller
{
    public function __construct(private FieldHaulService $fieldHauls) {}

    #[OA\Post(
        path: '/api/v1/field-sessions',
        operationId: 'fieldSessionsLock',
        summary: 'Adım 4 · Saha oturumunu aç (field_session_id üretir)',
        description: 'Şantiye ve vardiyayı, verilirse döküm yerini de kilitler. Döküm yeri sadece taşıma fişi için zorunludur; makine ve yakıt fişi döküm yeri olmadan kesilebilir. Döküm yerini sonradan seçmek veya değiştirmek için aynı şantiye ve vardiyayla tekrar çağrılır. Kullanıcının açık oturumu varsa kapatılıp yenisi açılır. Dönen `data.id`, fiş isteklerinde `field_session_id` olarak gönderilir.',
        security: [['bearerAuth' => []]],
        tags: ['2. Saha oturumu'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/FieldSessionRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Oturum açıldı', content: new OA\JsonContent(ref: '#/components/schemas/FieldSessionResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Eksik/geçersiz alan veya döküm yeri verildiyse şantiye ile arasında aktif güzergah yok', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(FieldHaulSessionRequest $request): JsonResponse
    {
        $payload = $this->fieldHauls->lockSession($request->validated(), $request->user());

        return response()->json(['data' => $payload], 201);
    }

    #[OA\Get(
        path: '/api/v1/field-sessions/current',
        operationId: 'fieldSessionsCurrent',
        summary: 'Açık oturumu getir',
        description: 'Uygulama açılışında çağrılır. Açık oturum yoksa `data: null` döner.',
        security: [['bearerAuth' => []]],
        tags: ['2. Saha oturumu'],
        responses: [
            new OA\Response(response: 200, description: 'Açık oturum veya null', content: new OA\JsonContent(ref: '#/components/schemas/CurrentFieldSessionResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function current(Request $request): JsonResponse
    {
        $payload = $this->fieldHauls->currentSession($request->user());

        return response()->json(['data' => $payload]);
    }

    #[OA\Delete(
        path: '/api/v1/field-sessions/current',
        operationId: 'fieldSessionsClose',
        summary: 'Açık oturumu kapat',
        security: [['bearerAuth' => []]],
        tags: ['2. Saha oturumu'],
        responses: [
            new OA\Response(response: 200, description: 'Oturum kapatıldı (açık oturum yoksa da 200)', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Token yok veya süresi dolmuş', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')),
            new OA\Response(response: 403, description: 'field-haul.operate yetkisi yok', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        $this->fieldHauls->closeSession($request->user());

        return response()->json(['message' => 'Oturum kapatıldı.']);
    }
}
