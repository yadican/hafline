<div class="panel-liste-bar">
    <x-search.form :action="route('admin.employee-payments.index')">
        <x-search.select searchable name="employee_id" label="Personel" placeholder="Tüm personel" :options="$options['employees']" :value="$filters['employee_id'] ?? null" class="md:col-span-4" />
        <x-search.select searchable hide-search name="month" label="Ay" placeholder="Ay" :options="$options['months']" :value="$filters['month'] ?? null" class="md:col-span-2" />
        <x-search.select searchable hide-search name="year" label="Yıl" placeholder="Yıl" :options="$options['years']" :value="$filters['year'] ?? null" class="md:col-span-2" />

        <div class="flex flex-wrap gap-2 md:col-span-4">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-search" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
            @if (! empty($filters))
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.employee-payments.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            <x-ui.button variant="create" icon="fa-solid fa-money-bill-transfer" :href="route('admin.employee-payments.create')" class="w-full justify-center sm:w-auto">Yeni ödeme</x-ui.button>
        </div>
    </x-search.form>
</div>
