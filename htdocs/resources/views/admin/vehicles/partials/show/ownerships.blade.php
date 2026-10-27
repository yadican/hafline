<div class="panel-form-grid panel-form-grid-after" id="sahiplik">
    <x-form.group legend="Sahiplik geçmişi" icon="fa-solid fa-people-arrows" description="Taşıma, fiş tarihinde aracın kime kayıtlı olduğuna göre o carinin hesabına yazılır" class="is-wide">
        @if ($vehicle->ownerships->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Sahip</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th>Devirde eski sahibin bakiyesi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vehicle->ownerships as $ownership)
                        <tr>
                            <td>
                                @if ($ownership->party && ! $ownership->party->trashed())
                                    <a href="{{ route('admin.parties.show', $ownership->party) }}">{{ $ownership->party->name }}</a>
                                @else
                                    {{ $ownership->party?->name }}
                                @endif
                            </td>
                            <td>{{ $ownership->started_on->format('d.m.Y') }}</td>
                            <td>{{ $ownership->ended_on?->format('d.m.Y') ?? 'Halen' }}</td>
                            <td>
                                @if ($ownership->carried_balance === null)
                                    —
                                @elseif ((float) $ownership->carried_balance === 0.0)
                                    Hesaplaşıldı, aktarılan bakiye yok
                                @else
                                    {{ number_format(abs((float) $ownership->carried_balance), 2, ',', '.') }} TL
                                    {{ (float) $ownership->carried_balance > 0 ? 'alacaklı' : 'borçlu' }} · yeni sahibe aktarıldı
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Bu aracın kayıtlı sahibi yok; taşıma fişi girilemez.</p>
        @endif
    </x-form.group>
</div>
