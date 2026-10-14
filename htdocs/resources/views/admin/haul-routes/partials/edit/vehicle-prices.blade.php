<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="Araca özel fiyatlar" icon="fa-solid fa-truck" description="Bu güzergahta sınıf fiyatı yerine geçen araç fiyatları" class="is-wide">
        @if ($haulRoute->vehiclePrices->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Plaka</th>
                        <th>Sınıf</th>
                        <th class="is-end">Fiyat</th>
                        <th class="is-end"><span class="sr-only">İşlem</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($haulRoute->vehiclePrices->sortBy(fn ($price) => $price->vehicle?->plate_key) as $vehiclePrice)
                        <tr>
                            <td><span class="panel-plate-text">{{ $vehiclePrice->vehicle?->plate }}</span></td>
                            <td>{{ $vehiclePrice->vehicle?->vehicleClass?->name ?: '—' }}</td>
                            <td class="is-end"><x-ui.money :value="$vehiclePrice->price" /></td>
                            <x-table.actions
                                :delete="route('admin.haul-routes.vehicle-prices.destroy', [$haulRoute, $vehiclePrice])"
                                confirm="Araca özel fiyat kaldırılsın mı?"
                                :confirm-text="$vehiclePrice->vehicle?->plate.' bu güzergahta yeniden sınıf fiyatından hesaplanacak.'"
                            />
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Bu güzergahta araca özel fiyat yok; tüm araçlar sınıf fiyatından hesaplanıyor.</p>
        @endif

        <form method="POST" action="{{ route('admin.haul-routes.vehicle-prices.store', $haulRoute) }}" class="panel-inline-form">
            @csrf
            <x-form.autocomplete name="vehicle_plate" label="Plaka" :url="route('admin.hauls.lookups.vehicles')" placeholder="34 ABC 123" class="panel-plate" maxlength="20" required />
            <x-form.money name="vehicle_price" label="Fiyat" placeholder="0,00" required />
            <x-ui.button type="submit" variant="create" icon="fa-solid fa-plus">Ekle</x-ui.button>
        </form>
        <p class="panel-hint">Aynı araç için yeniden fiyat girilirse eskisinin yerine geçer.</p>
    </x-form.group>
</div>
