@php
    $quantity = rtrim(rtrim(number_format((float) $usage->quantity, 3, ',', '.'), '0'), ',');
@endphp

<div class="panel-form-grid is-detail">
    <x-form.group legend="Stok kullanımı" icon="fa-solid fa-box-open" description="Kalem, miktar, maliyet ve gider yeri" class="is-wide">
        <div class="panel-detail-columns">
            <x-form.field label="Kalem">
                <div>
                    @can('view-stock-item')
                        <a href="{{ route('admin.stock-items.show', $usage->stock_item_id) }}">{{ $usage->stockItem?->name }}</a>
                    @else
                        {{ $usage->stockItem?->name }}
                    @endcan
                </div>
            </x-form.field>
            <x-form.field label="Tarih">
                <div>{{ $usage->used_on->format('d.m.Y') }}</div>
            </x-form.field>
            <x-form.field label="Miktar">
                <div>{{ $quantity }} {{ $usage->stockItem?->unit?->value }}</div>
            </x-form.field>
            <x-form.field label="Birim maliyet">
                <div><x-ui.money :value="$usage->unit_cost" :decimals="4" /></div>
            </x-form.field>
            <x-form.field label="Tutar (gider)">
                <div><x-ui.money :value="$usage->amount" /></div>
            </x-form.field>
            <x-form.field label="Şantiye">
                <div>{{ $usage->site?->name ?? '—' }}</div>
            </x-form.field>
            <x-form.field label="Araç">
                <div>{{ $usage->vehicle?->plate ?? '—' }}</div>
            </x-form.field>
            <x-form.field label="Gider kategorisi">
                <div>{{ $usage->cashCategory?->fullName() ?? '—' }}</div>
            </x-form.field>
            <x-form.field label="Açıklama">
                <div>{{ $usage->description ?? '—' }}</div>
            </x-form.field>
        </div>
    </x-form.group>
</div>
