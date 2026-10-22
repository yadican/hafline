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

    <x-table.list class="report-table cash-movements-table">
        <thead>
            <tr class="report-col-head">
                <th>Tarih</th>
                <th>İşlem Tipi</th>
                <th>Açıklama</th>
                <th>Ödeme Tipi</th>
                <th class="is-end">Giriş</th>
                <th class="is-end">Çıkış</th>
                <th class="is-end">Bakiye</th>
            </tr>
        </thead>
        <tbody>
            <tr class="report-opening">
                <td></td>
                <td>Devir</td>
                <td></td>
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
                <td class="panel-tutar is-end">{{ number_format($report['opening'], 2, ',', '.') }}</td>
            </tr>
            @forelse ($report['lines'] as $line)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($line['occurred_on'])->format('d.m.Y') }}</td>
                    <td>{{ $line['kind_label'] }}</td>
                    <td>{{ $line['description'] ?? '—' }}</td>
                    <td>{{ $line['payment_label'] }}</td>
                    <td class="panel-tutar is-end">
                        {{ $line['giris'] > 0 ? number_format($line['giris'], 2, ',', '.') : '' }}
                    </td>
                    <td class="panel-tutar is-end">
                        {{ $line['cikis'] > 0 ? number_format($line['cikis'], 2, ',', '.') : '' }}
                    </td>
                    <td class="panel-tutar is-end">{{ number_format($line['balance'], 2, ',', '.') }}</td>
                </tr>
            @empty
                @if ($report['opening'] == 0)
                    <x-table.empty
                        colspan="7"
                        icon="fa-solid fa-cash-register"
                        title="Bu dönemde hareket yok."
                        text="Seçilen dönem için kasa hareketi bulunamadı."
                    />
                @endif
            @endforelse
        </tbody>
        @if ($report['lines'] !== [] || $report['opening'] != 0)
            <tfoot>
                <tr class="report-footer">
                    <th colspan="4">Toplam / Kapanış</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_giris'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['total_cikis'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($report['closing'], 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        @endif
    </x-table.list>

    @if ($report['lines'] !== [] || $report['opening'] != 0)
        <div class="panel-liste-bar report-actions">
            <x-ui.button
                icon="fa-solid fa-file-excel"
                :href="route('admin.reports.cash-movements.export', $exportQuery)"
            >
                Excel
            </x-ui.button>
            <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
        </div>
    @endif
</div>
