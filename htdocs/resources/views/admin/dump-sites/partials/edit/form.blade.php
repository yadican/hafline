<form method="POST" action="{{ route('admin.dump-sites.update', $dumpSite) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$dumpSite">
        <x-form.group legend="Döküm yeri" icon="fa-solid fa-dumpster" description="Döküm yeri adı ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" />
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.dump-sites.index')" />
    </x-form.grid>
</form>
