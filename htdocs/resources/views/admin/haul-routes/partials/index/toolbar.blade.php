<div class="panel-liste-bar">
    <x-search.form :action="route('admin.haul-routes.index')">
        <x-search.select name="site" label="Şantiye" placeholder="Tüm şantiyeler" :options="$options['sites']" :value="$filters['site'] ?? null" />
        <x-search.select name="dump_site" label="Döküm yeri" placeholder="Tüm döküm yerleri" :options="$options['dumpSites']" :value="$filters['dump_site'] ?? null" />
        <x-search.select name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.haul-routes.index')">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.haul-routes.create')">Yeni güzergah</x-ui.button>
    </x-search.form>
</div>
