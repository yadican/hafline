@php
    $exportQuery = array_filter([
        'fuel_station' => $filters['fuel_station'] ?? null,
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'day_from' => $filters['day_from'] ?? null,
        'day_to' => $filters['day_to'] ?? null,
        'class' => $filters['class'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
    $colspan = 2 + count($report['days']);
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    <x-table.list class="report-table report-matrix-table fuel-station-monthly-table">
        <thead>
            <tr class="report-col-head">
                <th>Plaka</th>
                @foreach ($report['days'] as $day)
                    <th class="is-center">{{ $day }}</th>
                @endforeach
                <th class="is-end">Toplam</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    <td>{{ $row['plate'] }}</td>
                    @foreach ($report['days'] as $day)
                        @php $liters = $row['liters'][$day] ?? 0; @endphp
                        <td class="is-center">
                            {{ $liters > 0 ? number_format($liters, 2, ',', '.') : '-' }}
                        </td>
                    @endforeach
                    <td class="is-end">{{ number_format($row['total'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <x-table.empty
                    :colspan="$colspan"
                    icon="fa-solid fa-gas-pump"
                    title="Bu aralıkta yakıt fişi yok."
                    text="Seçilen yakıt yeri, ay ve gün aralığında fiş bulunamadı."
                />
            @endforelse
        </tbody>
        @if ($report['rows'] !== [])
            <tfoot>
                <tr class="report-footer">
                    <th>Toplam</th>
                    @foreach ($report['days'] as $day)
                        <th class="is-center">{{ number_format($report['day_totals'][$day] ?? 0, 2, ',', '.') }}</th>
                    @endforeach
                    <th class="is-end">{{ number_format($report['grand_total'], 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['rows'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.fuel-stations-monthly.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
