@php
    $latest = $vehicle->ownerships->first();
    $balanceText = fn (float $balance): string => number_format(abs($balance), 2, ',', '.').' TL '.($balance > 0 ? 'alacaklı' : 'borçlu');
@endphp

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
                        <th class="is-end"><span class="sr-only">İşlem</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vehicle->ownerships as $ownership)
                        <tr>
                            <td>
                                @if ($ownership->party && ! $ownership->party->trashed())
                                    <a href="{{ route('admin.parties.edit', $ownership->party) }}">{{ $ownership->party->name }}</a>
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
                                    {{ $balanceText((float) $ownership->carried_balance) }} · yeni sahibe aktarıldı
                                @endif
                            </td>
                            @if ($ownership->is($latest) && $vehicle->ownerships->count() > 1)
                                <x-table.actions
                                    :delete="route('admin.vehicles.ownerships.destroy', [$vehicle, $ownership])"
                                    confirm="Son devir geri alınsın mı?"
                                    :confirm-text="$ownership->party?->name.' sahipliği silinecek; önceki sahip yeniden güncel sahip olur, '.$ownership->started_on->format('d.m.Y').' sonrası taşıma, makine, yakıt fişleri ve plaka alacakları ona aktarılır ve devir kapanışı iptal edilir.'"
                                />
                            @else
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Bu aracın kayıtlı sahibi yok; taşıma fişi girilemez.</p>
        @endif

        <form method="POST" action="{{ route('admin.vehicles.ownerships.store', $vehicle) }}" class="panel-inline-form">
            @csrf
            <x-form.select name="party_id" label="Yeni sahip" :options="$options['parties']" placeholder="Cari kart seçin" searchable required />
            <x-form.input name="started_on" label="Devir tarihi" type="date" :value="today()" required />
            <x-form.radios name="settlement" label="Eski sahibin bu plakadaki bakiyesi" :options="[
                'carry' => 'Yeni sahibe aktar (devir kapanışı + devralınan bakiye)',
                'settled' => 'Eski sahiple hesaplaşıldı (bakiye sıfır olmalı)',
            ]" />
            <x-ui.button type="submit" variant="create" icon="fa-solid fa-right-left">Devret</x-ui.button>
        </form>
        @if ($ownerBalance !== null)
            <p class="panel-hint">
                Mevcut sahibin bu plakadaki bakiyesi bugün itibarıyla:
                <strong>{{ (float) $ownerBalance === 0.0 ? 'sıfır' : $balanceText($ownerBalance) }}</strong>.
                Devir tarihi geçmişteyse, o tarihten sonraki fişler yeni sahibe geçtiği için devirdeki tutar farklı olabilir.
            </p>
        @endif
        <p class="panel-hint">Devir tarihinden itibaren girilmiş taşıma, makine, yakıt fişleri ve plaka alacakları yeni sahibe aktarılır; eski sahibe yapılmış ödemeler onda kalır. Devirden sonra eski sahibin plakadaki bakiyesi sıfırlanır ve devir tarihinden önceki kayıtlar kilitlenir.</p>
    </x-form.group>
</div>
