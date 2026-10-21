<div class="panel-liste-bar haul-search-bar site-exit-filters">
    <x-search.form :action="route('admin.reports.site-exits')" data-report-search>
        <x-search.select
            searchable
            name="site"
            label="Şantiyeler"
            placeholder="Şantiye seçin"
            :options="$options['sites']"
            :value="$filters['site'] ?? null"
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
            class="md:col-span-2"
        />
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
            name="class"
            label="Plaka Tipi"
            placeholder="Tüm plaka tipleri"
            :options="$options['classes']"
            :value="$filters['class'] ?? null"
            class="md:col-span-3"
        />
        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-2">
            Raporu Göster
        </x-ui.button>
    </x-search.form>
</div>
