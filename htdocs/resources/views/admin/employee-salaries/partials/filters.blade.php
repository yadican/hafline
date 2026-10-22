<div class="panel-liste-bar haul-search-bar report-filters">
    <x-search.form :action="route('admin.employee-salaries.index')" data-report-search>
        <x-search.select
            searchable
            hide-search
            name="month"
            label="Ay"
            :placeholder="null"
            :options="$options['months']"
            :value="$filters['month'] ?? null"
            required
            class="md:col-span-2"
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
            Bul
        </x-ui.button>
    </x-search.form>
</div>
