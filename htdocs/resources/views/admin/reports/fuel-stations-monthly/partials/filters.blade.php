@php
    $dayOptions = collect(range(1, 31))->mapWithKeys(fn (int $day): array => [$day => (string) $day])->all();
@endphp

<div class="panel-liste-bar haul-search-bar report-filters">
    <x-search.form :action="route('admin.reports.fuel-stations-monthly')" data-report-search>
        <x-search.select
            searchable
            name="fuel_station"
            label="Yakıt Yeri"
            placeholder="Yakıt yeri"
            :options="$options['stations']"
            :value="$filters['fuel_station'] ?? null"
            required
            class="md:col-span-3"
        />
        <x-search.select
            searchable
            hide-search
            name="class"
            label="Araç Tipi"
            placeholder="Araç tipi"
            :options="$options['classes']"
            :value="$filters['class'] ?? null"
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
            class="md:col-span-1"
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
            name="day_from"
            label="Gün başlangıç"
            placeholder="Gün1"
            :options="$dayOptions"
            :value="$filters['day_from'] ?? null"
            class="md:col-span-1"
        />
        <x-search.select
            searchable
            hide-search
            name="day_to"
            label="Gün bitiş"
            placeholder="Gün2"
            :options="$dayOptions"
            :value="$filters['day_to'] ?? null"
            class="md:col-span-1"
        />
        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-2">
            Rapor
        </x-ui.button>
    </x-search.form>
</div>
