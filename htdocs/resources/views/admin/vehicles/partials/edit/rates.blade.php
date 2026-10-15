<div class="panel-form-grid panel-form-grid-after" id="saat-ucretleri">
    <x-form.group legend="Saat ücretleri" icon="fa-solid fa-clock" description="Makine çalışma fişinde, fiş tarihinde geçerli ücret otomatik gelir" class="is-wide">
        @if ($vehicle->rates->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th class="is-end">Saat ücreti</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th class="is-end"><span class="sr-only">İşlem</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vehicle->rates as $rate)
                        <tr>
                            <td class="is-end"><x-ui.money :value="$rate->hourly_rate" /></td>
                            <td>{{ $rate->started_on->format('d.m.Y') }}</td>
                            <td>{{ $rate->ended_on?->format('d.m.Y') ?? 'Halen' }}</td>
                            @if ($loop->first)
                                <x-table.actions
                                    :delete="route('admin.vehicles.rates.destroy', [$vehicle, $rate])"
                                    confirm="Son ücret dönemi silinsin mi?"
                                    :confirm-text="$rate->started_on->format('d.m.Y').' tarihli ücret silinecek; varsa önceki ücret yeniden geçerli olur. Kayıtlı fişlerin tutarı değişmez.'"
                                />
                            @else
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Bu makine için saat ücreti tanımlı değil; fişte ücret elle girilir.</p>
        @endif

        <form method="POST" action="{{ route('admin.vehicles.rates.store', $vehicle) }}" class="panel-inline-form">
            @csrf
            <x-form.money name="hourly_rate" label="Saat ücreti" required />
            <x-form.input name="rate_started_on" label="Geçerlilik başlangıcı" type="date" :value="today()" required />
            <x-ui.button type="submit" variant="create" icon="fa-solid fa-plus">Ücret ekle</x-ui.button>
        </form>
        <p class="panel-hint">Yeni ücret, başlangıç tarihinden bir gün öncesinde mevcut ücreti kapatır. Önceden girilmiş fişlerin tutarı değişmez.</p>
    </x-form.group>
</div>
