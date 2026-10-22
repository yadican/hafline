<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: '/api/v1/dashboard',
        operationId: 'dashboardShow',
        summary: 'Get dashboard summary for the authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard'],
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
            'counts' => [
                'sites' => 0,
                'vehicles' => 0,
                'hauls' => 0,
            ],
        ]);
    }
}
