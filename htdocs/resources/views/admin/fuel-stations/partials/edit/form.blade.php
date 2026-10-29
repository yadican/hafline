<form method="POST" action="{{ route('admin.fuel-stations.update', $fuelStation) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$fuelStation">
        <x-form.group legend="Yakıt yeri" icon="fa-solid fa-gas-pump" description="Ad, tür ve ürün">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-form.field label="Tür">
                    <div class="panel-choices" role="radiogroup">
                        @foreach (\App\Enums\FuelSourceType::options() as $typeValue => $typeLabel)
                            <label class="panel-choice" title="Tür kayıttan sonra değiştirilemez">
                                <input type="radio" value="{{ $typeValue }}" @checked($fuelStation->type->value === $typeValue) disabled>
                                {{ $typeLabel }}
                                @if ($fuelStation->type->value === $typeValue)
                                    <span class="panel-choice-lock" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </x-form.field>
                <x-form.radios name="product" label="Ürün" :options="\App\Enums\FuelProduct::options()" />
            </div>
            <p class="panel-hint">
                @if ($fuelStation->isTank())
                    Saha pompasında yakıt bizimdir; dolumlarla stok tutulur.
                @else
                    İstasyonda yakıt firmanındır; fişler firmaya alış fiyatıyla alacak yazar.
                @endif
            </p>
            <x-form.checkbox name="active" label="Durum" text="Yakıt fişlerinde seçilebilsin" />
        </x-form.group>

        <x-form.group legend="Bağlantılar" icon="fa-solid fa-link" :description="$fuelStation->isTank() ? 'Şantiyeler' : 'Akaryakıt firması ve şantiyeler'">
            @unless ($fuelStation->isTank())
                <x-form.select name="party_id" label="Akaryakıt firması (cari)" :options="$parties" placeholder="Cari kart seçin" searchable required />
            @endunless
            <x-form.select name="site_ids" label="Şantiyeler" :options="$sites" :value="$fuelStation->sites->pluck('id')->all()" placeholder="Şantiye seçin" multiple>
                <p class="panel-hint">Yakıt fişinde şantiye seçildiğinde yalnızca o şantiyeye bağlı yakıt yerleri listelenir.</p>
            </x-form.select>
        </x-form.group>

        <x-form.group legend="Fiyat" icon="fa-solid fa-tag" description="Litre başına güncel fiyatlar; kayıtlı fişlerin fiyatı değişmez" class="is-wide">
            <div class="grid gap-3 sm:grid-cols-2">
                @unless ($fuelStation->isTank())
                    <x-form.money name="purchase_price" label="Alış fiyatı" step="0.0001" suffix="TL/lt" placeholder="0,0000" required />
                @endunless
                <x-form.money name="unit_price" label="Satış fiyatı" step="0.0001" suffix="TL/lt" placeholder="0,0000" required />
            </div>
            @if ($fuelStation->isTank())
                <p class="panel-hint">Pompadan verilen yakıtın maliyeti dolumların ağırlıklı ortalamasıdır.</p>
            @endif
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.fuel-stations.index')" />
    </x-form.grid>
</form>
