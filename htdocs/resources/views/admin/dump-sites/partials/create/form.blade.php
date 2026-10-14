<form method="POST" action="{{ route('admin.dump-sites.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="Döküm yeri" icon="fa-solid fa-dumpster" description="Döküm yeri adı ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" checked />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.dump-sites.index')" />
    </x-form.grid>
</form>
