<div class="panel-liste-bar">
    <x-search.form :action="route('admin.fuel-stations.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="İstasyon adı" maxlength="100" class="md:col-span-3" />
        <x-search.select searchable name="party" label="İşletmeci" placeholder="Tüm işletmeciler" :options="$parties" :value="$filters['party'] ?? null" class="md:col-span-4" />
        <x-search.select searchable name="site" label="Şantiye" placeholder="Tüm şantiyeler" :options="$sites" :value="$filters['site'] ?? null" class="md:col-span-3" />
        <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-2" />

        <div class="col-span-full mt-1 flex flex-wrap justify-stretch gap-2 border-t border-[var(--ui-border-soft)] pt-3 sm:justify-end">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto" >Ara</x-ui.button>
            @if (! empty($filters))
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.fuel-stations.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            <x-ui.button variant="create" icon="fa-solid fa-gas-pump" :href="route('admin.fuel-stations.create')" class="w-full justify-center sm:w-auto">Yeni yakıt yeri</x-ui.button>        
        </div>

    </x-search.form>
</div>
