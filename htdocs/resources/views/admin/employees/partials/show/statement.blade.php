@php
    $months = \App\Services\Reports\SiteExitReportService::MONTHS;
    $from = $statement['from'];
    $to = now();
@endphp

<div class="panel-form-grid panel-form-grid-after" id="maas-dokumu">
    <x-form.group
        legend="Maaş dökümü"
        icon="fa-solid fa-money-check-dollar"
        :description="'Son 6 ay · '.$months[$from->month].' '.$from->year.' – '.$months[$to->month].' '.$to->year"
        class="is-wide"
    >
        <div class="panel-ledger-totals">
            <span>Maaş <x-ui.money :value="$statement['totals']['salary']" /></span>
            <span>Prim <x-ui.money :value="$statement['totals']['bonus']" /></span>
            <span>Ödeme <x-ui.money :value="$statement['totals']['payment']" /></span>
            <span>Bakiye <x-ui.balance :value="$statement['totals']['closing']" /></span>
        </div>

        <table class="panel-subtable">
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Dönem</th>
                    <th>Açıklama</th>
                    <th class="is-end">Maaş / Prim</th>
                    <th class="is-end">Ödeme</th>
                    <th class="is-end">Bakiye</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($statement['lines'] as $line)
                    <tr>
                        <td>{{ $line['date'] ? \Illuminate\Support\Carbon::parse($line['date'])->format('d.m.Y') : '' }}</td>
                        <td>{{ $line['period'] }}</td>
                        <td>{{ $line['description'] }}</td>
                        <td class="is-end">{{ $line['salary'] > 0 ? number_format($line['salary'], 2, ',', '.') : '' }}</td>
                        <td class="is-end">{{ $line['payment'] > 0 ? number_format($line['payment'], 2, ',', '.') : '' }}</td>
                        <td class="is-end"><x-ui.balance :value="$line['balance']" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-form.group>
</div>
