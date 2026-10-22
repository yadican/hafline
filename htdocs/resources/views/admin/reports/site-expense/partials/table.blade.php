@php
    $exportQuery = array_filter([
        'period' => $filters['period'] ?? 'month',
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'site' => $filters['site'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    <x-table.list class="report-table site-expense-table">
        <thead>
            <tr class="report-col-head">
                <th>Tarih</th>
                <th>Açıklama</th>
                <th>Ödeme Tipi</th>
                <th class="is-end">Borç</th>
                <th class="is-end">Alacak</th>
                <th class="is-end">Bakiye</th>
            </tr>
        </thead>
        <tbody>
            <tr class="report-opening">
                <td></td>
                <td>Geçmiş Dönemler (Devir)</td>
                <td></td>
                <td class="panel-tutar is-end">
                    @if ($report['opening'] > 0)
                        {{ number_format($report['opening'], 2, ',', '.') }}
                    @endif
                </td>
                <td class="panel-tutar is-end">
                    @if ($report['opening'] < 0)
                        {{ number_format(abs($report['opening']), 2, ',', '.') }}
                    @endif
                </td>
                <td class="is-end"><x-ui.balance :value="$report['opening']" /></td>
            </tr>
            @forelse ($report['lines'] as $line)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($line['occurred_on'])->format('d.m.Y') }}</td>
                    <td>{{ $line['description'] ?? '—' }}</td>
                    <td>{{ $line['payment_method'] ?? '—' }}</td>
                    <td class="panel-tutar is-end">
                        {{ $line['debit'] > 0 ? number_format($line['debit'], 2, ',', '.') : '' }}
                    </td>
                    <td class="panel-tutar is-end">
                        {{ $line['credit'] > 0 ? number_format($line['credit'], 2, ',', '.') : '' }}
                    </td>
                    <td class="is-end"><x-ui.balance :value="$line['balance']" /></td>
                </tr>
            @empty
                @if ($report['opening'] == 0)
                    <x-table.empty
                        colspan="6"
                        icon="fa-solid fa-file-invoice-dollar"
                        title="Bu dönemde hareket yok."
                        text="Seçilen şantiye ve dönem için gelir / gider kaydı bulunamadı."
                    />
                @endif
            @endforelse
        </tbody>
        @if ($report['lines'] !== [] || $report['opening'] != 0)
            <tfoot>
                <tr class="report-footer">
                    <th colspan="3">Toplam / Kapanış</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_debit'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_credit'], 2, ',', '.') }}</th>
                    <th class="is-end"><x-ui.balance :value="$report['closing']" /></th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['lines'] !== [] || $report['opening'] != 0)
        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.site-expense.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
