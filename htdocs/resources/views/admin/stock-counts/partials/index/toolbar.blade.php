<div class="panel-liste-bar">
    <x-search.form :action="route('admin.stock-counts.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Kalem, açıklama" maxlength="100" class="md:col-span-3" />
        <x-search.select searchable name="stock_item_id" label="Kalem" placeholder="Tüm kalemler" :options="$options['stock_items']" :value="$filters['stock_item_id'] ?? null" class="md:col-span-3" />
        <x-search.text name="from" label="Başlangıç" type="date" :value="$filters['from'] ?? null" class="md:col-span-1" />
        <x-search.text name="to" label="Bitiş" type="date" :value="$filters['to'] ?? null" class="md:col-span-1" />

        <div class="flex flex-wrap gap-2 md:col-span-4">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
            @if (! empty($filters))
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.stock-counts.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            <x-ui.button variant="create" icon="fa-solid fa-clipboard-check" :href="route('admin.stock-counts.create')" class="w-full justify-center sm:w-auto">Yeni sayım</x-ui.button>
        </div>
    </x-search.form>
</div>
