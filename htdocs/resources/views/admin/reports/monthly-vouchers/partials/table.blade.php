@php
    $exportQuery = array_filter([
        'orientation' => $filters['orientation'] ?? 'site_dump',
        'route' => $filters['route'] ?? null,
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'day_from' => $filters['day_from'] ?? null,
        'day_to' => $filters['day_to'] ?? null,
        'class' => $filters['class'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
    $colspan = 2 + count($report['days']) + ($report['show_class'] ? 1 : 0);
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    <x-table.list class="report-table report-matrix-table monthly-voucher-table">
        <thead>
            <tr class="report-col-head">
                <th>Plaka</th>
                @if ($report['show_class'])
                    <th>Araç Sınıfı</th>
                @endif
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
                    @if ($report['show_class'])
                        <td>{{ $row['class_name'] }}</td>
                    @endif
                    @foreach ($report['days'] as $day)
                        @php $count = $row['counts'][$day] ?? 0; @endphp
                        <td class="is-center">{{ $count > 0 ? $count : '-' }}</td>
                    @endforeach
                    <td class="is-end">{{ $row['total'] }}</td>
                </tr>
            @empty
                <x-table.empty
                    :colspan="$colspan"
                    icon="fa-solid fa-calendar-days"
                    title="Bu aralıkta taşıma yok."
                    text="Seçilen güzergah, ay ve gün aralığında taşıma bulunamadı."
                />
            @endforelse
        </tbody>
        @if ($report['rows'] !== [])
            <tfoot>
                <tr class="report-footer">
                    <th>Toplam</th>
                    @if ($report['show_class'])
                        <th></th>
                    @endif
                    @foreach ($report['days'] as $day)
                        <th class="is-center">{{ $report['day_totals'][$day] ?? 0 }}</th>
                    @endforeach
                    <th class="is-end">{{ $report['grand_total'] }}</th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['rows'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.monthly-vouchers.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
