<form method="POST" action="{{ route('admin.vehicles.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="Araç" icon="fa-solid fa-truck" description="Plaka, sınıf ve kullanım durumu">
            <x-form.input name="plate" label="Plaka" maxlength="20" placeholder="34 ABC 123" class="panel-plate" autocomplete="off" required autofocus />
            <x-form.select name="vehicle_class_id" label="Sınıf" :options="$options['classes']" placeholder="Sınıf seçin" required />
            <x-form.checkbox name="active" label="Durum" text="Taşıma fişlerinde seçilebilsin" checked />
        </x-form.group>

        <x-form.group legend="Sahip ve şoför" icon="fa-solid fa-id-card" description="Aracın sahibi ve kullanan şoför">
            <x-form.select name="owner_id" label="Araç sahibi" :options="$options['owners']" placeholder="Sahip seçin" />
            <x-form.input name="driver_name" label="Şoför" maxlength="80" />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.vehicles.index')" />
    </x-form.grid>
</form>
