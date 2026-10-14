<div class="panel-liste-bar">
    <x-search.form :action="route('admin.fuel-stations.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="İstasyon adı" maxlength="100" />
        <x-search.select name="site" label="Şantiye" placeholder="Tüm şantiyeler" :options="$sites" :value="$filters['site'] ?? null" />
        <x-search.select name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.fuel-stations.index')">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.fuel-stations.create')">Yeni istasyon</x-ui.button>
    </x-search.form>
</div>
