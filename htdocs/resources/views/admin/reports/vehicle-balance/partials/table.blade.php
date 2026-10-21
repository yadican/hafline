@php
    $exportQuery = array_filter([
        'period' => $filters['period'] ?? 'month',
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'site' => $filters['site'] ?? null,
        'fuel_station' => $filters['fuel_station'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    <x-table.list class="report-table vehicle-balance-table">
        <thead>
            <tr class="report-col-head">
                <th>Plaka</th>
                <th class="is-end">Alacak</th>
                <th class="is-end">Elden</th>
                <th class="is-end">Borç</th>
                <th class="is-end">{{ $report['balance_label'] }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    <td>{{ $row['plate'] }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['credit'], 2, ',', '.') }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['cash'], 2, ',', '.') }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['debit'], 2, ',', '.') }}</td>
                    <td class="is-end"><x-ui.balance :value="$row['balance']" /></td>
                </tr>
            @empty
                <x-table.empty
                    colspan="5"
                    icon="fa-solid fa-scale-balanced"
                    title="Bu dönemde hareket yok."
                    text="Seçilen dönem ve filtreler için plaka bakiyesi bulunamadı."
                />
            @endforelse
        </tbody>
        @if ($report['rows'] !== [])
            <tfoot>
                <tr class="report-footer">
                    <th>Toplam</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_credit'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_cash'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_debit'], 2, ',', '.') }}</th>
                    <th class="is-end"><x-ui.balance :value="$report['total_balance']" /></th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['rows'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.vehicle-balance.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
