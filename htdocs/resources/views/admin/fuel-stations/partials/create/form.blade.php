<form method="POST" action="{{ route('admin.fuel-stations.store') }}" x-data="{ type: @js(old('type', 'station')) }" @change="if ($event.target.name === 'type') type = $event.target.value">
    @csrf
    <x-form.grid :model="['type' => 'station', 'product' => 'diesel']">
        <x-form.group legend="Yakıt yeri" icon="fa-solid fa-gas-pump" description="Ad, tür ve ürün">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-form.radios name="type" label="Tür" :options="\App\Enums\FuelSourceType::options()" />
                <x-form.radios name="product" label="Ürün" :options="\App\Enums\FuelProduct::options()" />
            </div>
            <p class="panel-hint" x-show="type === 'station'" x-cloak>İstasyonda yakıt firmanındır; fişler firmaya alış fiyatıyla alacak yazar.</p>
            <p class="panel-hint" x-show="type === 'tank'" x-cloak>Saha pompasında yakıt bizimdir; dolumlarla stok tutulur.</p>
            <x-form.checkbox name="active" label="Durum" text="Yakıt fişlerinde seçilebilsin" checked />
        </x-form.group>

        <x-form.group legend="Bağlantılar" icon="fa-solid fa-link" description="Akaryakıt firması ve şantiyeler">
            <div x-show="type === 'station'" x-cloak>
                <x-form.select name="party_id" label="Akaryakıt firması (cari)" :options="$parties" placeholder="Cari kart seçin" searchable />
            </div>
            <x-form.select name="site_ids" label="Şantiyeler" :options="$sites" placeholder="Şantiye seçin" multiple>
                <p class="panel-hint">Yakıt fişinde şantiye seçildiğinde yalnızca o şantiyeye bağlı yakıt yerleri listelenir.</p>
            </x-form.select>
        </x-form.group>

        <x-form.group legend="Fiyat" icon="fa-solid fa-tag" description="Litre başına güncel fiyatlar" class="is-wide">
            <div class="grid gap-3 sm:grid-cols-2">
                <div x-show="type === 'station'" x-cloak>
                    <x-form.money name="purchase_price" label="Alış fiyatı" step="0.0001" suffix="TL/lt" placeholder="0,0000" />
                </div>
                <x-form.money name="unit_price" label="Satış fiyatı" step="0.0001" suffix="TL/lt" placeholder="0,0000" required />
            </div>
            <p class="panel-hint" x-show="type === 'tank'" x-cloak>Pompadan verilen yakıtın maliyeti dolumların ağırlıklı ortalamasıdır.</p>
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.fuel-stations.index')" />
    </x-form.grid>
</form>
