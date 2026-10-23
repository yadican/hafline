<div class="panel-liste-bar">
    <x-search.form :action="route('admin.employees.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Ad, telefon" maxlength="100" class="md:col-span-5" />
        <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-3" />

        <div class="flex flex-wrap gap-2 md:col-span-4">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
            @if (! empty($filters))
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.employees.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            <x-ui.button variant="create" icon="fa-solid fa-id-card" :href="route('admin.employees.create')" class="w-full justify-center sm:w-auto">Yeni personel</x-ui.button>
        </div>
    </x-search.form>
</div>
