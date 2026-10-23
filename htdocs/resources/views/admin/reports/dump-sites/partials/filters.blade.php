@php
    $period = $filters['period'] ?? 'month';
@endphp

<div class="panel-liste-bar haul-search-bar report-filters">
    <x-search.form :action="route('admin.reports.dump-sites')" data-report-search>
        <x-search.select
            searchable
            name="dump_site"
            label="Döküm Yerleri"
            placeholder="Döküm yeri seçin"
            :options="$options['dumpSites']"
            :value="$filters['dump_site'] ?? null"
            required
            class="md:col-span-3"
        />
        <x-search.select
            searchable
            hide-search
            name="class"
            label="Plaka Tipi"
            placeholder="Tüm plaka tipleri"
            :options="$options['classes']"
            :value="$filters['class'] ?? null"
            class="md:col-span-2"
        />
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

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="md:col-span-1">
            Rapor
        </x-ui.button>
    </x-search.form>
</div>
