<div class="panel-form-grid panel-form-grid-after" id="cari-hareketleri">
    <x-form.group legend="Cari hareketleri" icon="fa-solid fa-scale-balanced" description="Borç: cari bize borçlu / biz ödedik · Alacak: biz cariye borçluyuz" class="is-wide">
        <div class="panel-ledger-totals">
            <span>Toplam borç <x-ui.money :value="$totals['debit']" /></span>
            <span>Toplam alacak <x-ui.money :value="$totals['credit']" /></span>
            <span>Bakiye <x-ui.balance :value="$totals['balance']" /></span>
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
                                    <a href="{{ route('admin.hauls.edit', $entry->source) }}">{{ $entry->description }}</a>
                                @elseif ($entry->source_type === 'machine_work' && $entry->source && ! $entry->source->trashed())
                                    <a href="{{ route('admin.machine-works.edit', $entry->source) }}">{{ $entry->description }}</a>
                                @elseif ($entry->source_type === 'fuel_receipt' && $entry->source && ! $entry->source->trashed())
                                    <a href="{{ route('admin.fuel-receipts.edit', $entry->source) }}">{{ $entry->description }}</a>
                                @elseif (in_array($entry->source_type, ['company_transaction', 'vehicle_payment', 'vehicle_receivable', 'party_payment', 'dump_site_payment'], true) && $entry->source && ! $entry->source->trashed())
                                    @php
                                        $cariKind = match ($entry->source_type) {
                                            'company_transaction' => $entry->source->role->value,
                                            'party_payment' => 'owner_payment',
                                            'dump_site_payment' => 'dump_payment',
                                            default => $entry->source_type,
                                        };
                                    @endphp
                                    <a href="{{ route('admin.cari-entries.edit', ['kind' => $cariKind, 'entry' => $entry->source_id]) }}">{{ $entry->description }}</a>
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
