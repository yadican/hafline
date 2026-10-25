<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'NamedOption',
    type: 'object',
    required: ['id', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '01a172ee-f413-7048-88d9-7a690733cf2a'),
        new OA\Property(property: 'name', type: 'string', example: 'ADALAR HEYBELİ'),
    ]
)]
#[OA\Schema(
    schema: 'ShiftOption',
    type: 'object',
    required: ['value', 'label'],
    properties: [
        new OA\Property(property: 'value', type: 'string', enum: ['day', 'night'], example: 'day'),
        new OA\Property(property: 'label', type: 'string', example: 'Gündüz'),
    ]
)]
#[OA\Schema(
    schema: 'HaulOptionsResponse',
    type: 'object',
    required: ['sites', 'shifts'],
    properties: [
        new OA\Property(property: 'sites', type: 'array', items: new OA\Items(ref: '#/components/schemas/NamedOption')),
        new OA\Property(property: 'shifts', type: 'array', items: new OA\Items(ref: '#/components/schemas/ShiftOption')),
    ]
)]
#[OA\Schema(
    schema: 'DumpSitesResponse',
    type: 'object',
    required: ['dump_sites'],
    properties: [
        new OA\Property(property: 'dump_sites', type: 'array', items: new OA\Items(ref: '#/components/schemas/NamedOption')),
    ]
)]
#[OA\Schema(
    schema: 'FieldSessionRequest',
    type: 'object',
    required: ['site_id', 'dump_site_id', 'shift'],
    properties: [
        new OA\Property(property: 'site_id', type: 'string', format: 'uuid', description: 'GET /hauls/options > sites[].id', example: '01a172ee-f413-7048-88d9-7a690733cf2a'),
        new OA\Property(property: 'dump_site_id', type: 'string', format: 'uuid', description: 'GET /hauls/dump-sites > dump_sites[].id', example: '01a172ee-f9ae-70ac-a3f5-b7cb76efdf99'),
        new OA\Property(property: 'shift', type: 'string', enum: ['day', 'night'], description: 'GET /hauls/options > shifts[].value', example: 'day'),
    ]
)]
#[OA\Schema(
    schema: 'FieldSession',
    type: 'object',
    required: ['id', 'site_id', 'site_name', 'dump_site_id', 'dump_site_name', 'shift', 'shift_label', 'locked_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Sonraki isteklerde field_session_id olarak gönderilir', example: '01a1745d-96cf-7091-809a-ae092f874703'),
        new OA\Property(property: 'site_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'site_name', type: 'string', nullable: true, example: 'ADALAR HEYBELİ'),
        new OA\Property(property: 'dump_site_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'dump_site_name', type: 'string', nullable: true, example: 'ERZURUM SAHA'),
        new OA\Property(property: 'shift', type: 'string', enum: ['day', 'night'], example: 'day'),
        new OA\Property(property: 'shift_label', type: 'string', example: 'Gündüz'),
        new OA\Property(property: 'locked_at', type: 'string', format: 'date-time', example: '2026-10-25T21:21:00+03:00'),
    ]
)]
#[OA\Schema(
    schema: 'FieldSessionResponse',
    type: 'object',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/FieldSession'),
    ]
)]
#[OA\Schema(
    schema: 'CurrentFieldSessionResponse',
    type: 'object',
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            description: 'Açık oturum yoksa null',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/FieldSession')]
        ),
    ]
)]
#[OA\Schema(
    schema: 'PlateScanResult',
    type: 'object',
    required: ['success', 'scan_id', 'field_session_id', 'plate', 'confidence', 'matched_regex', 'vehicle', 'owner_on_date', 'can_create', 'message'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', description: 'OCR plaka bulabildi mi', example: true),
        new OA\Property(property: 'scan_id', type: 'string', format: 'uuid', description: 'POST /hauls için gerekli. 1 saat geçerli.', example: '01a17493-00d0-71e2-a9ac-55171280c960'),
        new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'plate', type: 'string', nullable: true, description: 'OCR\'ın okuduğu metin (boşluksuz olabilir)', example: '01HCU728'),
        new OA\Property(property: 'confidence', type: 'number', format: 'float', nullable: true, description: '0–1 arası. Yüksek olması doğruluk garantisi değildir.', example: 0.93),
        new OA\Property(property: 'matched_regex', type: 'boolean', description: 'Okunan metin Türk plaka biçimine uyuyor mu', example: true),
        new OA\Property(
            property: 'vehicle',
            type: 'object',
            required: ['id', 'plate', 'found'],
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'plate', type: 'string', nullable: true, description: 'Sistemde kayıtlı yazım', example: '01 HCU 728'),
                new OA\Property(property: 'found', type: 'boolean', example: true),
            ]
        ),
        new OA\Property(property: 'owner_on_date', type: 'boolean', description: 'Aracın bugün kayıtlı sahibi var mı', example: true),
        new OA\Property(property: 'can_create', type: 'boolean', description: 'Okunan plakayla doğrudan fiş kesilebilir mi', example: true),
        new OA\Property(property: 'message', type: 'string', nullable: true, description: 'can_create false ise sebebi', example: null),
    ]
)]
#[OA\Schema(
    schema: 'HaulStoreRequest',
    type: 'object',
    required: ['plate', 'scan_id', 'field_session_id'],
    properties: [
        new OA\Property(property: 'plate', type: 'string', maxLength: 20, description: 'Kullanıcının onayladığı plaka. Kayıtlı bir araç olmalı.', example: '01 HCU 728'),
        new OA\Property(property: 'scan_id', type: 'string', format: 'uuid', description: 'POST /hauls/scan > scan_id'),
        new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid', description: 'POST /field-sessions > data.id'),
    ]
)]
#[OA\Schema(
    schema: 'HaulTicket',
    type: 'object',
    description: 'Fiş yazdırma verisi',
    required: ['id', 'title', 'ticket_no', 'hauled_at', 'hauled_at_label', 'plate', 'site_name', 'dump_site_name', 'shift', 'shift_label', 'tonnage', 'qr_payload', 'photo_url'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'title', type: 'string', example: 'SEFER HAREKET FİŞİ'),
        new OA\Property(property: 'ticket_no', type: 'string', example: '20261025-235232-63'),
        new OA\Property(property: 'hauled_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'hauled_at_label', type: 'string', example: '25/10/2026 23:52'),
        new OA\Property(property: 'plate', type: 'string', nullable: true, example: '72 DLH 681'),
        new OA\Property(property: 'site_name', type: 'string', nullable: true, example: 'ADALAR HEYBELİ'),
        new OA\Property(property: 'dump_site_name', type: 'string', nullable: true, example: 'ERZURUM SAHA'),
        new OA\Property(property: 'shift', type: 'string', enum: ['day', 'night'], example: 'day'),
        new OA\Property(property: 'shift_label', type: 'string', example: 'Gündüz'),
        new OA\Property(property: 'tonnage', type: 'integer', example: 0),
        new OA\Property(property: 'qr_payload', type: 'string', description: 'Fiş QR kodunun içeriği', example: '01a1749f-863a-71fc-83b9-7fd9051a7681'),
        new OA\Property(property: 'photo_url', type: 'string', format: 'uri', nullable: true, description: 'Plaka fotoğrafı için imzalı, süreli adres'),
    ]
)]
#[OA\Schema(
    schema: 'HaulTicketResponse',
    type: 'object',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/HaulTicket'),
    ]
)]
#[OA\Schema(
    schema: 'SessionHaul',
    type: 'object',
    required: ['id', 'ticket_no', 'plate', 'hauled_at_label', 'site_name', 'dump_site_name', 'shift', 'field_session_id'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'ticket_no', type: 'string', example: '20261025-235232-63'),
        new OA\Property(property: 'plate', type: 'string', nullable: true, example: '72 DLH 681'),
        new OA\Property(property: 'hauled_at_label', type: 'string', example: '25/10/2026 23:52'),
        new OA\Property(property: 'site_name', type: 'string', nullable: true),
        new OA\Property(property: 'dump_site_name', type: 'string', nullable: true),
        new OA\Property(property: 'shift', type: 'string', enum: ['day', 'night']),
        new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'SessionHaulsResponse',
    type: 'object',
    required: ['session', 'data'],
    properties: [
        new OA\Property(property: 'session', ref: '#/components/schemas/FieldSession'),
        new OA\Property(property: 'data', type: 'array', description: 'Bugünkü fişler, en yeni önce, en fazla 100', items: new OA\Items(ref: '#/components/schemas/SessionHaul')),
    ]
)]
class FieldHaulSchemas {}
