<form method="POST" action="{{ route('admin.sites.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="Şantiye" icon="fa-solid fa-helmet-safety" description="Şantiye adı ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" checked />
        </x-form.group>

        @include('admin.sites.partials.form.parties', ['partyValues' => []])

        <x-form.actions label="Kaydet" :cancel="route('admin.sites.index')" />
    </x-form.grid>
</form>
