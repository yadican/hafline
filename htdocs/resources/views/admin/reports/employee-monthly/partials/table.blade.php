@php
    $exportQuery = [
        'year' => $filters['year'],
        'month' => $filters['month'],
    ];
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    <x-table.list class="report-table">
        <thead>
            <tr class="report-col-head">
                <th>Personel</th>
                <th class="is-end">Dönem maaşı</th>
                <th class="is-end">Prim</th>
                <th class="is-end">Önceki devir</th>
                <th class="is-end">Ödenen</th>
                <th class="is-end">Kalan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['earned'], 2, ',', '.') }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['bonus'], 2, ',', '.') }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['carry'], 2, ',', '.') }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['paid'], 2, ',', '.') }}</td>
                    <td class="is-end"><x-ui.balance :value="$row['remaining']" /></td>
                </tr>
            @empty
                <x-table.empty
                    colspan="6"
                    icon="fa-solid fa-chart-column"
                    title="Bu dönemde hareket yok."
                    text="Seçilen ay için personel kaydı bulunamadı."
                />
            @endforelse
        </tbody>
        @if ($report['rows'] !== [])
            <tfoot>
                <tr class="report-footer">
                    <th>Toplam</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['earned'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['bonus'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['carry'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['paid'], 2, ',', '.') }}</th>
                    <th class="is-end"><x-ui.balance :value="$report['totals']['remaining']" /></th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['rows'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.button icon="fa-solid fa-file-excel" :href="route('admin.reports.employee-monthly.export', $exportQuery)">
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
