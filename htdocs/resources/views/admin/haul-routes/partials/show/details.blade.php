<div class="panel-form-grid">
    <x-form.group legend="Güzergah" icon="fa-solid fa-route" description="Şantiye, döküm yeri ve kullanım durumu">
        <x-form.field label="Şantiye">
            <div>{{ $haulRoute->site?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Döküm yeri">
            <div>{{ $haulRoute->dumpSite?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Temel fiyat">
            <div><x-ui.money :value="$haulRoute->price" /></div>
        </x-form.field>
        <x-form.field label="Prim">
            <div><x-ui.money :value="$haulRoute->bonus" /></div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$haulRoute->active" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Sınıf fiyatları" icon="fa-solid fa-layer-group" description="Sefer başına araç sınıfı fiyatı">
        <x-form.field label="Sınıf fiyatları">
            <div><x-ui.class-prices :prices="$haulRoute->classPrices" /></div>
        </x-form.field>
        <x-form.field label="Şantiye satış fiyatları">
            <div><x-ui.class-prices :prices="$haulRoute->sitePrices" /></div>
        </x-form.field>
    </x-form.group>
</div>

<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="Araca özel fiyatlar" icon="fa-solid fa-truck" description="Bu güzergahta sınıf fiyatı yerine geçen araç fiyatları" class="is-wide">
        @if ($haulRoute->vehiclePrices->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Plaka</th>
                        <th>Sınıf</th>
                        <th class="is-end">Fiyat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($haulRoute->vehiclePrices->sortBy(fn ($price) => $price->vehicle?->plate_key) as $vehiclePrice)
                        <tr>
                            <td><span class="panel-plate-text">{{ $vehiclePrice->vehicle?->plate }}</span></td>
                            <td>{{ $vehiclePrice->vehicle?->vehicleClass?->name ?: '—' }}</td>
                            <td class="is-end"><x-ui.money :value="$vehiclePrice->price" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Bu güzergahta araca özel fiyat yok; tüm araçlar sınıf fiyatından hesaplanıyor.</p>
        @endif
    </x-form.group>
</div>
