<div class="panel-liste-bar">
    <x-search.form :action="route('admin.employee-bonuses.index')">
        <x-search.select searchable name="employee_id" label="Personel" placeholder="Tüm personel" :options="$options['employees']" :value="$filters['employee_id'] ?? null" class="md:col-span-4" />
        <x-form.input name="from" label="" type="date" :value="$filters['from'] ?? null" placeholder="Başlangıç" aria-label="Başlangıç" class="md:col-span-2" />
        <x-form.input name="to" label="" type="date" :value="$filters['to'] ?? null" placeholder="Bitiş" aria-label="Bitiş" class="md:col-span-2" />

        <div class="flex flex-wrap gap-2 md:col-span-4">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-search" class="w-full justify-center sm:w-auto">Bul</x-ui.button>
            @if (! empty($filters))
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.employee-bonuses.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            <x-ui.button variant="create" icon="fa-solid fa-gift" :href="route('admin.employee-bonuses.create')" class="w-full justify-center sm:w-auto">Yeni prim</x-ui.button>
        </div>
    </x-search.form>
</div>
