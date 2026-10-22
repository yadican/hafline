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
        summary: 'Lock site, dump site and shift for the field operator session',
        description: 'Pick values from GET /hauls/options (sites, shifts) and GET /hauls/dump-sites?site_id=… then POST them here to lock the operator session on the server.',
        security: [['bearerAuth' => []]],
        tags: ['FieldSessions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['site_id', 'dump_site_id', 'shift'],
                properties: [
                    new OA\Property(property: 'site_id', type: 'string', format: 'uuid', example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'),
                    new OA\Property(property: 'dump_site_id', type: 'string', format: 'uuid', example: 'b2c3d4e5-f6a7-8901-bcde-f12345678901'),
                    new OA\Property(property: 'shift', type: 'string', enum: ['day', 'night'], example: 'day'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Session locked'),
            new OA\Response(response: 422, description: 'Validation failed'),
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
        summary: 'Get the operator active field session',
        security: [['bearerAuth' => []]],
        tags: ['FieldSessions'],
        responses: [
            new OA\Response(response: 200, description: 'Active session or null data'),
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
        summary: 'Close the active field session',
        security: [['bearerAuth' => []]],
        tags: ['FieldSessions'],
        responses: [
            new OA\Response(response: 200, description: 'Session closed'),
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        $this->fieldHauls->closeSession($request->user());

        return response()->json(['message' => 'Oturum kapatıldı.']);
    }
}
