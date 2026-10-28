<div class="panel-form-grid panel-form-grid-after" id="cari-hareketleri">
    <x-form.group legend="Cari hareketleri" icon="fa-solid fa-scale-balanced" description="Borç: cari bize borçlu / biz ödedik · Alacak: biz cariye borçluyuz" class="is-wide">
        @if (count($moduleTotals) > 1 || $module)
            <nav class="panel-ledger-modules" aria-label="Modüle göre hareketler">
                <a href="{{ request()->fullUrlWithQuery(['modul' => null, 'hareket' => null]) }}#cari-hareketleri" @class(['panel-chip', 'is-active' => $module === null])>Tümü</a>
                @foreach ($moduleTotals as $row)
                    <a href="{{ request()->fullUrlWithQuery(['modul' => $row['module']->value, 'hareket' => null]) }}#cari-hareketleri" @class(['panel-chip', 'is-active' => $module === $row['module']])>
                        {{ $row['module']->label() }} <x-ui.balance :value="$row['balance']" />
                    </a>
                @endforeach
            </nav>
        @endif
        <div class="panel-ledger-totals">
            <span>Toplam borç <x-ui.money :value="$totals['debit']" /></span>
            <span>Toplam alacak <x-ui.money :value="$totals['credit']" /></span>
            <span>Bakiye <x-ui.balance :value="$totals['balance']" /></span>
            @if ($hasReversals)
                <a href="{{ request()->fullUrlWithQuery(['iptaller' => $withReversals ? null : 1, 'hareket' => null]) }}#cari-hareketleri">
                    {{ $withReversals ? 'İptal edilen kayıtları gizle' : 'İptal edilen kayıtları göster' }}
                </a>
            @endif
        </div>

        @if ($entries->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Açıklama</th>
                        <th>Plaka</th>
                        <th class="is-end">Borç</th>
                        <th class="is-end">Alacak</th>
                        <th class="is-end">Bakiye</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr @class(['is-reversal' => $entry->reversal_of !== null])>
                            <td>{{ $entry->occurred_on->format('d.m.Y') }}</td>
                            <td>
                                @if ($entry->source_type === 'haul' && $entry->source && ! $entry->source->trashed())
                                    <a href="{{ route('admin.hauls.show', $entry->source) }}">{{ $entry->description }}</a>
                                @elseif ($entry->source_type === 'machine_work' && $entry->source && ! $entry->source->trashed())
                                    <a href="{{ route('admin.machine-works.show', $entry->source) }}">{{ $entry->description }}</a>
                                @elseif ($entry->source_type === 'fuel_receipt' && $entry->source && ! $entry->source->trashed())
                                    <a href="{{ route('admin.fuel-receipts.show', $entry->source) }}">{{ $entry->description }}</a>
                                @elseif ($entry->source_type === 'shipment' && $entry->source && ! $entry->source->trashed())
                                    <a href="{{ route('admin.shipments.show', $entry->source) }}">{{ $entry->description }}</a>
                                @elseif ($entry->source_type === 'fuel_tank_fill' && $entry->source && ! $entry->source->trashed())
                                    <a href="{{ route('admin.fuel-stations.show', $entry->source->fuel_station_id) }}#depo">{{ $entry->description }}</a>
                                @elseif (in_array($entry->source_type, ['company_transaction', 'vehicle_payment', 'vehicle_receivable', 'party_payment', 'dump_site_payment', 'opening_balance', 'party_transfer', 'site_bill', 'site_transaction'], true) && $entry->source && ! $entry->source->trashed())
                                    @php
                                        $cariKind = match ($entry->source_type) {
                                            'company_transaction' => $entry->source->role->value,
                                            'party_payment' => 'owner_payment',
                                            'dump_site_payment' => 'dump_payment',
                                            'opening_balance' => 'opening',
                                            'site_transaction' => 'site',
                                            default => $entry->source_type,
                                        };
                                    @endphp
                                    <a href="{{ route('admin.cari-entries.show', ['kind' => $cariKind, 'entry' => $entry->source_id]) }}">{{ $entry->description }}</a>
                                @elseif ($entry->source_type === 'employee_payment' && $entry->source && ! $entry->source->trashed())
                                    <a href="{{ route('admin.employee-payments.show', $entry->source) }}">{{ $entry->description }}</a>
                                @elseif ($entry->source_type === 'employee_bonus' && $entry->source && ! $entry->source->trashed())
                                    <a href="{{ route('admin.employee-bonuses.show', $entry->source) }}">{{ $entry->description }}</a>
                                @else
                                    {{ $entry->description }}
                                @endif
                                @if ($entry->payment_method)
                                    <span class="panel-chip"><i class="{{ $entry->payment_method->icon() }}" aria-hidden="true"></i> {{ $entry->payment_method->label() }}</span>
                                @endif
                                @if ($entry->cashCategory)
                                    <span class="panel-chip">{{ $entry->cashCategory->name }}</span>
                                @endif
                                @if ($entry->reversal_of)
                                    <span class="panel-chip">Ters kayıt</span>
                                @endif
                            </td>
                            <td><span class="panel-plate-text">{{ $entry->vehicle?->plate ?? '' }}</span></td>
                            <td class="is-end">{{ (float) $entry->debit ? number_format((float) $entry->debit, 2, ',', '.') : '' }}</td>
                            <td class="is-end">{{ (float) $entry->credit ? number_format((float) $entry->credit, 2, ',', '.') : '' }}</td>
                            <td class="is-end"><x-ui.balance :value="$entry->running_balance" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <x-table.pager :items="$entries->fragment('cari-hareketleri')" />
        @else
            <p class="panel-hint">Bu cari kartta henüz hareket yok.</p>
        @endif
    </x-form.group>
</div>
