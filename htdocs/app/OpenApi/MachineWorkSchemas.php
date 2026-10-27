<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MachineOperatorOption',
    type: 'object',
    required: ['id', 'name', 'salaried', 'last_rate'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', example: 'Kemal Usta'),
        new OA\Property(property: 'salaried', type: 'boolean', description: 'Personel ise true; fişte operatör ücreti yazılmaz', example: false),
        new OA\Property(property: 'last_rate', type: 'string', nullable: true, description: 'Son fişteki operatör saat ücreti', example: '350.00'),
    ]
)]
#[OA\Schema(
    schema: 'MachineWorkOptionsResponse',
    type: 'object',
    required: ['operators'],
    properties: [
        new OA\Property(property: 'operators', type: 'array', items: new OA\Items(ref: '#/components/schemas/MachineOperatorOption')),
    ]
)]
#[OA\Schema(
    schema: 'MachineSearchResponse',
    type: 'object',
    required: ['machines'],
    properties: [
        new OA\Property(
            property: 'machines',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                required: ['plate', 'owner_name', 'class_name', 'hourly_rate'],
                properties: [
                    new OA\Property(property: 'plate', type: 'string', example: '34 MK 100'),
                    new OA\Property(property: 'owner_name', type: 'string', nullable: true, example: 'ABC Hafriyat'),
                    new OA\Property(property: 'class_name', type: 'string', nullable: true, example: 'Ekskavatör'),
                    new OA\Property(property: 'hourly_rate', type: 'string', nullable: true, description: 'Güncel saat ücreti', example: '1500.00'),
                ]
            )
        ),
    ]
)]
#[OA\Schema(
    schema: 'MachinePlateCheck',
    type: 'object',
    required: ['vehicle', 'hourly', 'owner_on_date', 'owner_name', 'hourly_rate', 'rate_required', 'can_create', 'message'],
    properties: [
        new OA\Property(
            property: 'vehicle',
            type: 'object',
            required: ['id', 'plate', 'found'],
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'plate', type: 'string', nullable: true, description: 'Sistemde kayıtlı yazım', example: '34 MK 100'),
                new OA\Property(property: 'found', type: 'boolean', example: true),
            ]
        ),
        new OA\Property(property: 'hourly', type: 'boolean', description: 'Araç tipi saatlik çalışıyor mu', example: true),
        new OA\Property(property: 'owner_on_date', type: 'boolean', description: 'Makinenin bugün kayıtlı sahibi var mı', example: true),
        new OA\Property(property: 'owner_name', type: 'string', nullable: true, example: 'ABC Hafriyat'),
        new OA\Property(property: 'hourly_rate', type: 'string', nullable: true, description: 'Bugün geçerli saat ücreti', example: '1500.00'),
        new OA\Property(property: 'rate_required', type: 'boolean', description: 'Tanımlı ücret yok; fişte hourly_rate girilmeli', example: false),
        new OA\Property(property: 'can_create', type: 'boolean', description: 'Bu plakayla fiş kesilebilir mi', example: true),
        new OA\Property(property: 'message', type: 'string', nullable: true, description: 'can_create false ise sebebi; rate_required true ise uyarı', example: null),
    ]
)]
#[OA\Schema(
    schema: 'MachinePlateScanResult',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/MachinePlateCheck'),
        new OA\Schema(
            type: 'object',
            required: ['success', 'scan_id', 'field_session_id', 'plate', 'confidence', 'matched_regex'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', description: 'OCR plaka bulabildi mi', example: true),
                new OA\Property(property: 'scan_id', type: 'string', format: 'uuid', description: 'POST /machine-works için isteğe bağlı. 1 saat geçerli.'),
                new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'plate', type: 'string', nullable: true, description: 'OCR\'ın okuduğu metin (boşluksuz olabilir)', example: '34MK100'),
                new OA\Property(property: 'confidence', type: 'number', format: 'float', nullable: true, example: 0.93),
                new OA\Property(property: 'matched_regex', type: 'boolean', example: true),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'MachineWorkUpdateRequest',
    type: 'object',
    required: ['plate', 'hours'],
    properties: [
        new OA\Property(property: 'plate', type: 'string', maxLength: 20, description: 'Kayıtlı, saatlik çalışan bir makine', example: '34 MK 100'),
        new OA\Property(property: 'hours', type: 'number', format: 'float', minimum: 0, maximum: 24, description: '0\'dan büyük, en fazla 2 ondalık; "4,5" de kabul edilir', example: 4.5),
        new OA\Property(property: 'hourly_rate', type: 'number', format: 'float', nullable: true, description: 'Boşsa makinenin o tarihte geçerli saat ücreti kullanılır', example: null),
        new OA\Property(property: 'operator_id', type: 'string', format: 'uuid', nullable: true, description: 'GET /machine-works/options > operators[].id'),
        new OA\Property(property: 'operator_rate', type: 'number', format: 'float', nullable: true, description: 'Personel olmayan operatör için zorunlu', example: 350),
    ]
)]
#[OA\Schema(
    schema: 'MachineWorkStoreRequest',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/MachineWorkUpdateRequest'),
        new OA\Schema(
            type: 'object',
            required: ['field_session_id'],
            properties: [
                new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid', description: 'POST /field-sessions > data.id'),
                new OA\Property(property: 'scan_id', type: 'string', format: 'uuid', nullable: true, description: 'POST /machine-works/scan > scan_id. Elle girişte gönderilmez.'),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'MachineWorkTicket',
    type: 'object',
    description: 'Makine fişi yazdırma verisi',
    required: ['id', 'title', 'ticket_no', 'worked_on', 'worked_on_label', 'plate', 'class_name', 'owner_name', 'site_name', 'shift', 'shift_label', 'hours', 'hours_label', 'hourly_rate', 'amount', 'operator', 'operator_rate', 'operator_amount', 'qr_payload', 'photo_url'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'title', type: 'string', example: 'MAKİNE ÇALIŞMA FİŞİ'),
        new OA\Property(property: 'ticket_no', type: 'string', example: 'M-20261026-183012-07'),
        new OA\Property(property: 'worked_on', type: 'string', format: 'date', example: '2026-10-26'),
        new OA\Property(property: 'worked_on_label', type: 'string', example: '26/10/2026'),
        new OA\Property(property: 'plate', type: 'string', nullable: true, example: '34 MK 100'),
        new OA\Property(property: 'class_name', type: 'string', nullable: true, example: 'Ekskavatör'),
        new OA\Property(property: 'owner_name', type: 'string', nullable: true, example: 'ABC Hafriyat'),
        new OA\Property(property: 'site_name', type: 'string', nullable: true, example: 'ADALAR HEYBELİ'),
        new OA\Property(property: 'shift', type: 'string', enum: ['day', 'night'], example: 'day'),
        new OA\Property(property: 'shift_label', type: 'string', example: 'Gündüz'),
        new OA\Property(property: 'hours', type: 'number', format: 'float', example: 4.5),
        new OA\Property(property: 'hours_label', type: 'string', example: '4,5'),
        new OA\Property(property: 'hourly_rate', type: 'string', example: '1500.00'),
        new OA\Property(property: 'amount', type: 'string', example: '6750.00'),
        new OA\Property(
            property: 'operator',
            type: 'object',
            nullable: true,
            required: ['id', 'name'],
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string', example: 'Kemal Usta'),
            ]
        ),
        new OA\Property(property: 'operator_rate', type: 'string', nullable: true, example: '350.00'),
        new OA\Property(property: 'operator_amount', type: 'string', nullable: true, example: '1575.00'),
        new OA\Property(property: 'qr_payload', type: 'string', description: 'Fiş QR kodunun içeriği'),
        new OA\Property(property: 'photo_url', type: 'string', format: 'uri', nullable: true, description: 'Plaka fotoğrafı; elle girilen fişlerde null'),
    ]
)]
#[OA\Schema(
    schema: 'MachineWorkTicketResponse',
    type: 'object',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/MachineWorkTicket'),
    ]
)]
#[OA\Schema(
    schema: 'SessionMachineWork',
    type: 'object',
    required: ['id', 'ticket_no', 'plate', 'worked_on_label', 'site_name', 'shift', 'operator_name', 'hours', 'amount'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'ticket_no', type: 'string', example: 'M-20261026-183012-07'),
        new OA\Property(property: 'plate', type: 'string', nullable: true, example: '34 MK 100'),
        new OA\Property(property: 'worked_on_label', type: 'string', example: '26/10/2026'),
        new OA\Property(property: 'site_name', type: 'string', nullable: true),
        new OA\Property(property: 'shift', type: 'string', enum: ['day', 'night']),
        new OA\Property(property: 'operator_name', type: 'string', nullable: true),
        new OA\Property(property: 'hours', type: 'number', format: 'float', example: 4.5),
        new OA\Property(property: 'amount', type: 'string', example: '6750.00'),
    ]
)]
#[OA\Schema(
    schema: 'SessionMachineWorksResponse',
    type: 'object',
    required: ['session', 'data'],
    properties: [
        new OA\Property(property: 'session', ref: '#/components/schemas/FieldSession'),
        new OA\Property(property: 'data', type: 'array', description: 'Bugünkü makine fişleri, en yeni önce, en fazla 100', items: new OA\Items(ref: '#/components/schemas/SessionMachineWork')),
    ]
)]
class MachineWorkSchemas {}
