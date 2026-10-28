<form method="POST" action="{{ route('admin.fuel-stations.update', $fuelStation) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$fuelStation">
        <x-form.group legend="Yakıt kaynağı" icon="fa-solid fa-gas-pump" :description="$fuelStation->isTank() ? 'Kendi depomuz; yakıt bizimdir, stok tutulur' : 'İstasyon; fişler firmaya alış fiyatıyla alacak yazar'">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <x-form.field label="Tür">
                <div>{{ $fuelStation->type->label() }}</div>
            </x-form.field>
            <x-form.radios name="product" label="Ürün" :options="\App\Enums\FuelProduct::options()" />
            @unless ($fuelStation->isTank())
                <x-form.select name="party_id" label="Akaryakıt firması (cari)" :options="$parties" placeholder="Cari kart seçin" searchable required />
            @endunless
            <x-form.select name="site_ids" label="Şantiyeler" :options="$sites" :value="$fuelStation->sites->pluck('id')->all()" placeholder="Şantiye seçin" multiple>
                <p class="panel-hint">Yakıt fişinde şantiye seçildiğinde yalnızca o şantiyeye bağlı kaynaklar listelenir.</p>
            </x-form.select>
            <x-form.checkbox name="active" label="Durum" text="Yakıt fişlerinde seçilebilsin" />
        </x-form.group>

        <x-form.group legend="Fiyat" icon="fa-solid fa-tag" description="Litre başına güncel fiyatlar; kayıtlı fişlerin fiyatı değişmez">
            @unless ($fuelStation->isTank())
                <x-form.money name="purchase_price" label="Alış fiyatı" step="0.0001" suffix="TL/lt" placeholder="0,0000" required />
            @endunless
            <x-form.money name="unit_price" label="Satış fiyatı" step="0.0001" suffix="TL/lt" placeholder="0,0000" required />
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.fuel-stations.index')" />
    </x-form.grid>
</form>
