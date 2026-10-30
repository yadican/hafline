@php
    $unit = $stockItem->unit->value;
    $number = fn ($value, int $decimals = 2): string => number_format((float) $value, $decimals, ',', '.');
    $quantity = fn ($value): string => rtrim(rtrim($number($value, 3), '0'), ',');
    $kindLabel = fn (string $kind): string => match ($kind) {
        'purchase', 'sale_return' => 'Giriş',
        'sale', 'usage', 'purchase_return' => 'Çıkış',
        'count' => 'Sayım',
        default => $kind,
    };
@endphp

<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="Hareketler" icon="fa-solid fa-arrow-right-arrow-left" description="Giriş, çıkış ve sayım; belgeye tıklayınca kaynak açılır" class="is-wide">
        @if ($movements->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Tür</th>
                        <th class="is-end">Miktar</th>
                        <th class="is-end">Birim maliyet</th>
                        <th class="is-end">Tutar</th>
                        <th>Belge</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movements as $movement)
                        @php
                            $qty = (float) $movement['quantity'];
                            $amount = (float) $movement['amount'];
                            $unitCost = match (true) {
                                in_array($movement['kind'], ['purchase', 'sale_return'], true) && $qty > 0 => $amount / $qty,
                                in_array($movement['kind'], ['sale', 'usage', 'purchase_return'], true) && $qty != 0.0 && $amount != 0.0 => abs($amount / $qty),
                                default => null,
                            };
                            $document = match ($movement['kind']) {
                                'purchase' => 'Alış faturası '.$movement['invoice_no'],
                                'purchase_return' => 'Alış iadesi '.$movement['invoice_no'],
                                'sale' => 'Satış faturası '.$movement['invoice_no'],
                                'sale_return' => 'Satış iadesi '.$movement['invoice_no'],
                                'usage' => 'Stok kullanımı',
                                'count' => 'Sayım',
                                default => $movement['invoice_no'] ?? '—',
                            };
                        @endphp
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($movement['date'])->format('d.m.Y') }}</td>
                            <td>{{ $kindLabel($movement['kind']) }}</td>
                            <td class="is-end">{{ $qty > 0 ? '+' : '' }}{{ $quantity($qty) }} {{ $unit }}</td>
                            <td class="is-end">
                                @if ($unitCost !== null)
                                    <x-ui.money :value="$unitCost" :decimals="4" />
                                @else
                                    —
                                @endif
                            </td>
                            <td class="is-end">
                                @if ($amount != 0.0)
                                    <x-ui.money :value="$amount" />
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if (! empty($movement['invoice_id']))
                                    @can('view-invoice')
                                        <a href="{{ route('admin.invoices.show', $movement['invoice_id']) }}">{{ $document }}</a>
                                    @else
                                        {{ $document }}
                                    @endcan
                                @elseif (! empty($movement['stock_usage_id']))
                                    @can('view-stock-usage')
                                        <a href="{{ route('admin.stock-usages.show', $movement['stock_usage_id']) }}">{{ $document }}</a>
                                    @else
                                        {{ $document }}
                                    @endcan
                                @elseif (! empty($movement['stock_count_id']))
                                    @can('view-stock-count')
                                        <a href="{{ route('admin.stock-counts.show', $movement['stock_count_id']) }}">{{ $document }}</a>
                                    @else
                                        {{ $document }}
                                    @endcan
                                @else
                                    {{ $document }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Henüz stok hareketi yok.</p>
        @endif
    </x-form.group>
</div>
