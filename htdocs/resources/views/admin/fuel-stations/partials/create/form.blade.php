<form method="POST" action="{{ route('admin.fuel-stations.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="İstasyon" icon="fa-solid fa-gas-pump" description="Yakıt alımları işletmecinin cari hesabına yazılır">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <x-form.select name="party_id" label="İşletmeci (cari)" :options="$parties" placeholder="Cari kart seçin" searchable required />
            <x-form.select name="site_ids" label="Şantiyeler" :options="$sites" placeholder="Şantiye seçin" multiple>
                <p class="panel-hint">Yakıt fişinde şantiye seçildiğinde yalnızca o şantiyeye bağlı istasyonlar listelenir.</p>
            </x-form.select>
            <x-form.checkbox name="active" label="Durum" text="Yakıt fişlerinde seçilebilsin" checked />
        </x-form.group>

        <x-form.group legend="Fiyat" icon="fa-solid fa-tag" description="Litre başına güncel birim fiyat">
            <x-form.money name="unit_price" label="Birim fiyat" step="0.0001" suffix="TL/lt" placeholder="0,0000" required />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.fuel-stations.index')" />
    </x-form.grid>
</form>
