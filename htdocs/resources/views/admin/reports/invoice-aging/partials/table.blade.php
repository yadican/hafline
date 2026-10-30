@php
    $exportQuery = array_filter([
        'direction' => $filters['direction'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
    $labels = $report['bucket_labels'];
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    <x-table.list class="report-table invoice-aging-table">
        <thead>
            <tr class="report-col-head">
                <th>Cari</th>
                <th class="is-end">{{ $labels['current'] }}</th>
                <th class="is-end">{{ $labels['d0_30'] }}</th>
                <th class="is-end">{{ $labels['d31_60'] }}</th>
                <th class="is-end">{{ $labels['d61_90'] }}</th>
                <th class="is-end">{{ $labels['d90_plus'] }}</th>
                <th class="is-end">Toplam</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="panel-tutar is-end">{{ $row['current'] > 0 ? number_format($row['current'], 2, ',', '.') : '' }}</td>
                    <td class="panel-tutar is-end">{{ $row['d0_30'] > 0 ? number_format($row['d0_30'], 2, ',', '.') : '' }}</td>
                    <td class="panel-tutar is-end">{{ $row['d31_60'] > 0 ? number_format($row['d31_60'], 2, ',', '.') : '' }}</td>
                    <td class="panel-tutar is-end">{{ $row['d61_90'] > 0 ? number_format($row['d61_90'], 2, ',', '.') : '' }}</td>
                    <td class="panel-tutar is-end">{{ $row['d90_plus'] > 0 ? number_format($row['d90_plus'], 2, ',', '.') : '' }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['total'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <x-table.empty
                    colspan="7"
                    icon="fa-solid fa-hourglass-half"
                    title="Açık fatura yok."
                    text="Seçilen tür için kalan bakiyeli fatura bulunamadı."
                />
            @endforelse
        </tbody>
        @if ($report['rows'] !== [])
            <tfoot>
                <tr class="report-footer">
                    <th>Toplam</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['current'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['d0_30'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['d31_60'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['d61_90'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['d90_plus'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['totals']['total'], 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['rows'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.report-exports route="admin.reports.invoice-aging.export" :query="$exportQuery" />
        </div>
    @endif
</div>
