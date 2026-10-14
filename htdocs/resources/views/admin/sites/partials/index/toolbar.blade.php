<div class="panel-liste-bar">
    <x-search.form :action="route('admin.sites.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Şantiye adı" maxlength="100" />
        <x-search.select name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.sites.index')">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.sites.create')">Yeni şantiye</x-ui.button>
    </x-search.form>
</div>
