<form method="POST" action="{{ route('admin.sites.update', $site) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$site">
        <x-form.group legend="Şantiye" icon="fa-solid fa-helmet-safety" description="Şantiye adı ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" />
        </x-form.group>

        @include('admin.sites.partials.form.parties')

        <x-form.actions label="Güncelle" :cancel="route('admin.sites.index')" />
    </x-form.grid>
</form>
