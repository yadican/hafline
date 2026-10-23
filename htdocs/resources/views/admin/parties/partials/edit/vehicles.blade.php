@if ($party->ownerships->isNotEmpty())
    <div class="panel-form-grid panel-form-grid-after">
        <x-form.group legend="Araçları" icon="fa-solid fa-truck" description="Bu cariye kayıtlı olan ve geçmişte kayıtlı olmuş araçlar" class="is-wide">
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Plaka</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th>Durum</th>
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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-form.group>
    </div>
@endif
