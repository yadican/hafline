@php
    $quantity = rtrim(rtrim(number_format((float) $count->quantity, 3, ',', '.'), '0'), ',');
    $difference = (float) $count->difference;
    $differenceLabel = ($difference > 0 ? '+' : '').rtrim(rtrim(number_format($difference, 3, ',', '.'), '0'), ',');
@endphp

<div class="panel-form-grid is-detail">
    <x-form.group legend="Stok sayımı" icon="fa-solid fa-clipboard-check" description="Sayılan miktar ve defter farkı" class="is-wide">
        <div class="panel-detail-columns">
            <x-form.field label="Kalem">
                <div>
                    @can('view-stock-item')
                        <a href="{{ route('admin.stock-items.show', $count->stock_item_id) }}">{{ $count->stockItem?->name }}</a>
                    @else
                        {{ $count->stockItem?->name }}
                    @endcan
                </div>
            </x-form.field>
            <x-form.field label="Sayım tarihi">
                <div>{{ $count->counted_on->format('d.m.Y') }}</div>
            </x-form.field>
            <x-form.field label="Sayılan miktar">
                <div>{{ $quantity }} {{ $count->stockItem?->unit?->value }}</div>
            </x-form.field>
            <x-form.field label="Fark (sayım − defter)">
                <div>{{ $differenceLabel }} {{ $count->stockItem?->unit?->value }}</div>
            </x-form.field>
            <x-form.field label="Açıklama">
                <div>{{ $count->description ?? '—' }}</div>
            </x-form.field>
        </div>
    </x-form.group>
</div>
