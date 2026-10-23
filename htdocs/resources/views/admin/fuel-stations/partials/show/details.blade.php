<div class="panel-form-grid">
    <x-form.group legend="İstasyon" icon="fa-solid fa-gas-pump" description="Yakıt alımları işletmecinin cari hesabına yazılır">
        <x-form.field label="Ad">
            <div>{{ $fuelStation->name }}</div>
        </x-form.field>
        <x-form.field label="İşletmeci (cari)">
            <div>{{ $fuelStation->party?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Şantiyeler">
            <div>{{ $fuelStation->sites->pluck('name')->join(', ') ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$fuelStation->active" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Fiyat" icon="fa-solid fa-tag" description="Litre başına güncel birim fiyat">
        <x-form.field label="Birim fiyat">
            <div><x-ui.money :value="$fuelStation->unit_price" :decimals="4" suffix="TL/lt" /></div>
        </x-form.field>
    </x-form.group>
</div>
