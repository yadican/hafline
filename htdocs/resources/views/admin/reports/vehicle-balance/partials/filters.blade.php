@php
    $period = $filters['period'] ?? 'month';
@endphp

<div class="panel-liste-bar haul-search-bar report-filters">
    <x-search.form :action="route('admin.reports.vehicle-balance')" data-report-search>
        <x-search.select
            searchable
            hide-search
            name="period"
            label="Dönem"
            :placeholder="null"
            :options="$options['periods']"
            :value="$period"
            required
            data-report-period
            class="md:col-span-2"
        />
        <div class="min-w-0 md:col-span-2" data-period-fields="month" @if ($period !== 'month') hidden @endif>
            <x-search.select
                searchable
                hide-search
                name="year"
                label="Yıl"
                :placeholder="null"
                :options="$options['years']"
                :value="$filters['year'] ?? null"
                :required="$period === 'month'"
                class="w-full"
            />
        </div>
        <div class="min-w-0 md:col-span-2" data-period-fields="month" @if ($period !== 'month') hidden @endif>
            <x-search.select
                searchable
                hide-search
                name="month"
                label="Ay"
                :placeholder="null"
                :options="$options['months']"
                :value="$filters['month'] ?? null"
                :required="$period === 'month'"
                class="w-full"
            />
        </div>
        <div class="min-w-0 md:col-span-2" data-period-fields="range" @if ($period !== 'range') hidden @endif>
            <x-form.input
                name="from"
                label=""
                type="date"
                :value="$filters['from'] ?? null"
                :required="$period === 'range'"
                placeholder="Başlangıç"
                aria-label="Başlangıç"
                class="w-full"
            />
        </div>
        <div class="min-w-0 md:col-span-2" data-period-fields="range" @if ($period !== 'range') hidden @endif>
            <x-form.input
                name="to"
                label=""
                type="date"
                :value="$filters['to'] ?? null"
                :required="$period === 'range'"
                placeholder="Bitiş"
                aria-label="Bitiş"
                class="w-full"
            />
        </div>
        <x-search.select
            searchable
            name="site"
            label="Şantiye"
            placeholder="Şantiye seçin"
            :options="$options['sites']"
            :value="$filters['site'] ?? null"
            required
            class="md:col-span-2"
        />
        <x-search.select
            searchable
            name="fuel_station"
            label="Yakıt Yeri"
            placeholder="Yakıt yeri seçin"
            :options="$options['stations']"
            :value="$filters['fuel_station'] ?? null"
            required
            class="md:col-span-2"
        />
        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-2">
            Raporu Göster
        </x-ui.button>
    </x-search.form>
</div>
