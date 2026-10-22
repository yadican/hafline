@php
    $exportQuery = array_filter([
        'period' => $filters['period'] ?? 'month',
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'dump_site' => $filters['dump_site'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    <x-table.list class="report-table dump-site-payment-table">
        <thead>
            <tr class="report-col-head">
                <th>Araç Tipi</th>
                <th class="is-end">Sefer Sayısı</th>
                <th class="is-end">Tonaj</th>
                <th class="is-end">Toplam Fiyat</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['dump_rows'] as $row)
                <tr>
                    <td>{{ $row['class_name'] }}</td>
                    <td class="is-end">{{ $row['trips'] }}</td>
                    <td class="is-end">{{ number_format($row['tonnage'], 2, ',', '.') }}</td>
                    <td class="panel-tutar is-end">{{ number_format($row['amount'], 2, ',', '.') }} TL</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="is-center text-muted">Bu dönemde döküm yok.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="report-footer">
                <th>Toplam Döküm</th>
                <th class="is-end">{{ $report['total_trips'] }}</th>
                <th class="is-end">{{ number_format($report['total_tonnage'], 2, ',', '.') }}</th>
                <th class="panel-tutar is-end">{{ number_format($report['total_dump_amount'], 2, ',', '.') }} TL</th>
            </tr>
        </tfoot>
    </x-table.list>

    <x-table.list class="report-table dump-site-payment-table mt-4">
        <thead>
            <tr class="report-col-head">
                <th>Ödeme Açıklama</th>
                <th>Ödeme Tarih</th>
                <th>Ödeme Tipi</th>
                <th class="is-end">Ödeme Miktarı</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['payments'] as $payment)
                <tr>
                    <td>{{ $payment['description'] ?? '—' }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($payment['occurred_on'])->format('d.m.Y') }}</td>
                    <td>{{ $payment['payment_method'] }}</td>
                    <td class="panel-tutar is-end">{{ number_format($payment['amount'], 2, ',', '.') }} TL</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="is-center text-muted">Bu dönemde ödeme yok.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="report-footer">
                <th colspan="3">Toplam Ödeme</th>
                <th class="panel-tutar is-end">{{ number_format($report['total_payment_amount'], 2, ',', '.') }} TL</th>
            </tr>
            <tr class="report-footer">
                <th>Devir</th>
                <th class="is-end">Döküm : {{ number_format($report['opening_dump'], 2, ',', '.') }} TL</th>
                <th class="is-end">Ödeme : {{ number_format($report['opening_payment'], 2, ',', '.') }} TL</th>
                <th class="panel-tutar is-end">{{ number_format($report['opening'], 2, ',', '.') }} TL</th>
            </tr>
            <tr class="report-footer">
                <th colspan="3">Bakiye</th>
                <th class="panel-tutar is-end">{{ number_format($report['balance'], 2, ',', '.') }} TL</th>
            </tr>
        </tfoot>
    </x-table.list>

    <div class="panel-liste-bar report-actions">
        <x-ui.button
            icon="fa-solid fa-file-excel"
            :href="route('admin.reports.dump-site-payments.export', $exportQuery)"
        >
            Excel
        </x-ui.button>
        <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
    </div>
</div>
