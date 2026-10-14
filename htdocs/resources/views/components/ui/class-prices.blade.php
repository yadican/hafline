{{-- vehicleClass ilişkisi yüklü fiyat koleksiyonunu "SINIF 1.234,00 TL" etiketleriyle gösterir --}}
@props(['prices', 'empty' => 'Fiyat yok'])

@php
    $sorted = $prices->sortBy(fn ($price) => $price->vehicleClass?->name);
@endphp

@if ($sorted->isEmpty())
    <span class="panel-chips-empty">{{ $empty }}</span>
@else
    <span class="panel-chips">
        @foreach ($sorted as $price)
            <span class="panel-chip">
                <span class="panel-chip-label">{{ $price->vehicleClass?->name }}</span>
                <x-ui.money :value="$price->price" />
            </span>
        @endforeach
    </span>
@endif
