<div class="panel-liste-bar haul-search-bar report-filters">
    <x-search.form :action="route('admin.reports.employee-monthly')" data-report-search>
        <x-search.select
            searchable
            hide-search
            name="month"
            label="Ay"
            :placeholder="null"
            :options="$options['months']"
            :value="$filters['month'] ?? null"
            required
            class="md:col-span-3"
        />
        <x-search.select
            searchable
            hide-search
            name="year"
            label="Yıl"
            :placeholder="null"
            :options="$options['years']"
            :value="$filters['year'] ?? null"
            required
            class="md:col-span-3"
        />
        <div class="flex flex-wrap gap-2 md:col-span-4">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-file-invoice-dollar" class="w-full justify-center sm:w-auto">Raporu Göster</x-ui.button>
        </div>
    </x-search.form>
</div>
