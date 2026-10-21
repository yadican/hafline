<div class="panel-liste-bar">
    <x-search.form :action="route('admin.vehicle-classes.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Sınıf adı" maxlength="100" class="md:col-span-3" />
        <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-2" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-2">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.vehicle-classes.index')" class="md:col-span-2">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.vehicle-classes.create')" class="md:col-span-2">Yeni araç sınıfı</x-ui.button>
    </x-search.form>
</div>
