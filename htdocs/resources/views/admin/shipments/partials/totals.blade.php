{{-- $side: 'carrier' (kırmızı, nakliyeciye alacak) veya 'customer' (yeşil, müşteriye borç); değerler JS ile dolar --}}
@php
    $carrier = $side === 'carrier';
    $money = fn ($value): string => '₺'.number_format((float) $value, 2, ',', '.');
@endphp

<div @class([
    'mt-3 rounded-lg border px-4 py-3 text-sm',
    'border-red-300 bg-red-50' => $carrier,
    'border-green-300 bg-green-50' => ! $carrier,
]) data-shipment-totals="{{ $side }}">
    <div class="flex justify-between py-1">
        <span class="text-gray-600">Ara toplam (matrah):</span>
        <strong data-total="base">{{ $money($base ?? 0) }}</strong>
    </div>
    <div @class(['flex justify-between py-1', 'text-red-600' => $carrier, 'text-green-700' => ! $carrier])>
        <span>+ KDV (<span data-total="vat-rate">%{{ (int) ($vatRate ?? 0) }}</span>):</span>
        <span data-total="vat">+{{ $money($vat ?? 0) }}</span>
    </div>
    <div class="flex justify-between py-1 text-amber-600">
        <span>− Tevkifat (<span data-total="withholding-rate">{{ $withholdingLabel ?? 'Yok' }}</span>):</span>
        <span data-total="withholding">−{{ $money($withheld ?? 0) }}</span>
    </div>
    <div @class([
        'mt-1 flex justify-between border-t pt-2 font-bold',
        'border-red-200 text-red-700' => $carrier,
        'border-green-200 text-green-800' => ! $carrier,
    ])>
        <span>{{ $carrier ? 'NAKLİYECİ NET TOPLAM:' : 'MÜŞTERİ SATIŞ NET TOPLAM:' }}</span>
        <span data-total="net">{{ $money($net ?? 0) }}</span>
    </div>
</div>
