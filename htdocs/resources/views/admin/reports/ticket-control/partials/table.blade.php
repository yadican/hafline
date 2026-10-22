@php
    $exportQuery = array_filter([
        'period' => $filters['period'] ?? 'month',
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'site' => $filters['site'] ?? null,
        'dump_site' => $filters['dump_site'] ?? null,
        'owner' => $filters['owner'] ?? null,
        'plate' => $filters['plate'] ?? null,
        'group' => $filters['group'] ?? 'none',
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <x-table.list class="report-table">
            <thead>
                <tr class="report-col-head">
                    <th colspan="2">Fiş Teslim</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['handovers'] as $row)
                    <tr>
                        <td>{{ $row['label'] ?? '' }}</td>
                        <td class="panel-tutar is-end">{{ $row['total'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="is-center text-muted">Kayıt yok</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="report-footer">
                    <th>Toplam</th>
                    <th class="is-end">{{ $report['handover_total'] }}</th>
                </tr>
            </tfoot>
        </x-table.list>

        <x-table.list class="report-table">
            <thead>
                <tr class="report-col-head">
                    <th colspan="2">Hareket</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['hauls'] as $row)
                    <tr>
                        <td>{{ $row['label'] ?? '' }}</td>
                        <td class="panel-tutar is-end">{{ $row['total'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="is-center text-muted">Kayıt yok</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="report-footer">
                    <th>Toplam</th>
                    <th class="is-end">{{ $report['haul_total'] }}</th>
                </tr>
            </tfoot>
        </x-table.list>

        <x-table.list class="report-table">
            <thead>
                <tr class="report-col-head">
                    <th colspan="2">Yakıt</th>
                </tr>
            </thead>
            <tbody>
                @if ($report['fuel_na'])
                    <tr>
                        <td colspan="2" class="is-center text-muted">Döküm yeri için uygulanamaz</td>
                    </tr>
                @else
                    @forelse ($report['fuels'] as $row)
                        <tr>
                            <td>{{ $row['label'] ?? '' }}</td>
                            <td class="panel-tutar is-end">{{ $row['total'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="is-center text-muted">Kayıt yok</td>
                        </tr>
                    @endforelse
                @endif
            </tbody>
            @unless ($report['fuel_na'])
                <tfoot>
                    <tr class="report-footer">
                        <th>Toplam</th>
                        <th class="is-end">{{ $report['fuel_total'] }}</th>
                    </tr>
                </tfoot>
            @endunless
        </x-table.list>
    </div>

    <div class="panel-liste-bar report-actions">
        <x-ui.button
            icon="fa-solid fa-file-excel"
            :href="route('admin.reports.ticket-control.export', $exportQuery)"
        >
            Excel
        </x-ui.button>
        <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
    </div>
</div>
