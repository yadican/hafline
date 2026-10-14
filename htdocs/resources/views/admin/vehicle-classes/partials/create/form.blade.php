<form method="POST" action="{{ route('admin.vehicle-classes.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="Araç sınıfı" icon="fa-solid fa-layer-group" description="Sınıf adı ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="60" required autofocus />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" checked />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.vehicle-classes.index')" />
    </x-form.grid>
</form>
