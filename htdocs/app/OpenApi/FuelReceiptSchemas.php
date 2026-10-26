<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'FuelStationOption',
    type: 'object',
    required: ['id', 'name', 'firm_name', 'unit_price'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', example: 'MERKEZ PETROL'),
        new OA\Property(property: 'firm_name', type: 'string', nullable: true, description: 'İstasyonun cari firması', example: 'Merkez Akaryakıt Ltd.'),
        new OA\Property(property: 'unit_price', type: 'string', description: 'Güncel litre fiyatı (0 ise fişte unit_price girilmeli)', example: '48.7500'),
    ]
)]
#[OA\Schema(
    schema: 'FuelReceiptOptionsResponse',
    type: 'object',
    required: ['stations'],
    properties: [
        new OA\Property(property: 'stations', type: 'array', items: new OA\Items(ref: '#/components/schemas/FuelStationOption')),
    ]
)]
#[OA\Schema(
    schema: 'FuelVehicleSearchResponse',
    type: 'object',
    required: ['vehicles'],
    properties: [
        new OA\Property(
            property: 'vehicles',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                required: ['plate', 'owner_name', 'class_name'],
                properties: [
                    new OA\Property(property: 'plate', type: 'string', example: '34 AB 123'),
                    new OA\Property(property: 'owner_name', type: 'string', nullable: true, example: 'ABC Hafriyat'),
                    new OA\Property(property: 'class_name', type: 'string', nullable: true, example: 'Kamyon'),
                ]
            )
        ),
    ]
)]
#[OA\Schema(
    schema: 'FuelPlateCheck',
    type: 'object',
    required: ['vehicle', 'owner_on_date', 'owner_name', 'can_create', 'message'],
    properties: [
        new OA\Property(
            property: 'vehicle',
            type: 'object',
            required: ['id', 'plate', 'found'],
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'plate', type: 'string', nullable: true, description: 'Sistemde kayıtlı yazım', example: '34 AB 123'),
                new OA\Property(property: 'found', type: 'boolean', example: true),
            ]
        ),
        new OA\Property(property: 'owner_on_date', type: 'boolean', description: 'Aracın bugün kayıtlı sahibi var mı', example: true),
        new OA\Property(property: 'owner_name', type: 'string', nullable: true, example: 'ABC Hafriyat'),
        new OA\Property(property: 'can_create', type: 'boolean', description: 'Bu plakayla fiş kesilebilir mi', example: true),
        new OA\Property(property: 'message', type: 'string', nullable: true, description: 'can_create false ise sebebi', example: null),
    ]
)]
#[OA\Schema(
    schema: 'FuelPlateScanResult',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/FuelPlateCheck'),
        new OA\Schema(
            type: 'object',
            required: ['success', 'scan_id', 'field_session_id', 'plate', 'confidence', 'matched_regex'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', description: 'OCR plaka bulabildi mi', example: true),
                new OA\Property(property: 'scan_id', type: 'string', format: 'uuid', description: 'POST /fuel-receipts için isteğe bağlı. 1 saat geçerli.'),
                new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'plate', type: 'string', nullable: true, description: 'OCR\'ın okuduğu metin (boşluksuz olabilir)', example: '34AB123'),
                new OA\Property(property: 'confidence', type: 'number', format: 'float', nullable: true, example: 0.93),
                new OA\Property(property: 'matched_regex', type: 'boolean', example: true),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'FuelReceiptUpdateRequest',
    type: 'object',
    required: ['plate', 'fuel_station_id', 'quantity'],
    properties: [
        new OA\Property(property: 'plate', type: 'string', maxLength: 20, description: 'Kayıtlı bir araç', example: '34 AB 123'),
        new OA\Property(property: 'fuel_station_id', type: 'string', format: 'uuid', description: 'GET /fuel-receipts/options > stations[].id'),
        new OA\Property(property: 'quantity', type: 'number', format: 'float', minimum: 0, maximum: 99999, description: 'Litre; 0\'dan büyük, en fazla 2 ondalık; "45,5" de kabul edilir', example: 120),
        new OA\Property(property: 'unit_price', type: 'number', format: 'float', nullable: true, description: 'Boşsa istasyonun güncel fiyatı kullanılır; en fazla 4 ondalık', example: null),
        new OA\Property(property: 'km', type: 'integer', nullable: true, minimum: 0, description: 'Aracın kilometresi', example: 152300),
        new OA\Property(property: 'received_by', type: 'string', maxLength: 80, nullable: true, description: 'Yakıtı alan kişi', example: 'Mehmet Yılmaz'),
        new OA\Property(property: 'haul_ticket_count', type: 'integer', nullable: true, minimum: 0, maximum: 999, description: 'Taşıma fiş adedi', example: 4),
    ]
)]
#[OA\Schema(
    schema: 'FuelReceiptStoreRequest',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/FuelReceiptUpdateRequest'),
        new OA\Schema(
            type: 'object',
            required: ['field_session_id'],
            properties: [
                new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid', description: 'POST /field-sessions > data.id'),
                new OA\Property(property: 'scan_id', type: 'string', format: 'uuid', nullable: true, description: 'POST /fuel-receipts/scan > scan_id. Elle girişte gönderilmez.'),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'FuelReceiptTicket',
    type: 'object',
    description: 'Yakıt fişi yazdırma verisi',
    required: ['id', 'title', 'ticket_no', 'issued_on', 'issued_on_label', 'plate', 'class_name', 'owner_name', 'site_name', 'shift', 'shift_label', 'station', 'firm_name', 'quantity', 'quantity_label', 'unit_price', 'amount', 'km', 'received_by', 'haul_ticket_count', 'qr_payload', 'photo_url'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'title', type: 'string', example: 'YAKIT FİŞİ'),
        new OA\Property(property: 'ticket_no', type: 'string', example: 'Y-20261026-191530-42'),
        new OA\Property(property: 'issued_on', type: 'string', format: 'date', example: '2026-10-26'),
        new OA\Property(property: 'issued_on_label', type: 'string', example: '26/10/2026'),
        new OA\Property(property: 'plate', type: 'string', nullable: true, example: '34 AB 123'),
        new OA\Property(property: 'class_name', type: 'string', nullable: true, example: 'Kamyon'),
        new OA\Property(property: 'owner_name', type: 'string', nullable: true, example: 'ABC Hafriyat'),
        new OA\Property(property: 'site_name', type: 'string', nullable: true, example: 'ADALAR HEYBELİ'),
        new OA\Property(property: 'shift', type: 'string', enum: ['day', 'night'], nullable: true, example: 'day'),
        new OA\Property(property: 'shift_label', type: 'string', nullable: true, example: 'Gündüz'),
        new OA\Property(
            property: 'station',
            type: 'object',
            nullable: true,
            required: ['id', 'name'],
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string', example: 'MERKEZ PETROL'),
            ]
        ),
        new OA\Property(property: 'firm_name', type: 'string', nullable: true, example: 'Merkez Akaryakıt Ltd.'),
        new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 120.5),
        new OA\Property(property: 'quantity_label', type: 'string', example: '120,5'),
        new OA\Property(property: 'unit_price', type: 'string', example: '48.7500'),
        new OA\Property(property: 'amount', type: 'string', example: '5874.38'),
        new OA\Property(property: 'km', type: 'integer', nullable: true, example: 152300),
        new OA\Property(property: 'received_by', type: 'string', nullable: true, example: 'Mehmet Yılmaz'),
        new OA\Property(property: 'haul_ticket_count', type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'qr_payload', type: 'string', description: 'Fiş QR kodunun içeriği'),
        new OA\Property(property: 'photo_url', type: 'string', format: 'uri', nullable: true, description: 'Plaka fotoğrafı; elle girilen fişlerde null'),
    ]
)]
#[OA\Schema(
    schema: 'FuelReceiptTicketResponse',
    type: 'object',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/FuelReceiptTicket'),
    ]
)]
#[OA\Schema(
    schema: 'SessionFuelReceipt',
    type: 'object',
    required: ['id', 'ticket_no', 'plate', 'issued_on_label', 'site_name', 'shift', 'station_name', 'quantity', 'amount'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'ticket_no', type: 'string', example: 'Y-20261026-191530-42'),
        new OA\Property(property: 'plate', type: 'string', nullable: true, example: '34 AB 123'),
        new OA\Property(property: 'issued_on_label', type: 'string', example: '26/10/2026'),
        new OA\Property(property: 'site_name', type: 'string', nullable: true),
        new OA\Property(property: 'shift', type: 'string', enum: ['day', 'night']),
        new OA\Property(property: 'station_name', type: 'string', nullable: true, example: 'MERKEZ PETROL'),
        new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 120.5),
        new OA\Property(property: 'amount', type: 'string', example: '5874.38'),
    ]
)]
#[OA\Schema(
    schema: 'SessionFuelReceiptsResponse',
    type: 'object',
    required: ['session', 'data'],
    properties: [
        new OA\Property(property: 'session', ref: '#/components/schemas/FieldSession'),
        new OA\Property(property: 'data', type: 'array', description: 'Bugünkü yakıt fişleri, en yeni önce, en fazla 100', items: new OA\Items(ref: '#/components/schemas/SessionFuelReceipt')),
    ]
)]
class FuelReceiptSchemas {}
