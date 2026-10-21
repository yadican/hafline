@php
    $exportQuery = array_filter([
        'dump_site' => $filters['dump_site'] ?? null,
        'period' => $filters['period'] ?? 'month',
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'class' => $filters['class'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $matrix['title'] }}</h2>
        <p class="report-subtitle">{{ $matrix['subtitle'] }}</p>
    </div>

    <x-table.list class="report-table report-matrix-table">
        <thead>
            <tr class="report-col-head">
                <th>Plaka</th>
                @foreach ($matrix['columns'] as $column)
                    <th class="is-center">{{ $column['label'] }}</th>
                @endforeach
                <th class="is-center">Toplam</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($matrix['rows'] as $row)
                <tr>
                    <td>{{ $row['plate'] }}</td>
                    @foreach ($matrix['columns'] as $column)
                        @php $count = $row['counts'][$column['id']] ?? 0; @endphp
                        <td class="is-center">{{ $count > 0 ? $count : '-' }}</td>
                    @endforeach
                    <td class="is-center report-total">{{ $row['total'] }}</td>
                </tr>
            @empty
                <x-table.empty
                    :colspan="count($matrix['columns']) + 2"
                    icon="fa-solid fa-chart-column"
                    title="Bu dönemde sefer yok."
                    text="Seçilen döküm yeri, dönem ve plaka tipi için taşıma fişi bulunamadı."
                />
            @endforelse
        </tbody>
        @if ($matrix['rows'] !== [])
            <tfoot>
                <tr class="report-footer">
                    <th>Toplam</th>
                    @foreach ($matrix['columns'] as $column)
                        <th class="is-center">{{ $matrix['column_totals'][$column['id']] ?? 0 }}</th>
                    @endforeach
                    <th class="is-center">{{ $matrix['grand_total'] }}</th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($matrix['rows'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.dump-sites.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
