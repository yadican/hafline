@php
    $exportQuery = [
        'employee_id' => $filters['employee_id'],
        'year' => $filters['year'],
    ];
    $info = $report['employee'];
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
        <p class="report-subtitle">
            {{ $info['occupation'] ?: '—' }}
            · İşe başlama: {{ $info['started_on'] ? \Illuminate\Support\Carbon::parse($info['started_on'])->format('d.m.Y') : '—' }}
            · {{ $info['mobile'] ?: $info['phone'] ?: '—' }}
        </p>
        @if ($info['address'])
            <p class="report-subtitle">{{ $info['address'] }}</p>
        @endif
    </div>

    <x-table.list class="report-table">
        <thead>
            <tr class="report-col-head">
                <th>Tarih</th>
                <th>Dönem</th>
                <th>Açıklama</th>
                <th class="is-end">Maaş / Prim</th>
                <th class="is-end">Ödeme</th>
                <th class="is-end">Devir</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['lines'] as $line)
                <tr>
                    <td>{{ $line['date'] ? \Illuminate\Support\Carbon::parse($line['date'])->format('d.m.Y') : '—' }}</td>
                    <td>{{ $line['period'] ?: '—' }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="panel-tutar is-end">{{ $line['salary'] > 0 ? number_format($line['salary'], 2, ',', '.') : '' }}</td>
                    <td class="panel-tutar is-end">{{ $line['payment'] > 0 ? number_format($line['payment'], 2, ',', '.') : '' }}</td>
                    <td class="is-end"><x-ui.balance :value="$line['balance']" /></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="report-footer">
                <th colspan="3">
                    Toplam maaş {{ number_format($report['totals']['salary'], 2, ',', '.') }}
                    · Prim {{ number_format($report['totals']['bonus'], 2, ',', '.') }}
                    · Ödeme {{ number_format($report['totals']['payment'], 2, ',', '.') }}
                </th>
                <th class="panel-tutar is-end">{{ number_format($report['totals']['salary'] + $report['totals']['bonus'], 2, ',', '.') }}</th>
                <th class="panel-tutar is-end">{{ number_format($report['totals']['payment'], 2, ',', '.') }}</th>
                <th class="is-end"><x-ui.balance :value="$report['totals']['closing']" /></th>
            </tr>
        </tfoot>
    </x-table.list>

    <div class="panel-liste-bar report-actions">
        <x-ui.button icon="fa-solid fa-file-excel" :href="route('admin.reports.employee-annual.export', $exportQuery)">
            Excel
        </x-ui.button>
        <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
    </div>
</div>
