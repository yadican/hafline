@php
    $exportQuery = array_filter([
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

    <x-table.list class="report-table site-cari-table">
        <thead>
            <tr class="report-col-head">
                <th>Şantiye</th>
                <th class="is-end">Döküm</th>
                <th class="is-end">Nakliye</th>
                <th class="is-end">Nak. Fiş</th>
                <th class="is-end">Yakıt</th>
                <th class="is-end">Yak. Fiş</th>
                <th class="is-end">Diğer</th>
                <th class="is-end">Hakediş</th>
                <th class="is-end">Durum</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['dump'], 2, ',', '.') }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['haul'], 2, ',', '.') }}</td>
                    <td class="is-end">{{ $row['haul_count'] }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['fuel'], 2, ',', '.') }}</td>
                    <td class="is-end">{{ $row['fuel_count'] }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['other'], 2, ',', '.') }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['earned'], 2, ',', '.') }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['status'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <x-table.empty
                    colspan="9"
                    icon="fa-solid fa-helmet-safety"
                    title="Bu dönemde hareket yok."
                    text="Seçilen dönem için şantiye cari kaydı bulunamadı."
                />
            @endforelse
        </tbody>
        @if ($report['rows'] !== [])
            <tfoot>
                <tr class="report-footer">
                    <th>Toplam</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['dump'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['haul'], 2, ',', '.') }}</th>
                    <th class="is-end">{{ $report['totals']['haul_count'] }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['fuel'], 2, ',', '.') }}</th>
                    <th class="is-end">{{ $report['totals']['fuel_count'] }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['other'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['earned'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['status'], 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['rows'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.site-cari.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
