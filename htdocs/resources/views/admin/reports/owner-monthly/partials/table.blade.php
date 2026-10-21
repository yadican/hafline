@php
    $exportQuery = array_filter([
        'owner' => $filters['owner'] ?? $report['owner'] ?? null,
        'period' => $filters['period'] ?? 'month',
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');

    $rowIndex = 0;
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['owner_name'] }} · {{ $report['period_label'] }}</p>
    </div>

    <x-table.list class="report-table owner-monthly-table">
        <tbody>
            @forelse ($report['vehicles'] as $vehicle)
                @unless ($loop->first)
                    <tr class="report-vehicle-gap" aria-hidden="true">
                        <td colspan="7"></td>
                    </tr>
                @endunless
                <tr class="report-vehicle-head">
                    <th colspan="3" class="report-vehicle-plate">{{ $vehicle['plate'] }}</th>
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

                @forelse ($vehicle['rows'] as $row)
                    @php $rowIndex++; @endphp
                    <tr>
                        <td class="is-center">{{ $rowIndex }}</td>
                        <td>{{ $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->format('d.m.Y') : '' }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="is-end">
                            @if ($row['quantity'] !== null)
                                @if ($row['quantity_unit'] === 'lt')
                                    {{ number_format($row['quantity'], 2, ',', '.') }} lt
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
                        <td colspan="7" class="is-center report-empty-note">Bu plakada dönem hareketi yok.</td>
                    </tr>
                @endforelse

                <tr class="report-vehicle-subtotal">
                    <td></td>
                    <td></td>
                    <td>
                        Sefer:{{ $vehicle['trips'] }}
                        Yakıt:{{ number_format($vehicle['fuel_liters'], 2, ',', '.') }}lt
                        {{ $vehicle['fuel_receipts'] }} yakıt fişi
                        {{ $vehicle['fuel_haul_tickets'] }} hareket fişi
                    </td>
                    <td></td>
                    <td></td>
                    <td class="panel-tutar is-end">{{ number_format($vehicle['total_debit'], 2, ',', '.') }} TL</td>
                    <td class="panel-tutar is-end">{{ number_format($vehicle['total_credit'], 2, ',', '.') }} TL</td>
                </tr>
            @empty
                @unless (count($report['owner_payments']) > 0)
                    <tr>
                        <td colspan="7" class="is-center report-empty-note">Bu dönemde araç hareketi yok.</td>
                    </tr>
                @endunless
            @endforelse

            @if (count($report['owner_payments']) > 0)
                @if (count($report['vehicles']) > 0)
                    <tr class="report-vehicle-gap" aria-hidden="true">
                        <td colspan="7"></td>
                    </tr>
                @endif
                <tr class="report-section-head">
                    <th colspan="3" class="report-vehicle-plate">Plaka sahibi</th>
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

                @foreach ($report['owner_payments'] as $row)
                    @php $rowIndex++; @endphp
                    <tr>
                        <td class="is-center">{{ $rowIndex }}</td>
                        <td>{{ $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->format('d.m.Y') : '' }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td></td>
                        <td></td>
                        <td class="panel-tutar is-end">
                            @if ($row['debit'] !== null)
                                {{ number_format($row['debit'], 2, ',', '.') }} TL
                            @endif
                        </td>
                        <td></td>
                    </tr>
                @endforeach
            @endif

            <tr class="report-carryover">
                <td></td>
                <td></td>
                <td>{{ $report['carryover']['description'] }}</td>
                <td></td>
                <td></td>
                <td class="panel-tutar is-end">
                    @if ($report['carryover']['debit'] !== null)
                        {{ number_format($report['carryover']['debit'], 2, ',', '.') }} TL
                    @endif
                </td>
                <td class="panel-tutar is-end">
                    @if ($report['carryover']['credit'] !== null)
                        {{ number_format($report['carryover']['credit'], 2, ',', '.') }} TL
                    @endif
                </td>
            </tr>
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
            :href="route('admin.reports.owner-monthly.export', $exportQuery)"
        >
            Excel
        </x-ui.button>
        <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
    </div>
</div>
