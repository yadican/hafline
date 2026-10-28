<div class="panel-form-grid">
    <x-form.group legend="Yakıt kaynağı" icon="fa-solid fa-gas-pump" :description="$fuelStation->isTank() ? 'Kendi depomuz; yakıt bizimdir, stok tutulur' : 'İstasyon; fişler firmaya alış fiyatıyla alacak yazar'">
        <x-form.field label="Ad">
            <div>{{ $fuelStation->name }}</div>
        </x-form.field>
        <x-form.field label="Tür">
            <div>{{ $fuelStation->type->label() }} · {{ $fuelStation->product->label() }}</div>
        </x-form.field>
        @unless ($fuelStation->isTank())
            <x-form.field label="Akaryakıt firması (cari)">
                <div>{{ $fuelStation->party?->name ?: '—' }}</div>
            </x-form.field>
        @endunless
        <x-form.field label="Şantiyeler">
            <div>{{ $fuelStation->sites->pluck('name')->join(', ') ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$fuelStation->active" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Fiyat" icon="fa-solid fa-tag" description="Litre başına güncel fiyatlar">
        @unless ($fuelStation->isTank())
            <x-form.field label="Alış fiyatı">
                <div><x-ui.money :value="$fuelStation->purchase_price" :decimals="4" suffix="TL/lt" /></div>
            </x-form.field>
        @endunless
        <x-form.field label="Satış fiyatı">
            <div><x-ui.money :value="$fuelStation->unit_price" :decimals="4" suffix="TL/lt" /></div>
        </x-form.field>
    </x-form.group>
</div>

@if ($tank)
    @include('admin.fuel-stations.partials.show.tank')
@endif
