@php
    $exportQuery = array_filter([
        'period' => $filters['period'] ?? 'month',
        'year' => $filters['year'] ?? null,
        'month' => $filters['month'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'role' => $filters['role'] ?? null,
        'party' => $filters['party'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');

    $colspan = 6
        + ($report['show_fuel'] ? 3 : 0)
        + ($report['show_market'] ? 2 : 0);
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $report['title'] }}</h2>
        <p class="report-subtitle">{{ $report['subtitle'] }}</p>
    </div>

    @forelse ($report['groups'] as $group)
        <x-table.list class="report-table company-ledger-table mb-6">
            <thead>
                @if ($report['show_party_column'] || count($report['groups']) > 1)
                    <tr>
                        <th colspan="{{ $colspan }}" class="report-group-head">{{ $group['party_name'] }}</th>
                    </tr>
                @endif
                <tr class="report-col-head">
                    <th>Tarih</th>
                    <th>Belge No</th>
                    <th>Açıklama</th>
                    @if ($report['show_fuel'])
                        <th class="is-end">Litre</th>
                        <th class="is-end">Birim Fiyat</th>
                        <th>Yakıt Yeri</th>
                    @endif
                    @if ($report['show_market'])
                        <th>Plaka</th>
                        <th>Araç Sahibi</th>
                    @endif
                    <th class="is-end">Borç</th>
                    <th class="is-end">Alacak</th>
                    <th class="is-end">Bakiye</th>
                </tr>
            </thead>
            <tbody>
                <tr class="report-opening">
                    <td></td>
                    <td></td>
                    <td>Geçmiş Dönemler (Devir)</td>
                    @if ($report['show_fuel'])
                        <td></td>
                        <td></td>
                        <td></td>
                    @endif
                    @if ($report['show_market'])
                        <td></td>
                        <td></td>
                    @endif
                    <td class="panel-tutar is-end">
                        @if ($group['opening'] > 0)
                            {{ number_format($group['opening'], 2, ',', '.') }}
                        @endif
                    </td>
                    <td class="panel-tutar is-end">
                        @if ($group['opening'] < 0)
                            {{ number_format(abs($group['opening']), 2, ',', '.') }}
                        @endif
                    </td>
                    <td class="is-end"><x-ui.balance :value="$group['opening']" /></td>
                </tr>
                @foreach ($group['lines'] as $line)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($line['occurred_on'])->format('d.m.Y') }}</td>
                        <td>{{ $line['document_no'] ?? '—' }}</td>
                        <td>{{ $line['description'] ?? '—' }}</td>
                        @if ($report['show_fuel'])
                            <td class="panel-tutar is-end">
                                {{ $line['liters'] !== null ? number_format($line['liters'], 2, ',', '.') : '—' }}
                            </td>
                            <td class="panel-tutar is-end">
                                {{ $line['unit_price'] !== null ? number_format($line['unit_price'], 4, ',', '.') : '—' }}
                            </td>
                            <td>{{ $line['fuel_station_name'] ?? '—' }}</td>
                        @endif
                        @if ($report['show_market'])
                            <td>{{ $line['plate'] ?? '—' }}</td>
                            <td>{{ $line['owner_name'] ?? '—' }}</td>
                        @endif
                        <td class="panel-tutar is-end">
                            {{ $line['debit'] > 0 ? number_format($line['debit'], 2, ',', '.') : '' }}
                        </td>
                        <td class="panel-tutar is-end">
                            {{ $line['credit'] > 0 ? number_format($line['credit'], 2, ',', '.') : '' }}
                        </td>
                        <td class="is-end"><x-ui.balance :value="$line['balance']" /></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="report-footer">
                    <th colspan="{{ $colspan - 3 }}">Toplam / Kapanış</th>
                    <th class="panel-tutar is-end">{{ number_format($group['total_debit'], 2, ',', '.') }}</th>
                    <th class="panel-tutar is-end">{{ number_format($group['total_credit'], 2, ',', '.') }}</th>
                    <th class="is-end"><x-ui.balance :value="$group['closing']" /></th>
                </tr>
            </tfoot>
        </x-table.list>
    @empty
        <x-table.list class="report-table">
            <tbody>
                <x-table.empty
                    colspan="{{ $colspan }}"
                    icon="fa-solid fa-book"
                    title="Bu dönemde hareket yok."
                    text="Seçilen cari türü ve dönem için cari hareket bulunamadı."
                />
            </tbody>
        </x-table.list>
    @endforelse

    @if ($report['parties'] !== null)
        <div class="mt-4">
            {{ $report['parties']->links() }}
        </div>
    @endif

    @if ($report['groups'] !== [])
        <div class="panel-liste-bar report-actions">
            <x-ui.report-exports route="admin.reports.company-ledger.export" :query="$exportQuery" />
        </div>
    @endif
</div>
