<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Hafline API',
    description: 'Hafline v1 HTTP API (JWT Bearer token).'
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Application server'
)]
#[OA\Tag(name: 'Auth', description: 'Authentication')]
#[OA\Tag(name: 'Profile', description: 'Authenticated user profile')]
#[OA\Tag(name: 'Dashboard', description: 'Dashboard summary')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Paste the JWT returned by POST /api/v1/auth/login (without the Bearer prefix).'
)]
#[OA\Schema(
    schema: 'User',
    type: 'object',
    required: ['id', 'name', 'username'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Ada Lovelace'),
        new OA\Property(property: 'username', type: 'string', example: 'ada', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ada@hafline.test', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'LoginRequest',
    type: 'object',
    required: ['login', 'password'],
    properties: [
        new OA\Property(property: 'login', type: 'string', description: 'Email or username', example: 'admin@hafline.test'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
    ]
)]
#[OA\Schema(
    schema: 'LoginResponse',
    type: 'object',
    required: ['token', 'token_type', 'expires_in', 'user'],
    properties: [
        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0In0.signature'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', example: 3600, description: 'Seconds until the JWT expires'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'login zorunludur. (and 1 more error)'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            )
        ),
    ]
)]
#[OA\Schema(
    schema: 'UnauthenticatedError',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ]
)]
#[OA\Schema(
    schema: 'MessageResponse',
    type: 'object',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Çıkış yapıldı.'),
    ]
)]
#[OA\Schema(
    schema: 'UserResourceResponse',
    type: 'object',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
    ]
)]
#[OA\Schema(
    schema: 'DashboardResponse',
    type: 'object',
    required: ['user', 'counts'],
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(
            property: 'counts',
            type: 'object',
            required: ['sites', 'vehicles', 'hauls'],
            properties: [
                new OA\Property(property: 'sites', type: 'integer', example: 0),
                new OA\Property(property: 'vehicles', type: 'integer', example: 0),
                new OA\Property(property: 'hauls', type: 'integer', example: 0),
            ]
        ),
    ]
)]
class OpenApiSpec {}
