@php
    $number = fn ($value, int $decimals = 2): string => number_format((float) $value, $decimals, ',', '.');
    $quantity = fn ($value): string => rtrim(rtrim($number($value, 3), '0'), ',');
    $price = fn ($value): string => preg_replace('/(,\d{2}\d*?)0+$/', '$1', $number($value, 4));
    $hasVat = $invoice->lines->contains(fn ($line): bool => (float) $line->vat_rate > 0);
@endphp
<div class="panel-form-grid panel-form-grid-after" id="satirlar">
    <x-form.group legend="Satırlar" icon="fa-solid fa-list" :description="$invoice->direction === \App\Enums\InvoiceDirection::Sale ? 'Faturadaki kalemler ve gelir kategorileri' : 'Faturadaki kalemler ve gider kategorileri'" class="is-wide">
        <table class="panel-subtable">
            <thead>
                <tr>
                    <th>Stok kalemi</th>
                    <th>Açıklama</th>
                    <th>Kategori</th>
                    <th class="is-end">Miktar</th>
                    <th class="is-end">Birim fiyat</th>
                    @if ($hasVat)
                        <th class="is-end">Matrah</th>
                        <th class="is-end">KDV %</th>
                        <th class="is-end">KDV</th>
                    @endif
                    <th class="is-end">Toplam</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lines as $line)
                    <tr>
                        <td>{{ $line->stockItem?->name ?? '—' }}</td>
                        <td>{{ $line->description }}</td>
                        <td>{{ $line->cashCategory?->name ?? '—' }}</td>
                        <td class="is-end">{{ $quantity($line->quantity) }}</td>
                        <td class="is-end">{{ $price($line->unit_price) }}</td>
                        @if ($hasVat)
                            <td class="is-end">{{ $number($line->subtotal) }}</td>
                            <td class="is-end">%{{ $quantity($line->vat_rate) }}</td>
                            <td class="is-end">{{ $number($line->vat_amount) }}</td>
                        @endif
                        <td class="is-end">{{ $number($line->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-form.group>
</div>
