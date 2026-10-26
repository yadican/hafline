<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Hafline API',
    description: 'Hafline saha uygulamasının HTTP API\'si. Tüm istekler `Accept: application/json` ile gönderilir.',
    contact: new OA\Contact(name: 'Hafline', email: 'info@hafline.net'),
    license: new OA\License(name: 'MIT', url: 'https://opensource.org/licenses/MIT'),
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Application server'
)]
#[OA\Tag(name: '1. Giriş', description: 'Token alma ve çıkış')]
#[OA\Tag(name: '2. Saha oturumu', description: 'Şantiye, döküm yeri ve vardiya seçimi; oturumu açma, okuma ve kapatma')]
#[OA\Tag(name: '3. Plaka ve fiş', description: 'Plaka fotoğrafı okuma, fiş kaydı ve fişi tekrar alma')]
#[OA\Tag(name: '4. Makine fişi', description: 'Saatlik makine çalışma fişi: plakayı fotoğraftan okuma veya elle girip doğrulama, fiş kaydı, listeleme, düzenleme ve silme. Şantiye ve vardiya açık saha oturumundan alınır.')]
#[OA\Tag(name: '5. Yakıt fişi', description: 'Yakıt fişi: plakayı fotoğraftan okuma veya elle girip doğrulama, oturumdaki şantiyenin yakıt yerleri, fiş kaydı, listeleme, düzenleme ve silme. Şantiye ve vardiya açık saha oturumundan alınır.')]
#[OA\Tag(name: 'Profil', description: 'Giriş yapan kullanıcı ve özet')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'POST /api/v1/auth/login cevabındaki token (Bearer öneki olmadan).'
)]
#[OA\Schema(
    schema: 'User',
    type: 'object',
    required: ['id', 'name', 'username'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '01a17400-5296-7180-b4ae-4a96279c4bc1'),
        new OA\Property(property: 'name', type: 'string', example: 'Hafline Api'),
        new OA\Property(property: 'username', type: 'string', example: 'hafline.api', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'hafline@hafline.net', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'LoginRequest',
    type: 'object',
    required: ['login', 'password'],
    properties: [
        new OA\Property(property: 'login', type: 'string', description: 'Kullanıcı adı veya e-posta', example: 'hafline.api'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'HafLine34!'),
    ]
)]
#[OA\Schema(
    schema: 'LoginResponse',
    type: 'object',
    required: ['token', 'token_type', 'expires_in', 'user'],
    properties: [
        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0In0.signature'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', example: 3600, description: 'Token geçerlilik süresi (saniye)'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    type: 'object',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Geçerli bir saha oturumu seçin veya yeniden sabitleyin.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            ),
            example: ['field_session_id' => ['Geçerli bir saha oturumu seçin veya yeniden sabitleyin.']]
        ),
    ]
)]
#[OA\Schema(
    schema: 'UnauthenticatedError',
    type: 'object',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    type: 'object',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'User does not have the right permissions.'),
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
    schema: 'DashboardCounts',
    type: 'object',
    description: 'Kullanıcının kendi kestiği fiş sayıları (fiş tarihine göre)',
    required: ['today', 'yesterday', 'month'],
    properties: [
        new OA\Property(property: 'today', type: 'integer', example: 12),
        new OA\Property(property: 'yesterday', type: 'integer', example: 9),
        new OA\Property(property: 'month', type: 'integer', description: 'Ay başından bugüne', example: 210),
    ]
)]
#[OA\Schema(
    schema: 'DashboardRecentItem',
    type: 'object',
    required: ['type', 'id', 'time', 'plate', 'summary'],
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['haul', 'machine_work', 'fuel_receipt'], example: 'haul'),
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Tipine göre GET /hauls/{id}, /machine-works/{id} veya /fuel-receipts/{id}'),
        new OA\Property(property: 'time', type: 'string', description: 'Fişin girildiği saat', example: '14:32'),
        new OA\Property(property: 'plate', type: 'string', nullable: true, example: '34 ABC 123'),
        new OA\Property(property: 'summary', type: 'string', example: 'ADALAR HEYBELİ → ERZURUM SAHA'),
    ]
)]
#[OA\Schema(
    schema: 'DashboardResponse',
    type: 'object',
    required: ['user', 'field_session', 'counts', 'recent'],
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(
            property: 'field_session',
            description: 'Açık oturum yoksa null',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/FieldSession')]
        ),
        new OA\Property(
            property: 'counts',
            type: 'object',
            required: ['hauls', 'machine_works', 'fuel_receipts'],
            properties: [
                new OA\Property(property: 'hauls', ref: '#/components/schemas/DashboardCounts'),
                new OA\Property(property: 'machine_works', ref: '#/components/schemas/DashboardCounts'),
                new OA\Property(property: 'fuel_receipts', ref: '#/components/schemas/DashboardCounts'),
            ]
        ),
        new OA\Property(
            property: 'recent',
            type: 'array',
            description: 'Bugün girilen son fişler, en yeni önce, en fazla 10',
            items: new OA\Items(ref: '#/components/schemas/DashboardRecentItem')
        ),
    ]
)]
class OpenApiSpec {}
