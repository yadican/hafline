<form method="POST" action="{{ route('admin.vehicles.update', $vehicle) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$vehicle">
        <x-form.group legend="Araç" icon="fa-solid fa-truck" description="Plaka, sınıf ve kullanım durumu">
            <x-form.input name="plate" label="Plaka" maxlength="20" placeholder="34 ABC 123" class="panel-plate" autocomplete="off" required autofocus />
            <x-form.select name="vehicle_class_id" label="Sınıf" :options="$options['classes']" placeholder="Sınıf seçin" required />
            <x-form.checkbox name="active" label="Durum" text="Taşıma ve makine fişlerinde seçilebilsin" />
        </x-form.group>

        <x-form.group legend="Şoför" icon="fa-solid fa-id-badge" description="Aracı kullanan şoför">
            <x-form.input name="driver_name" label="Şoför" maxlength="80" />
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.vehicles.index')" />
    </x-form.grid>
</form>
