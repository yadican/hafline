<form method="POST" action="{{ route('admin.vehicle-classes.update', $vehicleClass) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$vehicleClass">
        <x-form.group legend="Araç sınıfı" icon="fa-solid fa-layer-group" description="Sınıf adı ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="60" required autofocus />
            <x-form.checkbox name="hourly" label="Ücretlendirme" text="Saatlik çalışır (makine çalışma fişiyle ücretlenir)" />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" />
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.vehicle-classes.index')" />
    </x-form.grid>
</form>
