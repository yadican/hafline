<div class="panel-liste-bar">
    <x-search.form :action="route('admin.haul-routes.index')">
        <x-search.select searchable name="site" label="Şantiye" placeholder="Tüm şantiyeler" :options="$options['sites']" :value="$filters['site'] ?? null" class="md:col-span-3" />
        <x-search.select searchable name="dump_site" label="Döküm yeri" placeholder="Tüm döküm yerleri" :options="$options['dumpSites']" :value="$filters['dump_site'] ?? null" class="md:col-span-3" />
        <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-2" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-1">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.haul-routes.index')" class="md:col-span-1">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.haul-routes.create')" class="md:col-span-2">Yeni güzergah</x-ui.button>
    </x-search.form>
</div>
