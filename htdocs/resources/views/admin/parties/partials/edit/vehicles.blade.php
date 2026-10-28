@php
    $plateBalances ??= [];
    $temporary = $party->isTemporary();
@endphp

@if ($party->ownerships->isNotEmpty())
    <div class="panel-form-grid panel-form-grid-after">
        <x-form.group
            legend="Araçları"
            icon="fa-solid fa-truck"
            :description="$temporary
                ? 'Sahada kayıtsız gelen plakalar. Gün sonunda her plakayı asıl sahibine aktarın ya da plakaya ödeme yapıp bakiyeyi sıfırlayın.'
                : 'Bu cariye kayıtlı olan ve geçmişte kayıtlı olmuş araçlar'"
            class="is-wide"
        >
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Plaka</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th>Durum</th>
                        @if ($temporary)
                            <th class="is-end">Plakadaki bakiye</th>
                            <th class="is-end"><span class="sr-only">İşlem</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($party->ownerships as $ownership)
                        <tr>
                            <td>
                                @if ($ownership->vehicle && ! $ownership->vehicle->trashed())
                                    <a href="{{ route('admin.vehicles.show', $ownership->vehicle) }}" class="panel-plate-text">{{ $ownership->vehicle->plate }}</a>
                                @else
                                    <span class="panel-plate-text">{{ $ownership->vehicle?->plate }}</span>
                                @endif
                            </td>
                            <td>{{ $ownership->started_on->format('d.m.Y') }}</td>
                            <td>{{ $ownership->ended_on?->format('d.m.Y') ?? '—' }}</td>
                            <td>{{ $ownership->ended_on ? 'Devredildi' : 'Halen sahibi' }}</td>
                            @if ($temporary)
                                @php($balance = $plateBalances[$ownership->vehicle_id] ?? null)
                                <td class="is-end">
                                    @if ($ownership->ended_on || $balance === null)
                                        —
                                    @else
                                        {{ number_format(abs($balance), 2, ',', '.') }} TL {{ $balance > 0 ? 'alacaklı' : ($balance < 0 ? 'borçlu' : '') }}
                                    @endif
                                </td>
                                <td class="is-end">
                                    @if (! $ownership->ended_on && $ownership->vehicle && ! $ownership->vehicle->trashed())
                                        <a href="{{ route('admin.vehicles.edit', $ownership->vehicle) }}#sahiplik">Asıl sahibine aktar</a>
                                        ·
                                        <a href="{{ route('admin.cari-entries.create', ['kind' => 'vehicle_payment', 'plate' => $ownership->vehicle->plate]) }}">Plakaya ödeme</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-form.group>
    </div>
@endif
