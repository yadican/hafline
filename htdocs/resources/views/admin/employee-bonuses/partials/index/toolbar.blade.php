<div class="panel-liste-bar">
    <x-search.form :action="route('admin.employee-bonuses.index')">
        <x-search.select searchable name="employee_id" label="Personel" placeholder="Tüm personel" :options="$options['employees']" :value="$filters['employee_id'] ?? null" class="md:col-span-3" />
        <x-form.input name="from" label="" type="date" :value="$filters['from'] ?? null" placeholder="Başlangıç" aria-label="Başlangıç" class="md:col-span-2" />
        <x-form.input name="to" label="" type="date" :value="$filters['to'] ?? null" placeholder="Bitiş" aria-label="Bitiş" class="md:col-span-2" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-2">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.employee-bonuses.index')" class="md:col-span-1">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.employee-bonuses.create')" class="md:col-span-2">Yeni prim</x-ui.button>
    </x-search.form>
</div>
