@php
    $exportQuery = array_filter([
        'owner' => $filters['owner'] ?? null,
        'period' => $filters['period'] ?? 'month',
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    @forelse ($report['vehicles'] as $vehicle)
        <x-table.list class="report-table owner-periodic-table mb-4">
            <thead>
                <tr class="report-vehicle-head">
                    <th colspan="5" class="report-vehicle-plate">{{ $vehicle['plate'] }}</th>
                </tr>
                <tr class="report-col-head">
                    <th class="is-center">Sıra</th>
                    <th>Açıklama</th>
                    <th class="is-end">Döküm Sayısı</th>
                    <th class="is-end">Toplam Lt</th>
                    <th class="is-end">Toplam Ton</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle['rows'] as $row)
                    <tr>
                        <td class="is-center">{{ $loop->iteration }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="is-end">{{ $row['trips'] ?? '' }}</td>
                        <td class="is-end">
                            {{ $row['liters'] !== null ? number_format($row['liters'], 2, ',', '.') : '' }}
                        </td>
                        <td class="is-end">
                            {{ $row['tonnage'] !== null ? number_format($row['tonnage'], 2, ',', '.') : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="report-footer">
                    <th></th>
                    <th>Toplam</th>
                    <th class="is-end">{{ $vehicle['total_trips'] }}</th>
                    <th class="is-end">{{ number_format($vehicle['total_liters'], 2, ',', '.') }}</th>
                    <th class="is-end">{{ number_format($vehicle['total_tonnage'], 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        </x-table.list>
    @empty
        <x-table.list class="report-table">
            <tbody>
                <x-table.empty
                    colspan="5"
                    icon="fa-solid fa-chart-simple"
                    title="Bu dönemde hareket yok."
                    text="Seçilen plaka sahibi ve dönem için taşıma veya yakıt fişi bulunamadı."
                />
            </tbody>
        </x-table.list>
    @endforelse

    @if ($report['vehicles'] !== [])
        <x-table.list class="report-table owner-periodic-table">
            <tfoot>
                <tr class="report-footer">
                    <th colspan="2">Genel Toplam</th>
                    <th class="is-end">{{ $report['total_trips'] }}</th>
                    <th class="is-end">{{ number_format($report['total_liters'], 2, ',', '.') }}</th>
                    <th class="is-end">{{ number_format($report['total_tonnage'], 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        </x-table.list>

        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.owner-periodic.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
