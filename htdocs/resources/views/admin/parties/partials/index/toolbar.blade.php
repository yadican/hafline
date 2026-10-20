@php($isPerson = $type === \App\Enums\PartyType::Person)

<div class="panel-liste-bar">
    <x-search.form :action="route($type->indexRoute())">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" :placeholder="$isPerson ? 'Ad soyad, telefon, TCKN' : 'Unvan, telefon, VKN'" maxlength="100" />
        <x-search.select searchable hide-search name="role" label="Rol" placeholder="Tüm roller" :options="\App\Enums\PartyRole::options()" :value="$filters['role'] ?? null" />
        <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route($type->indexRoute())">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" :icon="$isPerson ? 'fa-solid fa-user-plus' : 'fa-solid fa-building'" :href="route('admin.parties.create', ['type' => $type->value])">{{ $isPerson ? 'Yeni kişi' : 'Yeni şirket' }}</x-ui.button>
    </x-search.form>
</div>
