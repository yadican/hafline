<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Services\Api\FieldDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    public function __construct(private FieldDashboardService $dashboard) {}

    #[OA\Get(
        path: '/api/v1/dashboard',
        operationId: 'dashboardShow',
        summary: 'Saha kullanıcısının kendi fiş özeti',
        description: 'Giriş yapan kullanıcının kendi kestiği taşıma, makine ve yakıt fişlerinin bugün / dün / bu ay sayıları, bugün girdiği son 10 fiş ve açık saha oturumu. Kasa verisi içermez.',
        security: [['bearerAuth' => []]],
        tags: ['Profil'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dashboard payload',
                content: new OA\JsonContent(ref: '#/components/schemas/DashboardResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')
            ),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => (new UserResource($request->user()))->resolve(),
            ...$this->dashboard->summary($request->user()),
        ]);
    }
}
