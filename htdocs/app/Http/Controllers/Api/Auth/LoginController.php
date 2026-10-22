<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class LoginController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/login',
        operationId: 'authLogin',
        summary: 'Login and receive a JWT',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/LoginResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = User::credentials($request->string('login')->value(), $request->string('password')->value());
        $provider = Auth::guard('api')->getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            throw ValidationException::withMessages([
                'login' => 'Bu bilgilerle giriş yapılamadı.',
            ]);
        }

        $token = Auth::guard('api')->login($user);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        operationId: 'authLogout',
        summary: 'Invalidate the current JWT',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out',
                content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedError')
            ),
        ]
    )]
    public function destroy(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json([
            'message' => 'Çıkış yapıldı.',
        ]);
    }
}
