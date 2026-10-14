<form method="POST" action="{{ route('admin.fuel-stations.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="İstasyon" icon="fa-solid fa-gas-pump" description="Ad, şantiye ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <x-form.select name="site_id" label="Şantiye" :options="$sites" placeholder="Şantiyeye bağlı değil" />
            <x-form.checkbox name="active" label="Durum" text="Yakıt fişlerinde seçilebilsin" checked />
        </x-form.group>

        <x-form.group legend="Fiyat" icon="fa-solid fa-tag" description="Litre başına güncel birim fiyat">
            <x-form.money name="unit_price" label="Birim fiyat" step="0.0001" suffix="TL/lt" placeholder="0,0000" required />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.fuel-stations.index')" />
    </x-form.grid>
</form>
