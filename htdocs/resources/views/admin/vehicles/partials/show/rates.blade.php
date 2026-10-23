<div class="panel-form-grid panel-form-grid-after" id="saat-ucretleri">
    <x-form.group legend="Saat ücretleri" icon="fa-solid fa-clock" description="Makine çalışma fişinde, fiş tarihinde geçerli ücret otomatik gelir" class="is-wide">
        @if ($vehicle->rates->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th class="is-end">Saat ücreti</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vehicle->rates as $rate)
                        <tr>
                            <td class="is-end"><x-ui.money :value="$rate->hourly_rate" /></td>
                            <td>{{ $rate->started_on->format('d.m.Y') }}</td>
                            <td>{{ $rate->ended_on?->format('d.m.Y') ?? 'Halen' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Bu makine için saat ücreti tanımlı değil; fişte ücret elle girilir.</p>
        @endif
    </x-form.group>
</div>
