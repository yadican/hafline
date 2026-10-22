<div class="panel-liste-bar">
    <x-search.form :action="route('admin.employee-payments.index')">
        <x-search.select searchable name="employee_id" label="Personel" placeholder="Tüm personel" :options="$options['employees']" :value="$filters['employee_id'] ?? null" class="md:col-span-3" />
        <x-search.select searchable hide-search name="month" label="Ay" placeholder="Ay" :options="$options['months']" :value="$filters['month'] ?? null" class="md:col-span-2" />
        <x-search.select searchable hide-search name="year" label="Yıl" placeholder="Yıl" :options="$options['years']" :value="$filters['year'] ?? null" class="md:col-span-2" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-2">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.employee-payments.index')" class="md:col-span-1">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.employee-payments.create')" class="md:col-span-2">Yeni ödeme</x-ui.button>
    </x-search.form>
</div>
