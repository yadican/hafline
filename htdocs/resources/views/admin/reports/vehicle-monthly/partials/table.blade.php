@php
    $exportQuery = array_filter([
        'plate' => $filters['plate'] ?? $report['plate'] ?? null,
        'period' => $filters['period'] ?? 'month',
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');

    $detailRows = collect($report['rows'])->reject(fn (array $row): bool => $row['type'] === 'carryover')->values();
    $carryover = collect($report['rows'])->firstWhere('type', 'carryover');
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['plate'] }} · {{ $report['period_label'] }}</p>
    </div>

    <x-table.list class="report-table vehicle-monthly-table">
        <thead>
            <tr class="report-vehicle-head">
                <th colspan="3" class="report-vehicle-plate">{{ $report['plate'] }}</th>
                <th colspan="2" class="is-center">Yapılan İşin Açıklaması</th>
                <th colspan="2" class="is-center">Cari Hesap Durumu</th>
            </tr>
            <tr class="report-col-head">
                <th class="is-center">Sıra</th>
                <th>Tarih</th>
                <th>Açıklama</th>
                <th class="is-end">Toplam</th>
                <th class="is-end">Dönem Ortalama Birim Fiyatı</th>
                <th class="is-end">Borç</th>
                <th class="is-end">Alacak</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($detailRows as $row)
                <tr>
                    <td class="is-center">{{ $loop->iteration }}</td>
                    <td>{{ $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->format('d.m.Y') : '' }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="is-end">
                        @if ($row['quantity'] !== null)
                            @if (in_array($row['quantity_unit'], ['lt', 'saat'], true))
                                {{ number_format($row['quantity'], 2, ',', '.') }} {{ $row['quantity_unit'] }}
                            @else
                                {{ (int) $row['quantity'] }}
                            @endif
                        @endif
                    </td>
                    <td class="panel-tutar is-end">
                        @if ($row['unit_price'] !== null)
                            {{ number_format($row['unit_price'], 2, ',', '.') }} TL
                        @endif
                    </td>
                    <td class="panel-tutar is-end">
                        @if ($row['debit'] !== null)
                            {{ number_format($row['debit'], 2, ',', '.') }} TL
                        @endif
                    </td>
                    <td class="panel-tutar is-end">
                        @if ($row['credit'] !== null)
                            {{ number_format($row['credit'], 2, ',', '.') }} TL
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="is-center report-empty-note">Bu dönemde hareket yok.</td>
                </tr>
            @endforelse

            @if ($carryover !== null)
                <tr class="report-carryover">
                    <td></td>
                    <td></td>
                    <td>{{ $carryover['description'] }}</td>
                    <td></td>
                    <td></td>
                    <td class="panel-tutar is-end">
                        @if ($carryover['debit'] !== null)
                            {{ number_format($carryover['debit'], 2, ',', '.') }} TL
                        @endif
                    </td>
                    <td class="panel-tutar is-end">
                        @if ($carryover['credit'] !== null)
                            {{ number_format($carryover['credit'], 2, ',', '.') }} TL
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr class="report-footer">
                <th colspan="3">TOPLAM</th>
                <th class="is-end">
                    <span class="report-footer-meta">Sefer: {{ $report['trips'] }}</span>
                </th>
                <th></th>
                <th class="panel-tutar is-end">{{ number_format($report['total_debit'], 2, ',', '.') }} TL</th>
                <th class="panel-tutar is-end">{{ number_format($report['total_credit'], 2, ',', '.') }} TL</th>
            </tr>
            <tr class="report-footer">
                <th colspan="3"></th>
                <th class="is-end">
                    <span class="report-footer-meta">
                        Mazot:
                        {{ number_format($report['fuel_liters'], 2, ',', '.') }}
                        / {{ $report['fuel_receipts'] }}
                        / {{ $report['fuel_haul_tickets'] }}
                    </span>
                    @if ($report['machine_tickets'] > 0)
                        <span class="report-footer-meta">
                            Makine:
                            {{ number_format($report['machine_hours'], 2, ',', '.') }} saat
                            / {{ $report['machine_tickets'] }} fiş
                        </span>
                    @endif
                </th>
                <th colspan="3"></th>
            </tr>
            <tr class="report-footer">
                <th colspan="5"></th>
                <th class="is-end">Alacak :</th>
                <th class="panel-tutar is-end">{{ number_format($report['total_credit'], 2, ',', '.') }} TL</th>
            </tr>
            <tr class="report-footer">
                <th colspan="5"></th>
                <th class="is-end">Borç :</th>
                <th class="panel-tutar is-end">{{ number_format($report['total_debit'], 2, ',', '.') }} TL</th>
            </tr>
            <tr class="report-footer is-balance">
                <th colspan="5"></th>
                <th class="is-end">Bakiye :</th>
                <th class="panel-tutar is-end">{{ number_format($report['balance'], 2, ',', '.') }} TL</th>
            </tr>
        </tfoot>
    </x-table.list>

    <div class="panel-liste-bar report-actions">
        <x-ui.button
            icon="fa-solid fa-file-excel"
            :href="route('admin.reports.vehicle-monthly.export', $exportQuery)"
        >
            Excel
        </x-ui.button>
        <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
    </div>
</div>
