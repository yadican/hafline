<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: '/api/v1/me',
        operationId: 'profileShow',
        summary: 'Get the authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current user',
                content: new OA\JsonContent(ref: '#/components/schemas/UserResourceResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')
            ),
        ]
    )]
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
