<div class="panel-liste-bar">
    <x-search.form :action="route('admin.tags.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Etiket adı" maxlength="60" class="md:col-span-4" />
        <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-2" />

        <div class="flex flex-wrap gap-2 md:col-span-6">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
            @if (! empty($filters))
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.tags.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            @can('create-tag')
                <x-ui.button variant="create" icon="fa-solid fa-tag" :href="route('admin.tags.create')" class="w-full justify-center sm:w-auto">Yeni etiket</x-ui.button>
            @endcan
        </div>
    </x-search.form>
</div>
