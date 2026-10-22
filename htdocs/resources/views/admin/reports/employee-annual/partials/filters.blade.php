<div class="panel-liste-bar haul-search-bar report-filters">
    <x-search.form :action="route('admin.reports.employee-annual')" data-report-search>
        <x-search.select
            searchable
            name="employee_id"
            label="Personel"
            placeholder="Personel seçin"
            :options="$options['employees']"
            :value="$filters['employee_id'] ?? null"
            required
            class="md:col-span-4"
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
            class="md:col-span-2"
        />
        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-2">
            Raporu Göster
        </x-ui.button>
    </x-search.form>
</div>
