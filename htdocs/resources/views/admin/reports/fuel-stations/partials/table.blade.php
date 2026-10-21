@php
    $exportQuery = array_filter([
        'fuel_station' => $filters['fuel_station'] ?? null,
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'class' => $filters['class'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
    </div>

    <x-table.list class="report-table">
        <thead>
            <tr>
                <th class="is-center">Sıra</th>
                <th>Plaka / Araç</th>
                <th class="is-end">Litre</th>
                <th class="is-end">Birim Fiyat</th>
                <th class="is-end">Tutar</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    <td class="is-center">{{ $loop->iteration }}</td>
                    <td>{{ $row['plate'] }}</td>
                    <td class="is-end">{{ number_format($row['liters'], 2, ',', '.') }} lt</td>
                    <td class="is-end">{{ number_format($row['unit_price'], 2, ',', '.') }} TL</td>
                    <td class="panel-tutar">{{ number_format($row['amount'], 2, ',', '.') }} TL</td>
                </tr>
            @empty
                <x-table.empty
                    colspan="5"
                    icon="fa-solid fa-gas-pump"
                    title="Bu dönemde yakıt fişi yok."
                    text="Seçilen yakıt yeri, ay ve araç tipi için yakıt fişi bulunamadı."
                />
            @endforelse
        </tbody>
        @if ($report['rows'] !== [])
            <tfoot>
                <tr class="report-footer">
                    <th colspan="2">Toplam</th>
                    <th class="is-end">{{ number_format($report['total_liters'], 2, ',', '.') }} lt</th>
                    <th></th>
                    <th class="panel-tutar">{{ number_format($report['total_amount'], 2, ',', '.') }} TL</th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['rows'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.fuel-stations.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
