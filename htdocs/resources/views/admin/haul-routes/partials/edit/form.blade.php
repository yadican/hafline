<form method="POST" action="{{ route('admin.haul-routes.update', $haulRoute) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$haulRoute">
        <x-form.group legend="Güzergah" icon="fa-solid fa-route" description="Şantiye, döküm yeri ve kullanım durumu">
            <x-form.select name="site_id" label="Şantiye" :options="$options['sites']" placeholder="Şantiye seçin" required />
            <x-form.select name="dump_site_id" label="Döküm yeri" :options="$options['dumpSites']" placeholder="Döküm yeri seçin" required />
            <x-form.money name="price" label="Temel fiyat" placeholder="0,00" />
            <x-form.money name="bonus" label="Prim" placeholder="0,00" />
            <x-form.checkbox name="active" label="Durum" text="Taşıma fişlerinde seçilebilsin" />
        </x-form.group>

        <x-form.group legend="Sınıf fiyatları" icon="fa-solid fa-layer-group" description="Sefer başına araç sınıfı fiyatı">
            <x-form.class-prices :classes="$options['classes']" :prices="$haulRoute->classPrices->pluck('price', 'vehicle_class_id')->all()">
                <p class="panel-hint">Fiş tutarı aracın sınıf fiyatından hesaplanır; araca özel fiyat varsa o kullanılır. Kayıtlı fişler düzenlenmedikçe eski tutarıyla kalır.</p>
            </x-form.class-prices>
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.haul-routes.index')" />
    </x-form.grid>
</form>
