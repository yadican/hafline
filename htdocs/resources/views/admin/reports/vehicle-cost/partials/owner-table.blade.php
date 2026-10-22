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

    <x-table.list class="report-table vehicle-cost-owner-table">
        <thead>
            <tr class="report-col-head">
                <th>Plaka Sahibi</th>
                <th class="is-end">Gelir</th>
                <th class="is-end">Yakıt</th>
                <th class="is-end">Piyasa</th>
                <th class="is-end">Durum</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    <td>
                        <a href="{{ route('admin.reports.vehicle-cost', array_filter([
                            'owner' => $row['owner'],
                            'period' => $filters['period'] ?? 'month',
                            'year' => $filters['year'] ?? null,
                            'month' => $filters['month'] ?? null,
                            'from' => $filters['from'] ?? null,
                            'to' => $filters['to'] ?? null,
                        ], fn ($value) => $value !== null && $value !== '')) }}">
                            {{ $row['owner_name'] }}
                        </a>
                    </td>
                    <td class="panel-tutar is-end">{{ number_format($row['gelir'], 2, ',', '.') }} TL</td>
                    <td class="panel-tutar is-end">{{ number_format($row['yakit'], 2, ',', '.') }} TL</td>
                    <td class="panel-tutar is-end">{{ number_format($row['piyasa'], 2, ',', '.') }} TL</td>
                    <td class="panel-tutar is-end">{{ number_format($row['durum'], 2, ',', '.') }} TL</td>
                </tr>
            @empty
                <x-table.empty
                    colspan="5"
                    icon="fa-solid fa-users"
                    title="Bu dönemde hareket yok."
                    text="Seçilen dönem için plaka sahibi maliyeti bulunamadı."
                />
            @endforelse
        </tbody>
        @if ($report['rows'] !== [])
            <tfoot>
                <tr class="report-footer">
                    <th>{{ $report['owner_count'] }} sahip</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_gelir'], 2, ',', '.') }} TL</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_yakit'], 2, ',', '.') }} TL</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_piyasa'], 2, ',', '.') }} TL</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_durum'], 2, ',', '.') }} TL</th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['rows'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.vehicle-cost.owners.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
