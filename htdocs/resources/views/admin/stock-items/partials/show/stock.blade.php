@php
    $unit = $stockItem->unit->value;
    $quantity = fn ($value): string => rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
@endphp

<div class="panel-form-grid panel-form-grid-after is-detail" id="stok">
    <x-form.group legend="Stok" icon="fa-solid fa-warehouse" description="Kalan miktar, ortalama maliyet ve stok değeri; alış faturalarından hesaplanır">
        <x-form.field label="Kalan">
            <div><strong>{{ $quantity($stock['quantity']) }} {{ $unit }}</strong></div>
        </x-form.field>
        <x-form.field label="Ortalama maliyet">
            <div><x-ui.money :value="$stock['average_cost']" :decimals="4" suffix="TL/{{ $unit }}" /></div>
        </x-form.field>
        <x-form.field label="Stok değeri">
            <div><x-ui.money :value="$stock['value']" /></div>
        </x-form.field>
    </x-form.group>
</div>
