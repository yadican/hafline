<div class="panel-liste-bar haul-search-bar report-filters">
    <x-search.form :action="route('admin.reports.invoice-aging')">
        <x-search.select
            searchable
            hide-search
            name="direction"
            label="Tür"
            placeholder="Tür seçin"
            :options="$options['directions']"
            :value="$filters['direction'] ?? null"
            required
            class="md:col-span-3"
        />
        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-1">
            Rapor
        </x-ui.button>
    </x-search.form>
</div>
